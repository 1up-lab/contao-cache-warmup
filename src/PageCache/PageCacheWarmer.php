<?php

declare(strict_types=1);

namespace Oneup\Contao\PageCacheWarmupBundle\PageCache;

use Symfony\Component\HttpFoundation\RequestStack;

class PageCacheWarmer
{
    /**
     * @var RequestStack
     */
    protected $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }
}
