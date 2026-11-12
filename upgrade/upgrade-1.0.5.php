<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_5($module): bool
{
    // La modalità "solo mancanti" legge il registro esistente: nessuna
    // modifica allo schema o alla configurazione è necessaria.
    return $module instanceof AbWatermark;
}
