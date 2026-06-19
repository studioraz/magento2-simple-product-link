<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Plugin\Magento\CatalogInventory\Model\Indexer\Stock;

use Magento\CatalogInventory\Model\Indexer\Stock\CacheCleaner;
use SR\SimpleProductLink\Model\Cache\GroupCacheCleaner;
use SR\SimpleProductLink\Model\Product\GroupValuesResolver;

class CacheCleanerGroupCacheInvalidator
{
    public function __construct(
        private readonly GroupValuesResolver $groupValuesResolver,
        private readonly GroupCacheCleaner $groupCacheCleaner,
    ) {}

    public function afterClean(CacheCleaner $subject, $result, array $productIds, callable $reindex)
    {
        $this->groupCacheCleaner->cleanGroupValues(
            $this->groupValuesResolver->getByProductIds($productIds)
        );

        return $result;
    }
}
