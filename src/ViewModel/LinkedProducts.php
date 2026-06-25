<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as CatalogEavAttribute;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
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
        private readonly StockRegistryInterface $stockRegistry,
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

        // Resolve once — used as a filter condition for every option in every attribute
        $showOutOfStock = $this->isShowOutOfStock();

        $result = [];
        foreach ($attributeCodes as $attributeCode) {
            $group = $this->buildAttributeGroup($attributeCode, $product, $linkedProducts, $showOutOfStock, $attributeCodes);
            if ($group !== null) {
                $result[] = $group;
            }
        }

        return $result ?: null;
    }

    /**
     * Build one attribute group (label + options array), or null if fewer than 2 options resolve.
     *
     * @param Product[] $linkedProducts
     * @param string[]  $allAttributeCodes All variation attribute codes for the rule (used to filter candidates)
     */
    private function buildAttributeGroup(
        string $attributeCode,
        Product $currentProduct,
        array $linkedProducts,
        bool $showOutOfStock,
        array $allAttributeCodes = []
    ): ?array {
        // Delegate virtual attributes to dedicated builder
        if ($this->virtualAttributePool->has($attributeCode)) {
            $otherAttributeCodes = array_values(array_filter($allAttributeCodes, fn(string $c) => $c !== $attributeCode));
            $candidates = $this->filterProductsByOtherAttributes($linkedProducts, $currentProduct, $otherAttributeCodes);
            return $this->buildVirtualAttributeGroup($attributeCode, $currentProduct, $candidates, $showOutOfStock);
        }

        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $attributeCode);
        if (!$attribute || !$attribute->getAttributeId()) {
            return null;
        }

        // For multi-attribute rules, only consider products that share the current product's
        // values for every OTHER variation attribute. This ensures, e.g., Storage options on a
        // White product page always link to White products — not to whichever color happens to
        // have the lowest entity_id.
        $otherAttributeCodes = array_values(array_filter($allAttributeCodes, fn(string $c) => $c !== $attributeCode));
        $candidates = $this->filterProductsByOtherAttributes($linkedProducts, $currentProduct, $otherAttributeCodes);

        // Single pass: collect option IDs and index products by option ID simultaneously
        $optionIds          = [];
        $productsByOptionId = [];
        foreach ($candidates as $linkedProduct) {
            $optionId = (int)$linkedProduct->getData($attributeCode);
            if (!$optionId || isset($productsByOptionId[$optionId])) {
                continue;
            }
            $optionIds[]                   = $optionId;
            $productsByOptionId[$optionId] = $linkedProduct;
        }

        $isSwatchAttribute  = $attribute instanceof CatalogEavAttribute
            && $this->swatchHelper->isSwatchAttribute($attribute);
        $swatchData         = $isSwatchAttribute
            ? $this->swatchHelper->getSwatchesByOptionsId($optionIds)
            : [];

        // Iterate attribute options in admin sort order
        $options = [];
        foreach ($this->getAttributeOptionLabels($attribute) as $optionId => $label) {
            if (!isset($productsByOptionId[$optionId])) {
                continue;
            }

            $option = $this->buildOption(
                $optionId,
                $label,
                $productsByOptionId[$optionId],
                $currentProduct,
                $isSwatchAttribute,
                $swatchData[$optionId] ?? null,
            );

            if (!$option['is_salable'] && !$option['is_current'] && !$showOutOfStock) {
                continue;
            }

            $options[] = $option;
        }

        if (count($options) < 2) {
            return null;
        }

        return [
            'attribute_label'    => $attribute->getStoreLabel() ?: $attribute->getFrontendLabel(),
            'attribute_code'     => $attributeCode,
            'current_product_id' => (int)$currentProduct->getId(),
            'options'            => $options,
        ];
    }

    /**
     * Build an attribute group for a virtual attribute using the pool's value builder.
     * Options are text-pill style (SWATCH_TYPE_NONE) with the computed string as label.
     *
     * @param Product[] $candidates Already filtered for other-axis alignment
     */
    private function buildVirtualAttributeGroup(
        string $attributeCode,
        Product $currentProduct,
        array $candidates,
        bool $showOutOfStock
    ): ?array {
        $virtualAttribute = $this->virtualAttributePool->getByCode($attributeCode);
        if ($virtualAttribute === null) {
            return null;
        }

        // Collect unique non-null values; index by value so we keep only the first product per value
        $productsByValue = [];
        foreach ($candidates as $candidate) {
            $value = $virtualAttribute->getValueForProduct($candidate);
            if ($value === null || $value === '') {
                continue;
            }
            if (!isset($productsByValue[$value])) {
                $productsByValue[$value] = $candidate;
            }
        }

        if (count($productsByValue) < 2) {
            return null;
        }

        // Natural-sort the computed values so "3 kg" < "10 kg" < "20 kg"
        uksort($productsByValue, 'strnatcasecmp');

        $options = [];
        foreach ($productsByValue as $value => $linkedProduct) {
            $option = $this->buildOption(
                0,    // sentinel — virtual options have no real EAV option ID
                $value,
                $linkedProduct,
                $currentProduct,
                true, // treat as textual swatch so the template renders a text pill, not a thumbnail
                null, // no swatch row → falls through to SWATCH_TYPE_TEXTUAL
            );

            if (!$option['is_salable'] && !$option['is_current'] && !$showOutOfStock) {
                continue;
            }

            $options[] = $option;
        }

        if (count($options) < 2) {
            return null;
        }

        return [
            'attribute_label'    => $virtualAttribute->getFrontendLabel($currentProduct),
            'attribute_code'     => $attributeCode,
            'current_product_id' => (int)$currentProduct->getId(),
            'options'            => $options,
        ];
    }

    /**
     * Build a single swatch option array for the given linked product.
     *
     * @param array<string,mixed>|null $swatch Raw swatch row from SwatchHelper, or null
     * @return array<string,mixed>
     */
    private function buildOption(
        int $optionId,
        string $label,
        Product $linkedProduct,
        Product $currentProduct,
        bool $isSwatchAttribute,
        ?array $swatch,
    ): array {
        $swatchType = match (true) {
            !$isSwatchAttribute => self::SWATCH_TYPE_NONE,
            $swatch !== null    => (int)$swatch['type'],
            default             => Swatch::SWATCH_TYPE_TEXTUAL,
        };

        return [
            'product_id'    => (int)$linkedProduct->getId(),
            'option_id'     => $optionId,
            'label'         => $label,
            'url'           => $linkedProduct->getProductUrl(),
            'is_current'    => (int)$linkedProduct->getId() === (int)$currentProduct->getId(),
            'is_salable'    => $this->isProductSalable($linkedProduct),
            'swatch_type'   => $swatchType,
            'swatch_value'  => $swatch['value'] ?? '',
            // Resolved only for dropdown (non-swatch) attributes; empty string otherwise
            'product_image' => !$isSwatchAttribute
                ? $this->imageHelper->init($linkedProduct, 'product_thumbnail_image')->getUrl()
                : '',
        ];
    }


    /**
     * Return only the products whose values for every code in $otherAttributeCodes
     * match the corresponding value on $currentProduct.
     *
     * When $otherAttributeCodes is empty (single-attribute rule) the full list is returned
     * unchanged, preserving backward compatibility.
     *
     * @param  Product[] $products
     * @param  string[]  $otherAttributeCodes
     * @return Product[]
     */
    private function filterProductsByOtherAttributes(
        array $products,
        Product $currentProduct,
        array $otherAttributeCodes
    ): array {
        if (empty($otherAttributeCodes)) {
            return $products;
        }

        return array_values(array_filter($products, function (Product $product) use ($currentProduct, $otherAttributeCodes): bool {
            foreach ($otherAttributeCodes as $code) {
                if ($this->virtualAttributePool->has($code)) {
                    $virtual = $this->virtualAttributePool->getByCode($code);
                    if ($virtual->getValueForProduct($product) !== $virtual->getValueForProduct($currentProduct)) {
                        return false;
                    }
                } elseif ($product->getData($code) !== $currentProduct->getData($code)) {
                    return false;
                }
            }
            return true;
        }));
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
            ->addAttributeToFilter('type_id', 'simple')
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
            return $this->stockRegistry->getStockItem($product->getId())->getIsInStock();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @return array<int, string> Option labels keyed by option_id, in admin sort order
     */
    private function getAttributeOptionLabels(AbstractAttribute $attribute): array
    {
        $labels = [];
        foreach ($attribute->getSource()->getAllOptions(false) as $option) {
            $labels[(int)$option['value']] = (string)$option['label'];
        }
        return $labels;
    }

    public function getSwatchMediaHelper(): SwatchMediaHelper
    {
        return $this->swatchMediaHelper;
    }
}
