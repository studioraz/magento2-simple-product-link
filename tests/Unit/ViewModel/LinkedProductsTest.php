<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Test\Unit\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Swatches\Helper\Data as SwatchHelper;
use Magento\Swatches\Helper\Media as SwatchMediaHelper;
use PHPUnit\Framework\TestCase;
use SR\SimpleProductLink\Api\VirtualAttributeInterface;
use SR\SimpleProductLink\Model\LinkRule;
use SR\SimpleProductLink\Model\LinkRuleMatcher;
use SR\SimpleProductLink\Model\VirtualAttribute\Pool;
use SR\SimpleProductLink\Setup\Patch\Data\AddSimpleProductGroupAttribute;
use SR\SimpleProductLink\ViewModel\LinkedProducts;

/**
 * Unit tests for the virtual attribute path in LinkedProducts.
 * We exercise getLinkedProductData() with a mocked environment.
 */
class LinkedProductsTest extends TestCase
{
    private function buildViewModel(Pool $pool, array $groupProducts, LinkRule $rule): LinkedProducts
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getWebsiteId')->willReturn(1);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $collection = $this->createMock(ProductCollection::class);
        $collection->method('setStoreId')->willReturnSelf();
        $collection->method('addStoreFilter')->willReturnSelf();
        $collection->method('addWebsiteFilter')->willReturnSelf();
        $collection->method('setVisibility')->willReturnSelf();
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getItems')->willReturn($groupProducts);

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $matcherMock = $this->createMock(LinkRuleMatcher::class);
        $matcherMock->method('findForProduct')->willReturn($rule);

        $stockRegistry = $this->createMock(StockRegistryInterface::class);
        $stockItem = $this->createMock(\Magento\CatalogInventory\Model\Stock\Item::class);
        $stockItem->method('getIsInStock')->willReturn(true);
        $stockRegistry->method('getStockItem')->willReturn($stockItem);

        $visibility = $this->createMock(Visibility::class);
        $visibility->method('getVisibleInCatalogIds')->willReturn([4]);

        $imageHelper = $this->createMock(ImageHelper::class);
        $imageHelper->method('init')->willReturnSelf();
        $imageHelper->method('getUrl')->willReturn('http://example.com/thumb.jpg');

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true); // show out of stock

        return new LinkedProducts(
            $collectionFactory,
            $matcherMock,
            $this->createMock(SwatchHelper::class),
            $stockRegistry,
            $this->createMock(EavConfig::class),
            $this->createMock(SwatchMediaHelper::class),
            $scopeConfig,
            $imageHelper,
            $storeManager,
            $visibility,
            $pool,
        );
    }

    private function buildProduct(int $id, string $groupValue, ?string $virtualValue): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getData')->willReturnCallback(function (string $key) use ($groupValue) {
            return $key === AddSimpleProductGroupAttribute::ATTRIBUTE_CODE ? $groupValue : null;
        });
        $product->method('getProductUrl')->willReturn("http://example.com/product-$id");
        return $product;
    }

    private function buildRule(string $attributeCode): LinkRule
    {
        $rule = $this->createMock(LinkRule::class);
        $rule->method('getVariationAttributeCodes')->willReturn([['attribute_code' => $attributeCode]]);
        return $rule;
    }

    public function testVirtualAttributeGroupBuiltCorrectly(): void
    {
        $virtual = $this->createMock(VirtualAttributeInterface::class);
        $virtual->method('getAttributeCode')->willReturn('sr_virtual');
        $virtual->method('getAdminLabel')->willReturn('Virtual');
        $virtual->method('getFrontendLabel')->willReturn('Weight');
        $virtual->method('getDependsOnAttributeCodes')->willReturn(['dep_a', 'dep_b']);

        $products = [
            $this->buildProduct(1, 'group1', '3 kg'),
            $this->buildProduct(2, 'group1', '5 kg'),
            $this->buildProduct(3, 'group1', '10 kg'),
        ];

        // Wire getValueForProduct to return values based on product ID
        $valueMap = [1 => '3 kg', 2 => '5 kg', 3 => '10 kg'];
        $virtual->method('getValueForProduct')->willReturnCallback(
            fn(Product $p) => $valueMap[(int)$p->getId()] ?? null
        );

        $pool = new Pool([$virtual]);
        $rule = $this->buildRule('sr_virtual');

        // current product is product 1
        $currentProduct = $products[0];

        $vm   = $this->buildViewModel($pool, $products, $rule);
        $data = $vm->getLinkedProductData($currentProduct);

        $this->assertNotNull($data);
        $this->assertCount(1, $data);

        $group = $data[0];
        $this->assertSame('Weight', $group['attribute_label']);
        $this->assertSame('sr_virtual', $group['attribute_code']);
        $this->assertCount(3, $group['options']);

        // Current product option is marked
        $currentOption = array_values(array_filter($group['options'], fn($o) => $o['is_current']))[0] ?? null;
        $this->assertNotNull($currentOption);
        $this->assertSame('3 kg', $currentOption['label']);

        // All options use SWATCH_TYPE_NONE
        foreach ($group['options'] as $option) {
            $this->assertSame(LinkedProducts::SWATCH_TYPE_NONE, $option['swatch_type']);
        }
    }

    public function testFewerThanTwoUniqueVirtualValuesReturnsNull(): void
    {
        $virtual = $this->createMock(VirtualAttributeInterface::class);
        $virtual->method('getAttributeCode')->willReturn('sr_virtual');
        $virtual->method('getAdminLabel')->willReturn('Virtual');
        $virtual->method('getFrontendLabel')->willReturn('Weight');
        $virtual->method('getDependsOnAttributeCodes')->willReturn([]);
        // All products return the same value → only 1 unique → group should be null
        $virtual->method('getValueForProduct')->willReturn('3 kg');

        $products = [
            $this->buildProduct(1, 'group1', '3 kg'),
            $this->buildProduct(2, 'group1', '3 kg'),
        ];

        $pool = new Pool([$virtual]);
        $rule = $this->buildRule('sr_virtual');

        $vm   = $this->buildViewModel($pool, $products, $rule);
        $data = $vm->getLinkedProductData($products[0]);

        $this->assertNull($data);
    }

    public function testNullVirtualValuesAreExcluded(): void
    {
        $virtual = $this->createMock(VirtualAttributeInterface::class);
        $virtual->method('getAttributeCode')->willReturn('sr_virtual');
        $virtual->method('getAdminLabel')->willReturn('Virtual');
        $virtual->method('getFrontendLabel')->willReturn('Weight');
        $virtual->method('getDependsOnAttributeCodes')->willReturn([]);

        $valueMap = [1 => '3 kg', 2 => null, 3 => '10 kg'];
        $virtual->method('getValueForProduct')->willReturnCallback(
            fn(Product $p) => $valueMap[(int)$p->getId()] ?? null
        );

        $products = [
            $this->buildProduct(1, 'group1', '3 kg'),
            $this->buildProduct(2, 'group1', null),
            $this->buildProduct(3, 'group1', '10 kg'),
        ];

        $pool = new Pool([$virtual]);
        $rule = $this->buildRule('sr_virtual');

        $vm   = $this->buildViewModel($pool, $products, $rule);
        $data = $vm->getLinkedProductData($products[0]);

        $this->assertNotNull($data);
        $labels = array_column($data[0]['options'], 'label');
        $this->assertNotContains(null, $labels);
        $this->assertCount(2, $data[0]['options']);
    }

    public function testOptionsAreSortedNaturally(): void
    {
        $virtual = $this->createMock(VirtualAttributeInterface::class);
        $virtual->method('getAttributeCode')->willReturn('sr_virtual');
        $virtual->method('getAdminLabel')->willReturn('Virtual');
        $virtual->method('getFrontendLabel')->willReturn('Weight');
        $virtual->method('getDependsOnAttributeCodes')->willReturn([]);

        $valueMap = [1 => '10 kg', 2 => '3 kg', 3 => '20 kg'];
        $virtual->method('getValueForProduct')->willReturnCallback(
            fn(Product $p) => $valueMap[(int)$p->getId()] ?? null
        );

        $products = [
            $this->buildProduct(1, 'group1', '10 kg'),
            $this->buildProduct(2, 'group1', '3 kg'),
            $this->buildProduct(3, 'group1', '20 kg'),
        ];

        $pool = new Pool([$virtual]);
        $rule = $this->buildRule('sr_virtual');

        $vm   = $this->buildViewModel($pool, $products, $rule);
        $data = $vm->getLinkedProductData($products[0]);

        $this->assertNotNull($data);
        $labels = array_column($data[0]['options'], 'label');
        $this->assertSame(['3 kg', '10 kg', '20 kg'], $labels);
    }
}
