<?php
/**
 * A.B. Tecnology - Filigrana immagini prodotto.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/src/Service/WatermarkProcessor.php';

use AbWatermark\Service\WatermarkProcessor;

class AbWatermark extends Module
{
    public const CONFIG_PREFIX = 'ABW_';
    public const AJAX_CONTROLLER = 'AdminAbWatermarkBatch';

    private const CONFIG_KEYS = [
        'ACTIVE',
        'MODE',
        'VARIANT',
        'OPACITY',
        'SCALE',
        'MARGIN',
        'MIN_SIZE',
        'IMAGE_TYPES',
        'EXCLUDED_PRODUCTS',
        'EXCLUDED_CATEGORIES',
        'BATCH_CURSOR',
        'BATCH_TOTAL',
        'BATCH_MODE',
        'BATCH_RUNNING',
        'BATCH_ERRORS',
        'CATALOG_DIRTY',
    ];

    public function __construct()
    {
        $this->name = 'abwatermark';
        $this->tab = 'administration';
        $this->version = '1.0.5';
        $this->author = 'A.B. Tecnology';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->trans('A.B. Filigrana prodotti', [], 'Modules.Abwatermark.Admin');
        $this->description = $this->trans(
            'Applica automaticamente una filigrana configurabile alle immagini prodotto, mantenendo intatti gli originali.',
            [],
            'Modules.Abwatermark.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Prima di disinstallare, rigenera le immagini pulite dal pannello del modulo. Continuare?',
            [],
            'Modules.Abwatermark.Admin'
        );
    }

    public function install()
    {
        if (!extension_loaded('gd')) {
            $this->_errors[] = $this->trans('L’estensione PHP GD è obbligatoria.', [], 'Modules.Abwatermark.Admin');

            return false;
        }

        if (!parent::install()) {
            return false;
        }

        try {
            $installed = $this->installDatabase()
                && $this->installConfiguration()
                && $this->installAjaxTab()
                && $this->registerHook('actionWatermark');
        } catch (Throwable $exception) {
            $installed = false;
            $this->_errors[] = $exception->getMessage();
        }

        if (!$installed) {
            $this->rollbackInstallation();
            $this->_errors[] = $this->trans(
                'Installazione annullata: tutte le modifiche parziali sono state rimosse.',
                [],
                'Modules.Abwatermark.Admin'
            );

            return false;
        }

        return true;
    }

    public function uninstall()
    {
        if ((bool) Configuration::get(self::CONFIG_PREFIX . 'CATALOG_DIRTY')) {
            $this->_errors[] = $this->trans(
                'Disinstallazione bloccata: prima esegui “Rigenera il catalogo senza filigrana” e verifica che termini senza errori.',
                [],
                'Modules.Abwatermark.Admin'
            );

            return false;
        }

        $this->removeAjaxTab();
        $this->removeCustomWatermark();

        foreach (self::CONFIG_KEYS as $key) {
            Configuration::deleteByName(self::CONFIG_PREFIX . $key);
        }

        return $this->uninstallDatabase() && parent::uninstall();
    }

    public function hookActionWatermark(array $params)
    {
        if (!(bool) Configuration::get(self::CONFIG_PREFIX . 'ACTIVE')) {
            return;
        }

        $imageId = isset($params['id_image']) ? (int) $params['id_image'] : 0;
        if ($imageId <= 0) {
            return;
        }

        $requestedTypes = null;
        if (!empty($params['image_type'])) {
            $rawTypes = is_array($params['image_type']) ? $params['image_type'] : [$params['image_type']];
            $requestedTypes = [];
            foreach ($rawTypes as $rawType) {
                if (is_array($rawType) && isset($rawType['name'])) {
                    $requestedTypes[] = (string) $rawType['name'];
                } elseif (is_string($rawType)) {
                    $requestedTypes[] = $rawType;
                }
            }
            $requestedTypes = array_values(array_unique($requestedTypes));
        }

        try {
            $result = $this->getProcessor()->processImage($imageId, $requestedTypes, false, true);
            $this->logResult($imageId, 'hook', $result);
            if (!empty($result['processed'])) {
                Configuration::updateValue(self::CONFIG_PREFIX . 'CATALOG_DIRTY', 1);
            }
        } catch (Throwable $exception) {
            $this->writeLog($imageId, 'hook', 'error', $exception->getMessage());
            PrestaShopLogger::addLog('[abwatermark] ' . $exception->getMessage(), 3, null, 'Image', $imageId, true);
        }
    }

    public function getContent()
    {
        $output = $this->postProcessConfiguration();

        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        $this->context->controller->addJS($this->_path . 'views/js/admin.js');

        $imageTypes = ImageType::getImagesTypes('products', true);
        $selectedTypes = $this->getSelectedImageTypes();
        foreach ($imageTypes as &$imageType) {
            $imageType['selected'] = in_array($imageType['name'], $selectedTypes, true);
        }
        unset($imageType);

        $this->context->smarty->assign([
            'abw_form_action' => Tools::safeOutput($_SERVER['REQUEST_URI']),
            'abw_active' => (int) Configuration::get(self::CONFIG_PREFIX . 'ACTIVE'),
            'abw_mode' => (string) Configuration::get(self::CONFIG_PREFIX . 'MODE'),
            'abw_variant' => (string) Configuration::get(self::CONFIG_PREFIX . 'VARIANT'),
            'abw_opacity' => (int) Configuration::get(self::CONFIG_PREFIX . 'OPACITY'),
            'abw_scale' => (int) Configuration::get(self::CONFIG_PREFIX . 'SCALE'),
            'abw_margin' => (int) Configuration::get(self::CONFIG_PREFIX . 'MARGIN'),
            'abw_min_size' => (int) Configuration::get(self::CONFIG_PREFIX . 'MIN_SIZE'),
            'abw_excluded_products' => (string) Configuration::get(self::CONFIG_PREFIX . 'EXCLUDED_PRODUCTS'),
            'abw_excluded_categories' => (string) Configuration::get(self::CONFIG_PREFIX . 'EXCLUDED_CATEGORIES'),
            'abw_image_types' => $imageTypes,
            'abw_watermark_url' => $this->getWatermarkUrl(),
            'abw_watermark_color_url' => $this->_path . 'views/img/watermark-color.png',
            'abw_watermark_white_url' => $this->_path . 'views/img/watermark-white.png',
            'abw_watermark_custom_url' => is_file($this->getCustomWatermarkPath())
                ? $this->_path . 'views/img/watermark-custom.png?v=' . filemtime($this->getCustomWatermarkPath())
                : '',
            'abw_preview_url' => $this->_path . 'views/img/preview-product.jpg',
            'abw_batch_endpoint' => $this->context->link->getAdminLink(self::AJAX_CONTROLLER),
            'abw_batch_status' => $this->getBatchStatus(),
            'abw_missing_count' => $this->countBatchRemaining('missing', 0),
            'abw_catalog_dirty' => (bool) Configuration::get(self::CONFIG_PREFIX . 'CATALOG_DIRTY'),
            'abw_has_custom_watermark' => is_file($this->getCustomWatermarkPath()),
            'abw_logs' => Db::getInstance()->executeS(
                'SELECT `id_image`, `action`, `status`, `message`, `date_add`'
                . ' FROM `' . _DB_PREFIX_ . 'abwatermark_log`'
                . ' ORDER BY `id_abwatermark_log` DESC LIMIT 20'
            ),
        ]);

        return $output . $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    public function getProcessor(): WatermarkProcessor
    {
        return new WatermarkProcessor($this->getWatermarkPath());
    }

    public function startBatch(string $mode): array
    {
        if (!in_array($mode, ['apply', 'missing', 'clean'], true)) {
            throw new InvalidArgumentException('Modalità di elaborazione non valida.');
        }

        $total = $this->countBatchRemaining($mode, 0);
        Configuration::updateValue(self::CONFIG_PREFIX . 'BATCH_CURSOR', 0);
        Configuration::updateValue(self::CONFIG_PREFIX . 'BATCH_TOTAL', $total);
        Configuration::updateValue(self::CONFIG_PREFIX . 'BATCH_MODE', $mode);
        Configuration::updateValue(self::CONFIG_PREFIX . 'BATCH_RUNNING', 1);
        Configuration::updateValue(self::CONFIG_PREFIX . 'BATCH_ERRORS', 0);
        if ($mode !== 'clean') {
            Configuration::updateValue(self::CONFIG_PREFIX . 'CATALOG_DIRTY', 1);
        }

        return $this->getBatchStatus();
    }

    public function processBatchStep(int $limit = 3): array
    {
        $limit = max(1, min(5, $limit));
        $cursor = (int) Configuration::get(self::CONFIG_PREFIX . 'BATCH_CURSOR');
        $mode = (string) Configuration::get(self::CONFIG_PREFIX . 'BATCH_MODE');
        $batchErrors = (int) Configuration::get(self::CONFIG_PREFIX . 'BATCH_ERRORS');

        if (!(bool) Configuration::get(self::CONFIG_PREFIX . 'BATCH_RUNNING')) {
            return $this->getBatchStatus();
        }

        $ids = Db::getInstance()->executeS(
            'SELECT i.`id_image` FROM `' . _DB_PREFIX_ . 'image` i'
            . ' WHERE i.`id_image` > ' . (int) $cursor
            . ' AND ' . $this->getBatchScope($mode)
            . ' ORDER BY i.`id_image` ASC LIMIT ' . (int) $limit
        );

        if (!$ids) {
            $this->finishBatch($mode, $batchErrors);

            return $this->getBatchStatus();
        }

        $processor = $this->getProcessor();
        foreach ($ids as $row) {
            $imageId = (int) $row['id_image'];
            try {
                $result = $processor->processImage($imageId, null, true, $mode !== 'clean');
                $this->logResult($imageId, $mode, $result);
                $batchErrors += count($result['errors'] ?? []);
            } catch (Throwable $exception) {
                $this->writeLog($imageId, $mode, 'error', $exception->getMessage());
                ++$batchErrors;
            }
            $cursor = $imageId;
        }

        Configuration::updateValue(self::CONFIG_PREFIX . 'BATCH_CURSOR', $cursor);
        Configuration::updateValue(self::CONFIG_PREFIX . 'BATCH_ERRORS', $batchErrors);
        $remaining = $this->countBatchRemaining($mode, $cursor);
        if ($remaining === 0) {
            $this->finishBatch($mode, $batchErrors);
        }

        return $this->getBatchStatus();
    }

    public function getBatchStatus(): array
    {
        $total = (int) Configuration::get(self::CONFIG_PREFIX . 'BATCH_TOTAL');
        $cursor = (int) Configuration::get(self::CONFIG_PREFIX . 'BATCH_CURSOR');
        $mode = (string) Configuration::get(self::CONFIG_PREFIX . 'BATCH_MODE');
        $running = (bool) Configuration::get(self::CONFIG_PREFIX . 'BATCH_RUNNING');
        $processed = 0;
        if ($total > 0) {
            // A completed batch stays at 100%: images uploaded after it ended
            // sit beyond the cursor and would otherwise drag the bar backwards.
            $processed = $running ? max(0, $total - $this->countBatchRemaining($mode, $cursor)) : $total;
        }

        return [
            'running' => $running,
            'mode' => $mode,
            'total' => $total,
            'processed' => min($processed, $total),
            'percent' => $total > 0 ? min(100, (int) round($processed * 100 / $total)) : 0,
            'errors' => (int) Configuration::get(self::CONFIG_PREFIX . 'BATCH_ERRORS'),
            'catalog_dirty' => (bool) Configuration::get(self::CONFIG_PREFIX . 'CATALOG_DIRTY'),
        ];
    }

    /**
     * Numero di immagini ancora da elaborare nella modalità indicata.
     */
    public function countBatchRemaining(string $mode, int $cursor): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'image` i'
            . ' WHERE i.`id_image` > ' . (int) $cursor
            . ' AND ' . $this->getBatchScope($mode)
        );
    }

    /**
     * Condizione SQL che restringe il lotto alle sole immagini interessate.
     */
    private function getBatchScope(string $mode): string
    {
        if ($mode !== 'missing') {
            return '1';
        }

        // Un'immagine è priva di filigrana quando nessuna elaborazione riuscita
        // è più recente dell'ultima pulizia. Le immagini caricate mentre il
        // modulo era inattivo non hanno alcuna riga di registro: l'hook esce
        // prima di scrivere, quindi restano scoperte e vengono intercettate qui.
        $log = '`' . _DB_PREFIX_ . 'abwatermark_log`';
        $epoch = '\'1000-01-01 00:00:00\'';

        return 'COALESCE((SELECT MAX(w.`date_add`) FROM ' . $log . ' w'
            . ' WHERE w.`id_image` = i.`id_image` AND w.`status` = \'ok\''
            . ' AND w.`action` IN (\'hook\', \'apply\', \'missing\')), ' . $epoch . ')'
            . ' <= COALESCE((SELECT MAX(c.`date_add`) FROM ' . $log . ' c'
            . ' WHERE c.`id_image` = i.`id_image` AND c.`action` = \'clean\'), ' . $epoch . ')';
    }

    private function postProcessConfiguration(): string
    {
        if (!Tools::isSubmit('submitAbwSettings')) {
            return '';
        }

        $errors = [];
        $mode = (string) Tools::getValue(self::CONFIG_PREFIX . 'MODE');
        $variant = (string) Tools::getValue(self::CONFIG_PREFIX . 'VARIANT');
        $opacity = (int) Tools::getValue(self::CONFIG_PREFIX . 'OPACITY');
        $scale = (int) Tools::getValue(self::CONFIG_PREFIX . 'SCALE');
        $margin = (int) Tools::getValue(self::CONFIG_PREFIX . 'MARGIN');
        $minSize = (int) Tools::getValue(self::CONFIG_PREFIX . 'MIN_SIZE');
        $excludedProducts = $this->sanitizeIdList((string) Tools::getValue(self::CONFIG_PREFIX . 'EXCLUDED_PRODUCTS'));
        $excludedCategories = $this->sanitizeIdList((string) Tools::getValue(self::CONFIG_PREFIX . 'EXCLUDED_CATEGORIES'));
        $imageTypes = Tools::getValue(self::CONFIG_PREFIX . 'IMAGE_TYPES', []);

        if (!in_array($mode, ['corner', 'center', 'diagonal'], true)) {
            $errors[] = 'Modalità non valida.';
        }
        if (!in_array($variant, ['color', 'white', 'custom'], true)) {
            $errors[] = 'Variante grafica non valida.';
        }
        if ($opacity < 5 || $opacity > 80) {
            $errors[] = 'L’opacità deve essere compresa tra 5 e 80.';
        }
        if ($scale < 10 || $scale > 60) {
            $errors[] = 'La dimensione deve essere compresa tra 10 e 60.';
        }
        if ($margin < 0 || $margin > 15) {
            $errors[] = 'Il margine deve essere compreso tra 0 e 15.';
        }
        if ($minSize < 64 || $minSize > 4000) {
            $errors[] = 'La dimensione minima deve essere compresa tra 64 e 4000 pixel.';
        }

        $availableTypes = array_column(ImageType::getImagesTypes('products', true), 'name');
        $imageTypes = array_values(array_intersect($availableTypes, is_array($imageTypes) ? $imageTypes : []));
        if (!$imageTypes) {
            $errors[] = 'Seleziona almeno un formato immagine.';
        }

        if (!empty($_FILES['ABW_WATERMARK_FILE']['tmp_name'])) {
            $uploadError = $this->saveUploadedWatermark($_FILES['ABW_WATERMARK_FILE']);
            if ($uploadError !== null) {
                $errors[] = $uploadError;
            } else {
                $variant = 'custom';
            }
        }

        if ($errors) {
            return $this->displayError(implode('<br>', array_map('Tools::safeOutput', $errors)));
        }

        Configuration::updateValue(self::CONFIG_PREFIX . 'ACTIVE', (int) Tools::getValue(self::CONFIG_PREFIX . 'ACTIVE'));
        Configuration::updateValue(self::CONFIG_PREFIX . 'MODE', $mode);
        Configuration::updateValue(self::CONFIG_PREFIX . 'VARIANT', $variant);
        Configuration::updateValue(self::CONFIG_PREFIX . 'OPACITY', $opacity);
        Configuration::updateValue(self::CONFIG_PREFIX . 'SCALE', $scale);
        Configuration::updateValue(self::CONFIG_PREFIX . 'MARGIN', $margin);
        Configuration::updateValue(self::CONFIG_PREFIX . 'MIN_SIZE', $minSize);
        Configuration::updateValue(self::CONFIG_PREFIX . 'IMAGE_TYPES', json_encode($imageTypes));
        Configuration::updateValue(self::CONFIG_PREFIX . 'EXCLUDED_PRODUCTS', $excludedProducts);
        Configuration::updateValue(self::CONFIG_PREFIX . 'EXCLUDED_CATEGORIES', $excludedCategories);

        return $this->displayConfirmation('Configurazione della filigrana salvata.');
    }

    private function saveUploadedWatermark(array $file): ?string
    {
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return 'Caricamento della filigrana non riuscito.';
        }
        if ((int) $file['size'] > 5 * 1024 * 1024) {
            return 'La filigrana non può superare 5 MB.';
        }

        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg'], true)) {
            return 'Carica una filigrana PNG o JPEG valida.';
        }

        $contents = @file_get_contents($file['tmp_name']);
        $resource = $contents !== false ? @imagecreatefromstring($contents) : false;
        if (!$resource) {
            return 'Il file caricato non è un’immagine valida.';
        }

        imagesavealpha($resource, true);
        $saved = imagepng($resource, $this->getCustomWatermarkPath(), 6);
        imagedestroy($resource);

        return $saved ? null : 'Impossibile salvare la filigrana nella cartella del modulo.';
    }

    private function installConfiguration(): bool
    {
        $defaultImageTypes = array_column(ImageType::getImagesTypes('products', true), 'name');
        if (!$defaultImageTypes) {
            $defaultImageTypes = [
                'small_default', 'default_xs', 'cart_default', 'default_s', 'default_m',
                'home_default', 'default_md', 'default_xl', 'medium_default',
                'product_main', 'large_default', 'product_main_2x',
            ];
        }

        $defaults = [
            'ACTIVE' => 0,
            'MODE' => 'diagonal',
            'VARIANT' => 'color',
            'OPACITY' => 18,
            'SCALE' => 30,
            'MARGIN' => 3,
            'MIN_SIZE' => 64,
            'IMAGE_TYPES' => json_encode(array_values($defaultImageTypes)),
            'EXCLUDED_PRODUCTS' => '',
            'EXCLUDED_CATEGORIES' => '',
            'BATCH_CURSOR' => 0,
            'BATCH_TOTAL' => 0,
            'BATCH_MODE' => '',
            'BATCH_RUNNING' => 0,
            'BATCH_ERRORS' => 0,
            'CATALOG_DIRTY' => 0,
        ];

        foreach ($defaults as $key => $value) {
            if (!Configuration::updateValue(self::CONFIG_PREFIX . $key, $value)) {
                return false;
            }
        }

        return true;
    }

    private function rollbackInstallation(): void
    {
        $this->removeAjaxTab();
        foreach (self::CONFIG_KEYS as $key) {
            Configuration::deleteByName(self::CONFIG_PREFIX . $key);
        }
        $this->uninstallDatabase();
        parent::uninstall();
    }

    private function finishBatch(string $mode, int $errors): void
    {
        Configuration::updateValue(self::CONFIG_PREFIX . 'BATCH_RUNNING', 0);
        if ($mode === 'clean' && $errors === 0) {
            Configuration::updateValue(self::CONFIG_PREFIX . 'CATALOG_DIRTY', 0);
        }
    }

    private function getSelectedImageTypes(): array
    {
        $decoded = json_decode((string) Configuration::get(self::CONFIG_PREFIX . 'IMAGE_TYPES'), true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function sanitizeIdList(string $value): string
    {
        $ids = preg_split('/[^0-9]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));

        return implode(',', $ids);
    }

    private function installAjaxTab(): bool
    {
        if (Tab::getIdFromClassName(self::AJAX_CONTROLLER)) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = self::AJAX_CONTROLLER;
        $tab->module = $this->name;
        $tab->id_parent = -1;
        $tab->active = 1;
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = 'A.B. Filigrana batch';
        }

        return (bool) $tab->save();
    }

    private function removeAjaxTab(): void
    {
        $tabId = (int) Tab::getIdFromClassName(self::AJAX_CONTROLLER);
        if ($tabId > 0) {
            (new Tab($tabId))->delete();
        }
    }

    private function installDatabase(): bool
    {
        return (bool) include __DIR__ . '/sql/install.php';
    }

    private function uninstallDatabase(): bool
    {
        return (bool) include __DIR__ . '/sql/uninstall.php';
    }

    private function logResult(int $imageId, string $action, array $result): void
    {
        $status = empty($result['errors']) ? 'ok' : 'partial';
        $message = sprintf(
            'Elaborati: %d; ignorati: %d; errori: %d',
            count($result['processed'] ?? []),
            count($result['skipped'] ?? []),
            count($result['errors'] ?? [])
        );
        if (!empty($result['errors'])) {
            $message .= ' - ' . implode('; ', $result['errors']);
        }
        $this->writeLog($imageId, $action, $status, $message);
    }

    private function writeLog(int $imageId, string $action, string $status, string $message): void
    {
        Db::getInstance()->insert('abwatermark_log', [
            'id_image' => $imageId,
            'action' => pSQL($action),
            'status' => pSQL($status),
            'message' => pSQL(Tools::substr($message, 0, 1000)),
            'date_add' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getWatermarkPath(): string
    {
        $variant = (string) Configuration::get(self::CONFIG_PREFIX . 'VARIANT');
        if ($variant === 'custom' && is_file($this->getCustomWatermarkPath())) {
            return $this->getCustomWatermarkPath();
        }

        return __DIR__ . '/views/img/watermark-' . ($variant === 'white' ? 'white' : 'color') . '.png';
    }

    private function getWatermarkUrl(): string
    {
        $variant = (string) Configuration::get(self::CONFIG_PREFIX . 'VARIANT');
        if ($variant === 'custom' && is_file($this->getCustomWatermarkPath())) {
            return $this->_path . 'views/img/watermark-custom.png?v=' . filemtime($this->getCustomWatermarkPath());
        }

        return $this->_path . 'views/img/watermark-' . ($variant === 'white' ? 'white' : 'color') . '.png';
    }

    private function getCustomWatermarkPath(): string
    {
        return __DIR__ . '/views/img/watermark-custom.png';
    }

    private function removeCustomWatermark(): void
    {
        $path = $this->getCustomWatermarkPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
