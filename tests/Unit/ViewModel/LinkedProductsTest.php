<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Test\Unit\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as CatalogEavAttribute;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Swatches\Helper\Data as SwatchHelper;
use Magento\Swatches\Helper\Media as SwatchMediaHelper;
use Magento\Swatches\Model\Swatch;
use PHPUnit\Framework\Attributes\DataProvider;
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
    private function buildViewModel(
        Pool $pool,
        array $groupProducts,
        LinkRule $rule,
        ?EavConfig $eavConfig = null,
        bool $showOutOfStock = true,
    ): LinkedProducts {
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
        $collection->method('addAttributeToFilter')->willReturnCallback(
            function (string $attributeCode) use ($collection): ProductCollection {
                $this->assertNotSame('type_id', $attributeCode);
                return $collection;
            }
        );
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getItems')->willReturn($groupProducts);

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $matcherMock = $this->createMock(LinkRuleMatcher::class);
        $matcherMock->method('findForProduct')->willReturn($rule);

        $visibility = $this->createMock(Visibility::class);
        $visibility->method('getVisibleInCatalogIds')->willReturn([4]);

        $imageHelper = $this->createMock(ImageHelper::class);
        $imageHelper->method('init')->willReturnSelf();
        $imageHelper->method('getUrl')->willReturn('http://example.com/thumb.jpg');

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn($showOutOfStock);

        return new LinkedProducts(
            $collectionFactory,
            $matcherMock,
            $this->createMock(SwatchHelper::class),
            $eavConfig ?? $this->createMock(EavConfig::class),
            $this->createMock(SwatchMediaHelper::class),
            $scopeConfig,
            $imageHelper,
            $storeManager,
            $visibility,
            $pool,
        );
    }

    private function buildProduct(
        int $id,
        string $groupValue,
        ?string $virtualValue,
        string $typeId = 'simple',
        bool $isSalable = true,
    ): Product {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('isSalable')->willReturn($isSalable);
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

    /**
     * @param array<string, string> $values
     */
    private function buildMatrixProduct(
        int $id,
        string $groupValue,
        array $values,
        bool $isSalable = true,
        string $typeId = 'simple',
    ): Product {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('isSalable')->willReturn($isSalable);
        $product->method('getData')->willReturnCallback(
            static function (string $key) use ($groupValue, $values): mixed {
                if ($key === AddSimpleProductGroupAttribute::ATTRIBUTE_CODE) {
                    return $groupValue;
                }
                return $values[$key] ?? null;
            }
        );
        $product->method('getProductUrl')->willReturn("http://example.com/product-$id");
        return $product;
    }

    /**
     * @param string[] $attributeCodes
     */
    private function buildMatrixRule(array $attributeCodes): LinkRule
    {
        $rule = $this->createMock(LinkRule::class);
        $rule->method('getVariationAttributeCodes')->willReturn(array_map(
            static fn(string $attributeCode): array => ['attribute_code' => $attributeCode],
            $attributeCodes,
        ));
        return $rule;
    }

    /**
     * @param Product[] $products
     */
    private function buildHierarchyViewModel(array $products, bool $showOutOfStock = true): LinkedProducts
    {
        $weight = $this->createMock(VirtualAttributeInterface::class);
        $weight->method('getAttributeCode')->willReturn('weight');
        $weight->method('getFrontendLabel')->willReturn('Weight');
        $weight->method('getDependsOnAttributeCodes')->willReturn(['weight']);
        $weight->method('getValueForProduct')->willReturnCallback(
            static fn(Product $product): ?string => $product->getData('weight')
        );

        $flavorSource = $this->createMock(AbstractSource::class);
        $flavorSource->method('getAllOptions')->willReturn([
            ['value' => '10', 'label' => 'Mix'],
            ['value' => '20', 'label' => 'Chicken'],
            ['value' => '30', 'label' => 'Fish'],
            ['value' => '40', 'label' => 'Grill'],
        ]);

        $flavor = $this->createMock(CatalogEavAttribute::class);
        $flavor->method('getAttributeId')->willReturn(160);
        $flavor->method('getStoreLabel')->willReturn('Flavor');
        $flavor->method('getSource')->willReturn($flavorSource);

        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getAttribute')->willReturn($flavor);

        return $this->buildViewModel(
            new Pool([$weight]),
            $products,
            $this->buildMatrixRule(['weight', 'flavor']),
            $eavConfig,
            $showOutOfStock,
        );
    }

    /**
     * @param array<string, mixed> $group
     * @return array<string, array<string, mixed>>
     */
    private function indexOptionsByLabel(array $group): array
    {
        $indexed = [];
        foreach ($group['options'] as $option) {
            $indexed[$option['label']] = $option;
        }
        return $indexed;
    }

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
    public function testLinkedProductsSupportEveryProductType(string $typeId): void
    {
        $virtual = $this->createMock(VirtualAttributeInterface::class);
        $virtual->method('getAttributeCode')->willReturn('sr_virtual');
        $virtual->method('getFrontendLabel')->willReturn('Variant');
        $virtual->method('getDependsOnAttributeCodes')->willReturn([]);
        $virtual->method('getValueForProduct')->willReturnCallback(
            static fn(Product $product): string => sprintf('Option %d', (int)$product->getId())
        );

        $products = [
            $this->buildProduct(1, 'group1', 'Option 1', $typeId),
            $this->buildProduct(2, 'group1', 'Option 2', $typeId),
        ];

        $viewModel = $this->buildViewModel(new Pool([$virtual]), $products, $this->buildRule('sr_virtual'));
        $data = $viewModel->getLinkedProductData($products[0]);

        $this->assertNotNull($data);
        $this->assertCount(2, $data[0]['options']);
        $this->assertTrue($data[0]['options'][0]['is_salable']);
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

        // Virtual values render as textual swatches.
        foreach ($group['options'] as $option) {
            $this->assertSame(Swatch::SWATCH_TYPE_TEXTUAL, $option['swatch_type']);
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

    public function testHierarchyKeepsAllOptionsVisibleAndResolvesNearestLowerValues(): void
    {
        $products = [
            $this->buildMatrixProduct(1, 'group1', ['weight' => '1.5 kg', 'flavor' => '10']),
            $this->buildMatrixProduct(2, 'group1', ['weight' => '1.5 kg', 'flavor' => '20']),
            $this->buildMatrixProduct(3, 'group1', ['weight' => '2.85 kg', 'flavor' => '10']),
            $this->buildMatrixProduct(4, 'group1', ['weight' => '2.85 kg', 'flavor' => '20']),
            $this->buildMatrixProduct(5, 'group1', ['weight' => '2.85 kg', 'flavor' => '30']),
            $this->buildMatrixProduct(6, 'group1', ['weight' => '2.85 kg', 'flavor' => '40']),
            $this->buildMatrixProduct(7, 'group1', ['weight' => '15 kg', 'flavor' => '10']),
            // Reverse duplicate order to prove the final tie-break uses entity_id, not collection order.
            $this->buildMatrixProduct(9, 'group1', ['weight' => '15 kg', 'flavor' => '20']),
            $this->buildMatrixProduct(8, 'group1', ['weight' => '15 kg', 'flavor' => '20']),
        ];
        $viewModel = $this->buildHierarchyViewModel($products);

        $fishData = $viewModel->getLinkedProductData($products[4]);
        $chickenData = $viewModel->getLinkedProductData($products[1]);

        $this->assertNotNull($fishData);
        $this->assertNotNull($chickenData);
        $this->assertSame(
            ['1.5 kg', '2.85 kg', '15 kg'],
            array_column($fishData[0]['options'], 'label')
        );
        $this->assertSame(
            array_column($fishData[0]['options'], 'label'),
            array_column($chickenData[0]['options'], 'label')
        );
        $this->assertSame(
            ['Mix', 'Chicken', 'Fish', 'Grill'],
            array_column($fishData[1]['options'], 'label')
        );
        $this->assertSame(
            array_column($fishData[1]['options'], 'label'),
            array_column($chickenData[1]['options'], 'label')
        );

        $fishWeightOptions = $this->indexOptionsByLabel($fishData[0]);
        $this->assertSame('http://example.com/product-2', $fishWeightOptions['1.5 kg']['url']);
        $this->assertTrue($fishWeightOptions['1.5 kg']['is_available']);

        $chickenFlavorOptions = $this->indexOptionsByLabel($chickenData[1]);
        $this->assertTrue($chickenFlavorOptions['Mix']['is_available']);
        $this->assertTrue($chickenFlavorOptions['Chicken']['is_available']);
        $this->assertFalse($chickenFlavorOptions['Fish']['is_available']);
        $this->assertFalse($chickenFlavorOptions['Grill']['is_available']);
        $this->assertSame('', $chickenFlavorOptions['Fish']['url']);
        $this->assertTrue($chickenFlavorOptions['Fish']['is_salable']);

        $grillData = $viewModel->getLinkedProductData($products[5]);
        $this->assertNotNull($grillData);
        $grillWeightOptions = $this->indexOptionsByLabel($grillData[0]);
        $this->assertSame('http://example.com/product-8', $grillWeightOptions['15 kg']['url']);
    }

    public function testShowOutOfStockRemainsSeparateFromHierarchyAvailability(): void
    {
        $products = [
            $this->buildMatrixProduct(1, 'group1', ['weight' => '1.5 kg', 'flavor' => '10']),
            $this->buildMatrixProduct(2, 'group1', ['weight' => '1.5 kg', 'flavor' => '20']),
            $this->buildMatrixProduct(3, 'group1', ['weight' => '2.85 kg', 'flavor' => '30']),
            $this->buildMatrixProduct(4, 'group1', ['weight' => '20 kg', 'flavor' => '30'], false),
        ];

        $hiddenOutOfStockData = $this->buildHierarchyViewModel($products, false)
            ->getLinkedProductData($products[1]);
        $visibleOutOfStockData = $this->buildHierarchyViewModel($products, true)
            ->getLinkedProductData($products[1]);

        $this->assertNotNull($hiddenOutOfStockData);
        $this->assertNotNull($visibleOutOfStockData);
        $this->assertSame(
            ['1.5 kg', '2.85 kg'],
            array_column($hiddenOutOfStockData[0]['options'], 'label')
        );
        $this->assertSame(
            ['1.5 kg', '2.85 kg', '20 kg'],
            array_column($visibleOutOfStockData[0]['options'], 'label')
        );

        $visibleWeightOptions = $this->indexOptionsByLabel($visibleOutOfStockData[0]);
        $this->assertFalse($visibleWeightOptions['20 kg']['is_salable']);
        $this->assertFalse($visibleWeightOptions['20 kg']['is_available']);
        $this->assertSame('', $visibleWeightOptions['20 kg']['url']);

        $hiddenFlavorOptions = $this->indexOptionsByLabel($hiddenOutOfStockData[1]);
        $this->assertTrue($hiddenFlavorOptions['Fish']['is_salable']);
        $this->assertFalse($hiddenFlavorOptions['Fish']['is_available']);
    }
}
