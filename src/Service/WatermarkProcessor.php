<?php

declare(strict_types=1);

namespace AbWatermark\Service;

final class WatermarkProcessor
{
    private string $watermarkPath;

    public function __construct(string $watermarkPath)
    {
        $this->watermarkPath = $watermarkPath;
    }

    public function processImage(
        int $imageId,
        ?array $requestedTypes = null,
        bool $regenerate = false,
        bool $applyWatermark = true
    ): array {
        $result = ['processed' => [], 'skipped' => [], 'errors' => []];
        $image = new \Image($imageId);
        if (!\Validate::isLoadedObject($image)) {
            throw new \RuntimeException('Immagine PrestaShop non trovata: ' . $imageId);
        }

        $sourceBase = \_PS_PRODUCT_IMG_DIR_ . $image->getExistingImgPath();
        $originalPath = $sourceBase . '.jpg';
        if (!is_file($originalPath)) {
            throw new \RuntimeException('File originale non trovato: ' . $originalPath);
        }

        if ($applyWatermark && $this->isExcludedProduct((int) $image->id_product)) {
            $result['skipped'][] = 'prodotto escluso: ' . (int) $image->id_product;

            return $result;
        }

        $allTypes = \ImageType::getImagesTypes('products', true);
        if ($regenerate && !$applyWatermark) {
            // A safe cleanup must restore every product derivative, including
            // formats that were watermarked and later deselected in the module.
            $selectedTypes = array_column($allTypes, 'name');
        } else {
            $selectedTypes = $this->getSelectedTypes();
            if ($requestedTypes !== null) {
                $selectedTypes = array_values(array_intersect($selectedTypes, $requestedTypes));
            }
        }

        foreach ($allTypes as $type) {
            if (!in_array($type['name'], $selectedTypes, true)) {
                continue;
            }

            foreach ($this->getDerivativePaths($sourceBase, $type['name']) as $extension => $path) {
                try {
                    if ($regenerate) {
                        $this->regenerateDerivative($originalPath, $path, $type, $extension);
                    }
                    if (!is_file($path)) {
                        $result['skipped'][] = basename($path);
                        continue;
                    }
                    if (!$applyWatermark) {
                        $result['processed'][] = basename($path);
                        continue;
                    }
                    if ($this->shouldSkipForSize($path)) {
                        $result['skipped'][] = basename($path);
                        continue;
                    }
                    $this->applyWatermark($path);
                    $result['processed'][] = basename($path);
                } catch (\Throwable $exception) {
                    $result['errors'][] = basename($path) . ': ' . $exception->getMessage();
                }
            }
        }

        return $result;
    }

    private function getSelectedTypes(): array
    {
        $decoded = json_decode((string) \Configuration::get('ABW_IMAGE_TYPES'), true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function isExcludedProduct(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        if (in_array($productId, $this->parseIdList((string) \Configuration::get('ABW_EXCLUDED_PRODUCTS')), true)) {
            return true;
        }

        $excludedCategories = $this->parseIdList((string) \Configuration::get('ABW_EXCLUDED_CATEGORIES'));
        if (!$excludedCategories) {
            return false;
        }

        $productCategories = array_map('intval', \Product::getProductCategories($productId));

        return (bool) array_intersect($excludedCategories, $productCategories);
    }

    private function parseIdList(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $value))));
    }

    private function getDerivativePaths(string $sourceBase, string $type): array
    {
        $paths = ['jpg' => $sourceBase . '-' . $type . '.jpg'];
        foreach (['webp', 'avif'] as $extension) {
            $path = $sourceBase . '-' . $type . '.' . $extension;
            if (is_file($path)) {
                $paths[$extension] = $path;
            }
        }

        return $paths;
    }

    private function regenerateDerivative(string $source, string $destination, array $type, string $extension): void
    {
        if (!\ImageManager::resize(
            $source,
            $destination,
            (int) $type['width'],
            (int) $type['height'],
            $extension
        )) {
            throw new \RuntimeException('Rigenerazione non riuscita.');
        }
    }

    private function shouldSkipForSize(string $path): bool
    {
        $size = @getimagesize($path);
        if (!$size) {
            throw new \RuntimeException('Formato immagine non leggibile.');
        }
        $minimum = max(64, (int) \Configuration::get('ABW_MIN_SIZE'));

        return min((int) $size[0], (int) $size[1]) < $minimum;
    }

    private function applyWatermark(string $path): void
    {
        if (!is_file($this->watermarkPath)) {
            throw new \RuntimeException('File della filigrana non trovato.');
        }

        $destinationContents = @file_get_contents($path);
        $watermarkContents = @file_get_contents($this->watermarkPath);
        $destination = $destinationContents !== false ? @imagecreatefromstring($destinationContents) : false;
        $watermark = $watermarkContents !== false ? @imagecreatefromstring($watermarkContents) : false;
        if (!$destination || !$watermark) {
            if ($destination) {
                imagedestroy($destination);
            }
            if ($watermark) {
                imagedestroy($watermark);
            }
            throw new \RuntimeException('Impossibile leggere l’immagine o la filigrana.');
        }

        imagealphablending($destination, true);
        imagesavealpha($destination, true);

        $destinationWidth = imagesx($destination);
        $destinationHeight = imagesy($destination);
        $mode = (string) \Configuration::get('ABW_MODE');
        $scalePercent = max(10, min(60, (int) \Configuration::get('ABW_SCALE')));
        $smallDiagonal = $mode === 'diagonal' && min($destinationWidth, $destinationHeight) <= 320;
        if ($smallDiagonal) {
            $scalePercent = max(52, $scalePercent);
        }
        $scale = $scalePercent / 100;
        $targetWidth = max(1, (int) round($destinationWidth * $scale));
        $targetHeight = max(1, (int) round(imagesy($watermark) * $targetWidth / imagesx($watermark)));

        $resizedWatermark = $this->resizeWatermark($watermark, $targetWidth, $targetHeight);
        imagedestroy($watermark);

        if ($mode === 'diagonal') {
            $rotatedWatermark = $this->rotateWatermark($resizedWatermark, -22.0);
            imagedestroy($resizedWatermark);
            $resizedWatermark = $rotatedWatermark;
            $targetWidth = imagesx($resizedWatermark);
            $targetHeight = imagesy($resizedWatermark);
        }

        // Apply opacity only after rotation. GD creates nearly transparent
        // antialiasing pixels while rotating; reducing opacity beforehand can
        // make those pixels visible as a dark rectangle on JPEG derivatives.
        $opacity = max(5, min(80, (int) \Configuration::get('ABW_OPACITY'))) / 100;
        if ($smallDiagonal) {
            $opacity = max(0.32, $opacity);
        }
        $this->applyOpacity($resizedWatermark, $opacity);

        $placementMode = $smallDiagonal ? 'center' : $mode;
        foreach ($this->getPositions($placementMode, $destinationWidth, $destinationHeight, $targetWidth, $targetHeight) as $position) {
            imagecopy(
                $destination,
                $resizedWatermark,
                $position[0],
                $position[1],
                0,
                0,
                $targetWidth,
                $targetHeight
            );
        }
        imagedestroy($resizedWatermark);

        $mime = (string) (@getimagesize($path)['mime'] ?? 'image/jpeg');
        $this->saveAtomically($destination, $path, $mime);
        imagedestroy($destination);
    }

    private function resizeWatermark($source, int $width, int $height)
    {
        $destination = imagecreatetruecolor($width, $height);
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
        imagefill($destination, 0, 0, $transparent);
        imagecopyresampled(
            $destination,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height,
            imagesx($source),
            imagesy($source)
        );
        imagealphablending($destination, false);

        return $destination;
    }

    private function rotateWatermark($source, float $angle)
    {
        imagealphablending($source, false);
        imagesavealpha($source, true);
        $transparent = imagecolorallocatealpha($source, 255, 255, 255, 127);
        $rotated = imagerotate($source, $angle, $transparent);
        if (!$rotated) {
            throw new \RuntimeException('Rotazione della filigrana non riuscita.');
        }

        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        return $rotated;
    }

    private function applyOpacity($image, float $opacity): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                if ($alpha >= 127) {
                    continue;
                }
                $newAlpha = 127 - (int) round((127 - $alpha) * $opacity);
                $color = imagecolorallocatealpha(
                    $image,
                    ($rgba >> 16) & 0xFF,
                    ($rgba >> 8) & 0xFF,
                    $rgba & 0xFF,
                    max(0, min(127, $newAlpha))
                );
                imagesetpixel($image, $x, $y, $color);
            }
        }
    }

    private function getPositions(
        string $mode,
        int $width,
        int $height,
        int $watermarkWidth,
        int $watermarkHeight
    ): array {
        $marginRatio = max(0, min(15, (int) \Configuration::get('ABW_MARGIN'))) / 100;
        $marginX = (int) round($width * $marginRatio);
        $marginY = (int) round($height * $marginRatio);
        $maxX = max(0, $width - $watermarkWidth);
        $maxY = max(0, $height - $watermarkHeight);

        if ($mode === 'center') {
            return [[(int) round($maxX / 2), (int) round($maxY / 2)]];
        }
        if ($mode === 'diagonal') {
            return [
                [min($maxX, $marginX), min($maxY, (int) round($height * 0.16))],
                [(int) round($maxX / 2), (int) round($maxY / 2)],
                [max(0, $maxX - $marginX), max(0, $maxY - (int) round($height * 0.16))],
            ];
        }

        return [[max(0, $maxX - $marginX), max(0, $maxY - $marginY)]];
    }

    private function saveAtomically($resource, string $path, string $mime): void
    {
        $temporary = $path . '.abw-' . bin2hex(random_bytes(4)) . '.tmp';
        $saved = false;
        if ($mime === 'image/png') {
            $saved = imagepng($resource, $temporary, 6);
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            $saved = imagewebp($resource, $temporary, 90);
        } elseif ($mime === 'image/avif' && function_exists('imageavif')) {
            $saved = imageavif($resource, $temporary, 80);
        } else {
            $saved = imagejpeg($resource, $temporary, 90);
        }

        if (!$saved || !is_file($temporary)) {
            @unlink($temporary);
            throw new \RuntimeException('Scrittura del file non riuscita.');
        }

        if (!@rename($temporary, $path)) {
            if (!@copy($temporary, $path)) {
                @unlink($temporary);
                throw new \RuntimeException('Sostituzione del file non riuscita.');
            }
            @unlink($temporary);
        }
        @chmod($path, 0664);
    }
}
