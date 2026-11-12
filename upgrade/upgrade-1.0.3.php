<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_3($module)
{
    if (!$module instanceof AbWatermark) {
        return false;
    }

    $imageTypes = array_column(ImageType::getImagesTypes('products', true), 'name');
    if (!$imageTypes) {
        $imageTypes = [
            'small_default', 'default_xs', 'cart_default', 'default_s', 'default_m',
            'home_default', 'default_md', 'default_xl', 'medium_default',
            'product_main', 'large_default', 'product_main_2x',
        ];
    }

    return Configuration::updateValue('ABW_IMAGE_TYPES', json_encode(array_values($imageTypes)))
        && Configuration::updateValue('ABW_MIN_SIZE', 64)
        && Configuration::updateValue('ABW_CATALOG_DIRTY', 1)
        && Configuration::updateValue('ABW_BATCH_ERRORS', 0);
}
