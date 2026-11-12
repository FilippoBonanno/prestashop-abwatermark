<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'abwatermark_log` (
    `id_abwatermark_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_image` INT UNSIGNED NOT NULL DEFAULT 0,
    `action` VARCHAR(32) NOT NULL,
    `status` VARCHAR(16) NOT NULL,
    `message` TEXT NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_abwatermark_log`),
    KEY `idx_abwatermark_image` (`id_image`),
    KEY `idx_abwatermark_date` (`date_add`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

return Db::getInstance()->execute($sql);
