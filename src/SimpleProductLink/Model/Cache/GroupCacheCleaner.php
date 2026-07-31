<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Model\Cache;

use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Indexer\CacheContextFactory;

class GroupCacheCleaner
{
    public function __construct(
        private readonly GroupCacheTag $groupCacheTag,
        private readonly CacheContextFactory $cacheContextFactory,
        private readonly EventManagerInterface $eventManager,
    ) {}

    /**
     * @param mixed[] $groupValues
     */
    public function cleanGroupValues(array $groupValues): void
    {
        $tags = $this->groupCacheTag->getTags($groupValues);
        if (!$tags) {
            return;
        }

        $cacheContext = $this->cacheContextFactory->create();
        $cacheContext->registerTags($tags);
        $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $cacheContext]);
    }
}
