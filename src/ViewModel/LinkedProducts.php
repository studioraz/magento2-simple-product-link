<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as CatalogEavAttribute;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Swatches\Helper\Data as SwatchHelper;
use Magento\Swatches\Helper\Media as SwatchMediaHelper;
use Magento\Swatches\Model\Swatch;
use SR\SimpleProductLink\Model\LinkRuleMatcher;
use SR\SimpleProductLink\Model\VirtualAttribute\Pool;
use SR\SimpleProductLink\Setup\Patch\Data\AddSimpleProductGroupAttribute;

class LinkedProducts implements ArgumentInterface
{
    /** Sentinel for dropdown attributes that carry no swatch data */
    public const SWATCH_TYPE_NONE = -1;

    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly LinkRuleMatcher $linkRuleMatcher,
        private readonly SwatchHelper $swatchHelper,
        private readonly EavConfig $eavConfig,
        private readonly SwatchMediaHelper $swatchMediaHelper,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ImageHelper $imageHelper,
        private readonly StoreManagerInterface $storeManager,
        private readonly Visibility $productVisibility,
        private readonly Pool $virtualAttributePool,
    ) {}

    /**
     * @return array[]|null Array of attribute groups, each with attribute_label, attribute_code, current_product_id, options
     */
    public function getLinkedProductData(Product $product): ?array
    {
        $groupValue = $product->getData(AddSimpleProductGroupAttribute::ATTRIBUTE_CODE);
        if (empty($groupValue)) {
            return null;
        }

        $matchedRule = $this->linkRuleMatcher->findForProduct($product);
        if (!$matchedRule) {
            return null;
        }

        $variationAttributeCodes = $matchedRule->getVariationAttributeCodes();
        if (empty($variationAttributeCodes)) {
            return null;
        }

        $attributeCodes  = array_column($variationAttributeCodes, 'attribute_code');
        $linkedProducts  = $this->getGroupProducts($groupValue, $attributeCodes);

        if (count($linkedProducts) < 2) {
            return null;
        }

        $showOutOfStock = $this->isShowOutOfStock();
        $productValues = $this->buildProductValues($linkedProducts, $attributeCodes);
        $salability = [];
        foreach ($linkedProducts as $linkedProduct) {
            $salability[(int)$linkedProduct->getId()] = $this->isProductSalable($linkedProduct);
        }

        $axes = [];
        foreach ($attributeCodes as $attributeCode) {
            $axis = $this->buildAxis($attributeCode, $product, $linkedProducts, $productValues);
            if ($axis !== null) {
                $axes[] = $axis;
            }
        }

        $result = [];
        foreach (array_keys($axes) as $axisIndex) {
            $group = $this->buildAttributeGroup(
                $axisIndex,
                $axes,
                $product,
                $linkedProducts,
                $productValues,
                $salability,
                $showOutOfStock,
            );
            if ($group !== null) {
                $result[] = $group;
            }
        }

        return $result ?: null;
    }

    /**
     * @param array<int, array<string, mixed>> $axes
     * @param Product[] $linkedProducts
     * @param array<int, array<string, string|null>> $productValues
     * @param array<int, bool> $salability
     */
    private function buildAttributeGroup(
        int $axisIndex,
        array $axes,
        Product $currentProduct,
        array $linkedProducts,
        array $productValues,
        array $salability,
        bool $showOutOfStock
    ): ?array {
        $axis = $axes[$axisIndex];
        $attributeCode = $axis['attribute_code'];
        $currentProductId = (int)$currentProduct->getId();
        $currentValue = $productValues[$currentProductId][$attributeCode] ?? null;
        $options = [];
        foreach ($axis['values'] as $optionValue) {
            $productsWithValue = array_values(array_filter(
                $linkedProducts,
                static fn(Product $linkedProduct): bool =>
                    ($productValues[(int)$linkedProduct->getId()][$attributeCode] ?? null) === $optionValue
            ));
            $salableProductsWithValue = array_values(array_filter(
                $productsWithValue,
                static fn(Product $linkedProduct): bool => $salability[(int)$linkedProduct->getId()] ?? false
            ));

            $isCurrent = $currentValue === $optionValue;
            $isSalable = $salableProductsWithValue !== [];
            if (!$isSalable && !$isCurrent && !$showOutOfStock) {
                continue;
            }

            $availableCandidates = array_values(array_filter(
                $salableProductsWithValue,
                function (Product $candidate) use ($axisIndex, $axes, $currentProductId, $productValues): bool {
                    for ($prefixIndex = 0; $prefixIndex < $axisIndex; $prefixIndex++) {
                        $prefixCode = $axes[$prefixIndex]['attribute_code'];
                        if (($productValues[(int)$candidate->getId()][$prefixCode] ?? null)
                            !== ($productValues[$currentProductId][$prefixCode] ?? null)) {
                            return false;
                        }
                    }
                    return true;
                }
            ));
            $targetProduct = $this->resolveBestTarget(
                $availableCandidates,
                $axisIndex,
                $axes,
                $productValues,
                $currentProduct,
            );
            $representativeProduct = $isCurrent
                ? $currentProduct
                : ($targetProduct ?? $salableProductsWithValue[0] ?? $productsWithValue[0]);

            $options[] = $this->buildOption(
                $axis['is_virtual'] ? 0 : $optionValue,
                $axis['labels'][$optionValue],
                $representativeProduct,
                $targetProduct,
                $isCurrent,
                $isSalable,
                $targetProduct !== null,
                $axis['is_swatch'],
                $axis['swatch_data'][$optionValue] ?? null,
            );
        }

        if (count($options) < 2) {
            return null;
        }

        return [
            'attribute_label'    => $axis['attribute_label'],
            'attribute_code'     => $attributeCode,
            'current_product_id' => $currentProductId,
            'options'            => $options,
        ];
    }

    /**
     * @param Product[] $linkedProducts
     * @param array<int, array<string, string|null>> $productValues
     * @return array<string, mixed>|null
     */
    private function buildAxis(
        string $attributeCode,
        Product $currentProduct,
        array $linkedProducts,
        array $productValues
    ): ?array {
        $valuesInGroup = [];
        foreach ($linkedProducts as $linkedProduct) {
            $value = $productValues[(int)$linkedProduct->getId()][$attributeCode] ?? null;
            if ($value !== null) {
                $valuesInGroup[$value] = true;
            }
        }

        if ($this->virtualAttributePool->has($attributeCode)) {
            $virtualAttribute = $this->virtualAttributePool->getByCode($attributeCode);
            $values = array_keys($valuesInGroup);
            usort($values, 'strnatcasecmp');

            return [
                'attribute_code' => $attributeCode,
                'attribute_label' => $virtualAttribute->getFrontendLabel($currentProduct),
                'values' => $values,
                'labels' => array_combine($values, $values) ?: [],
                'is_virtual' => true,
                'is_swatch' => true,
                'swatch_data' => [],
            ];
        }

        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $attributeCode);
        if (!$attribute || !$attribute->getAttributeId()) {
            return null;
        }

        $values = [];
        $labels = [];
        foreach ($this->getAttributeOptionLabels($attribute) as $optionValue => $label) {
            $optionValue = (string)$optionValue;
            if (!isset($valuesInGroup[$optionValue])) {
                continue;
            }
            $values[] = $optionValue;
            $labels[$optionValue] = $label;
        }

        $isSwatchAttribute = $attribute instanceof CatalogEavAttribute
            && $this->swatchHelper->isSwatchAttribute($attribute);

        return [
            'attribute_code' => $attributeCode,
            'attribute_label' => $attribute->getStoreLabel() ?: $attribute->getFrontendLabel(),
            'values' => $values,
            'labels' => $labels,
            'is_virtual' => false,
            'is_swatch' => $isSwatchAttribute,
            'swatch_data' => $isSwatchAttribute
                ? $this->swatchHelper->getSwatchesByOptionsId(array_map('intval', $values))
                : [],
        ];
    }

    /**
     * @param Product[] $linkedProducts
     * @param string[] $attributeCodes
     * @return array<int, array<string, string|null>>
     */
    private function buildProductValues(array $linkedProducts, array $attributeCodes): array
    {
        $productValues = [];
        foreach ($linkedProducts as $linkedProduct) {
            $productId = (int)$linkedProduct->getId();
            foreach ($attributeCodes as $attributeCode) {
                if ($this->virtualAttributePool->has($attributeCode)) {
                    $rawValue = $this->virtualAttributePool
                        ->getByCode($attributeCode)
                        ->getValueForProduct($linkedProduct);
                } else {
                    $rawValue = $linkedProduct->getData($attributeCode);
                }

                $value = trim((string)$rawValue);
                $productValues[$productId][$attributeCode] = $value !== '' ? $value : null;
            }
        }
        return $productValues;
    }

    /**
     * @param Product[] $candidates
     * @param array<int, array<string, mixed>> $axes
     * @param array<int, array<string, string|null>> $productValues
     */
    private function resolveBestTarget(
        array $candidates,
        int $axisIndex,
        array $axes,
        array $productValues,
        Product $currentProduct
    ): ?Product {
        if ($candidates === []) {
            return null;
        }

        $currentProductId = (int)$currentProduct->getId();
        usort($candidates, function (Product $left, Product $right) use (
            $axisIndex,
            $axes,
            $productValues,
            $currentProductId
        ): int {
            for ($lowerIndex = $axisIndex + 1, $axisCount = count($axes);
                 $lowerIndex < $axisCount;
                 $lowerIndex++) {
                $axis = $axes[$lowerIndex];
                $attributeCode = $axis['attribute_code'];
                $currentValue = $productValues[$currentProductId][$attributeCode] ?? null;
                $leftDistance = $this->getOptionDistance(
                    $axis['values'],
                    $currentValue,
                    $productValues[(int)$left->getId()][$attributeCode] ?? null,
                );
                $rightDistance = $this->getOptionDistance(
                    $axis['values'],
                    $currentValue,
                    $productValues[(int)$right->getId()][$attributeCode] ?? null,
                );
                if ($leftDistance !== $rightDistance) {
                    return $leftDistance <=> $rightDistance;
                }
            }

            $leftIsCurrent = (int)$left->getId() === $currentProductId;
            $rightIsCurrent = (int)$right->getId() === $currentProductId;
            if ($leftIsCurrent !== $rightIsCurrent) {
                return $leftIsCurrent ? -1 : 1;
            }

            return (int)$left->getId() <=> (int)$right->getId();
        });

        return $candidates[0];
    }

    /**
     * @param string[] $orderedValues
     */
    private function getOptionDistance(array $orderedValues, ?string $currentValue, ?string $candidateValue): int
    {
        if ($currentValue === $candidateValue) {
            return 0;
        }

        $currentIndex = array_search($currentValue, $orderedValues, true);
        $candidateIndex = array_search($candidateValue, $orderedValues, true);
        if ($currentIndex === false || $candidateIndex === false) {
            return PHP_INT_MAX;
        }

        return abs($currentIndex - $candidateIndex);
    }

    /**
     * Build a single swatch option array for the given linked product.
     *
     * @param array<string,mixed>|null $swatch Raw swatch row from SwatchHelper, or null
     * @return array<string,mixed>
     */
    private function buildOption(
        int|string $optionValue,
        string $label,
        Product $representativeProduct,
        ?Product $targetProduct,
        bool $isCurrent,
        bool $isSalable,
        bool $isAvailable,
        bool $isSwatchAttribute,
        ?array $swatch,
    ): array {
        $swatchType = match (true) {
            !$isSwatchAttribute => self::SWATCH_TYPE_NONE,
            $swatch !== null    => (int)$swatch['type'],
            default             => Swatch::SWATCH_TYPE_TEXTUAL,
        };

        return [
            'product_id'    => (int)($targetProduct ?? $representativeProduct)->getId(),
            // Keep the existing array key for template compatibility; the value can be a string.
            'option_id'     => $optionValue,
            'label'         => $label,
            'url'           => $targetProduct?->getProductUrl() ?? '',
            'is_current'    => $isCurrent,
            'is_salable'    => $isSalable,
            'is_available'  => $isAvailable,
            'swatch_type'   => $swatchType,
            'swatch_value'  => $swatch['value'] ?? '',
            // Resolved only for dropdown (non-swatch) attributes; empty string otherwise
            'product_image' => !$isSwatchAttribute
                ? $this->imageHelper->init($representativeProduct, 'product_thumbnail_image')->getUrl()
                : '',
        ];
    }

    /**
     * Build the list of EAV attribute codes to load on the product collection.
     * Virtual attribute codes are replaced by their declared EAV dependencies.
     *
     * @param  string[] $variationAttributeCodes
     * @return string[]
     */
    private function resolveSelectCodes(array $variationAttributeCodes): array
    {
        $selectCodes = [];
        foreach ($variationAttributeCodes as $code) {
            if ($this->virtualAttributePool->has($code)) {
                foreach ($this->virtualAttributePool->getByCode($code)->getDependsOnAttributeCodes() as $dep) {
                    $selectCodes[] = $dep;
                }
            } else {
                $selectCodes[] = $code;
            }
        }
        return array_unique($selectCodes);
    }

    /**
     * @param  string[] $variationAttributeCodes
     * @return Product[]
     */
    private function getGroupProducts(string $groupValue, array $variationAttributeCodes): array
    {
        $store = $this->storeManager->getStore();
        $selectCodes = $this->resolveSelectCodes($variationAttributeCodes);

        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
        $collection = $this->productCollectionFactory->create();
        $collection
            ->setStoreId((int)$store->getId())
            ->addStoreFilter($store)
            ->addWebsiteFilter((int)$store->getWebsiteId())
            ->setVisibility($this->productVisibility->getVisibleInCatalogIds())
            ->addAttributeToSelect(array_merge(['name', 'url_key', 'thumbnail'], $selectCodes))
            ->addAttributeToFilter(AddSimpleProductGroupAttribute::ATTRIBUTE_CODE, $groupValue)
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->setOrder('entity_id', 'ASC');

        return $collection->getItems();
    }

    private function isShowOutOfStock(): bool
    {
        return $this->scopeConfig->isSetFlag(
            'cataloginventory/options/show_out_of_stock',
            ScopeInterface::SCOPE_STORE
        );
    }

    private function isProductSalable(Product $product): bool
    {
        try {
            return (bool)$product->isSalable();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @return array<int|string, string> Option labels keyed by option value, in admin sort order
     */
    private function getAttributeOptionLabels(AbstractAttribute $attribute): array
    {
        $labels = [];
        foreach ($attribute->getSource()->getAllOptions(false) as $option) {
            $optionValue = trim((string)($option['value'] ?? ''));
            if ($optionValue === '') {
                continue;
            }
            $labels[$optionValue] = (string)$option['label'];
        }
        return $labels;
    }

    public function getSwatchMediaHelper(): SwatchMediaHelper
    {
        return $this->swatchMediaHelper;
    }
}
