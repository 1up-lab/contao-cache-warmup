<?php

declare(strict_types=1);

namespace Oneup\Contao\PageCacheWarmupBundle\Module\Maintenance;

use Contao\Backend;
use Contao\Input;

class ModuleMaintenance extends Backend implements \executable
{
    public const ACTION_KEY = 'page_cache_warmup';

    public function __construct()
    {
        parent::__construct();

        // Add custom stylesheet
        if ('BE' === TL_MODE) {
            $GLOBALS['TL_CSS'][] = 'bundles/oneupcontaopagecachewarmupbundle/css/module.css';

            if ($this->isActive()) {
                $GLOBALS['TL_JAVASCRIPT'][] = 'assets/jquery/js/jquery.min.js|static';
                $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/oneupcontaopagecachewarmupbundle/js/jquery.ajaxQueue.js|static';
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isActive()
    {
        return (self::ACTION_KEY === Input::get('act'));
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {

    }
}
