<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use SR\SimpleProductLink\Setup\Patch\Data\AddSimpleProductGroupAttribute;
use Zend_Db;

class GroupValuesResolver
{
    private const BATCH_SIZE = 1000;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductResource $productResource,
        private readonly EavConfig $eavConfig,
    ) {}

    /**
     * @param mixed[] $productIds
     * @return string[]
     */
    public function getByProductIds(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$productIds) {
            return [];
        }

        $attribute = $this->eavConfig->getAttribute(
            Product::ENTITY,
            AddSimpleProductGroupAttribute::ATTRIBUTE_CODE
        );
        if (!$attribute || !$attribute->getAttributeId()) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $attributeTable = $attribute->getBackend()->getTable();
        $entityTable = $this->resourceConnection->getTableName('catalog_product_entity');
        $linkField = $this->productResource->getLinkField();
        $values = [];

        foreach (array_chunk($productIds, self::BATCH_SIZE) as $batchIds) {
            $select = $connection->select()
                ->from(['attribute_value' => $attributeTable], ['value'])
                ->joinInner(
                    ['product' => $entityTable],
                    sprintf('product.%1$s = attribute_value.%1$s', $linkField),
                    []
                )
                ->where('product.entity_id IN (?)', $batchIds, Zend_Db::INT_TYPE)
                ->where('attribute_value.attribute_id = ?', (int)$attribute->getAttributeId())
                ->where('attribute_value.store_id = ?', 0)
                ->where('attribute_value.value IS NOT NULL')
                ->where('attribute_value.value != ?', '');

            foreach ($connection->fetchCol($select) as $value) {
                $values[(string)$value] = true;
            }
        }

        return array_keys($values);
    }
}
