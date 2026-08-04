<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Test\Unit\Model\Product;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute as CatalogEavAttribute;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\Relation as ProductRelation;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;
use SR\SimpleProductLink\Model\Product\GroupValuesResolver;

class GroupValuesResolverTest extends TestCase
{
    public function testGroupValuesIncludeCompositeParentsAndRemainUnique(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinInner')->willReturnSelf();

        $queriedProductIds = null;
        $select->method('where')->willReturnCallback(
            function (string $condition, mixed $value = null) use ($select, &$queriedProductIds): Select {
                if ($condition === 'product.entity_id IN (?)') {
                    $queriedProductIds = $value;
                }

                return $select;
            }
        );

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturn([
            'child-group',
            'parent-group',
            'parent-group',
        ]);

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturn('catalog_product_entity');

        $productResource = $this->createMock(ProductResource::class);
        $productResource->method('getLinkField')->willReturn('entity_id');

        $backend = $this->createMock(AbstractBackend::class);
        $backend->method('getTable')->willReturn('catalog_product_entity_varchar');

        $attribute = $this->createMock(CatalogEavAttribute::class);
        $attribute->method('getAttributeId')->willReturn(157);
        $attribute->method('getBackend')->willReturn($backend);

        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getAttribute')->willReturn($attribute);

        $productRelation = $this->createMock(ProductRelation::class);
        $productRelation->expects($this->once())
            ->method('getRelationsByChildren')
            ->with([10])
            ->willReturn([10 => [20, 30]]);

        $resolver = new GroupValuesResolver(
            $resourceConnection,
            $productResource,
            $eavConfig,
            $productRelation,
        );

        $this->assertSame(
            ['child-group', 'parent-group'],
            $resolver->getByProductIds([10])
        );
        $this->assertSame([10, 20, 30], $queriedProductIds);
    }

    public function testEmptyProductIdsSkipRelationAndAttributeQueries(): void
    {
        $productRelation = $this->createMock(ProductRelation::class);
        $productRelation->expects($this->never())->method('getRelationsByChildren');

        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->expects($this->never())->method('getAttribute');

        $resolver = new GroupValuesResolver(
            $this->createMock(ResourceConnection::class),
            $this->createMock(ProductResource::class),
            $eavConfig,
            $productRelation,
        );

        $this->assertSame([], $resolver->getByProductIds([0, '']));
    }
}
