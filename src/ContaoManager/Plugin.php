<?php

declare(strict_types=1);

namespace Oneup\Contao\MailChimpBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Oneup\Contao\PageCacheWarmupBundle\OneupContaoPageCacheWarmupBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(OneupContaoPageCacheWarmupBundle::class)->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}
