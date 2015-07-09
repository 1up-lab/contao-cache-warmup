<?php

/**
 * Maintenance
 */
$arrBefore = array_slice($GLOBALS['TL_MAINTENANCE'], 0, 2, true);
$arrAfter = array_slice($GLOBALS['TL_MAINTENANCE'], 2, count($GLOBALS['TL_MAINTENANCE']) - 1, true);

$GLOBALS['TL_MAINTENANCE'] = array_merge($arrBefore, ['Oneup\Contao\CacheWarmup\CacheWarmup']);
$GLOBALS['TL_MAINTENANCE'] = array_merge($GLOBALS['TL_MAINTENANCE'], $arrAfter);

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['getCacheKey'][] = ['Oneup\Contao\CacheWarmup\CacheWarmup', 'getCacheKey'];
