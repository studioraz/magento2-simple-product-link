<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Plugin\Magento\Catalog\Model;

use Magento\Catalog\Model\Product\Action;
use SR\SimpleProductLink\Model\Cache\GroupCacheCleaner;
use SR\SimpleProductLink\Model\Product\GroupValuesResolver;

class ProductActionGroupCacheInvalidator
{
    /**
     * @var string[]
     */
    private array $groupValuesBeforeUpdate = [];

    public function __construct(
        private readonly GroupValuesResolver $groupValuesResolver,
        private readonly GroupCacheCleaner $groupCacheCleaner,
    ) {}

    public function beforeUpdateAttributes(Action $subject, $productIds, $attrData, $storeId): array
    {
        $this->groupValuesBeforeUpdate = $this->groupValuesResolver->getByProductIds((array)$productIds);

        return [$productIds, $attrData, $storeId];
    }

    public function afterUpdateAttributes(
        Action $subject,
        Action $result,
        $productIds,
        $attrData,
        $storeId
    ): Action {
        $groupValues = array_merge(
            $this->groupValuesBeforeUpdate,
            $this->groupValuesResolver->getByProductIds((array)$productIds)
        );

        $this->groupCacheCleaner->cleanGroupValues($groupValues);
        $this->groupValuesBeforeUpdate = [];

        return $result;
    }
}
