<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Test\Unit\Block\Product\View;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\View\Element\Template\Context;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SR\SimpleProductLink\Block\Product\View\LinkedProducts;
use SR\SimpleProductLink\Model\Cache\GroupCacheTag;
use SR\SimpleProductLink\Setup\Patch\Data\AddSimpleProductGroupAttribute;

class LinkedProductsTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function supportedProductTypesProvider(): array
    {
        return [
            'simple' => ['simple'],
            'virtual' => ['virtual'],
            'downloadable' => ['downloadable'],
            'configurable' => ['configurable'],
            'grouped' => ['grouped'],
            'bundle' => ['bundle'],
        ];
    }

    #[DataProvider('supportedProductTypesProvider')]
    public function testGroupIdentityIsReturnedForEveryProductType(string $typeId): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getData')
            ->with(AddSimpleProductGroupAttribute::ATTRIBUTE_CODE)
            ->willReturn('group-1');

        $catalogHelper = $this->createMock(CatalogHelper::class);
        $catalogHelper->method('getProduct')->willReturn($product);

        $groupCacheTag = $this->createMock(GroupCacheTag::class);
        $groupCacheTag->method('isValidGroupValue')->with('group-1')->willReturn(true);
        $groupCacheTag->method('getTag')->with('group-1')->willReturn('sr_spl_group_1');

        $block = new LinkedProducts(
            $this->createMock(Context::class),
            $catalogHelper,
            $groupCacheTag,
        );

        $this->assertSame(['sr_spl_group_1'], $block->getIdentities());
    }
}
