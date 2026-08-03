<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Test\Unit\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;
use SR\SimpleProductLink\Model\Cache\GroupCacheCleaner;
use SR\SimpleProductLink\Model\Product\GroupValuesResolver;
use SR\SimpleProductLink\Observer\InvalidateCacheOnProductSave;
use SR\SimpleProductLink\Setup\Patch\Data\AddSimpleProductGroupAttribute;

class InvalidateCacheOnProductSaveTest extends TestCase
{
    public function testParentGroupCacheIsCleanedWhenChildHasNoGroupValue(): void
    {
        $attributeCode = AddSimpleProductGroupAttribute::ATTRIBUTE_CODE;
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(10);
        $product->method('getData')->with($attributeCode)->willReturn(null);
        $product->method('getOrigData')->with($attributeCode)->willReturn(null);

        $groupValuesResolver = $this->createMock(GroupValuesResolver::class);
        $groupValuesResolver->expects($this->once())
            ->method('getByProductIds')
            ->with([10])
            ->willReturn(['parent-group']);

        $groupCacheCleaner = $this->createMock(GroupCacheCleaner::class);
        $groupCacheCleaner->expects($this->once())
            ->method('cleanGroupValues')
            ->with([null, null, 'parent-group']);

        $observer = new InvalidateCacheOnProductSave(
            $groupCacheCleaner,
            $groupValuesResolver,
        );

        $observer->execute(new Observer([
            'event' => new Event(['product' => $product]),
        ]));
    }

    public function testProductWithoutIdOrGroupDoesNotCleanCache(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(null);
        $product->method('getData')->willReturn(null);
        $product->method('getOrigData')->willReturn(null);

        $groupValuesResolver = $this->createMock(GroupValuesResolver::class);
        $groupValuesResolver->expects($this->never())->method('getByProductIds');

        $groupCacheCleaner = $this->createMock(GroupCacheCleaner::class);
        $groupCacheCleaner->expects($this->never())->method('cleanGroupValues');

        $observer = new InvalidateCacheOnProductSave(
            $groupCacheCleaner,
            $groupValuesResolver,
        );

        $observer->execute(new Observer([
            'event' => new Event(['product' => $product]),
        ]));
    }
}
