<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Model;

use Magento\Catalog\Model\Product;
use SR\SimpleProductLink\Model\ResourceModel\LinkRule\CollectionFactory as RuleCollectionFactory;
use SR\SimpleProductLink\Model\VirtualAttribute\Pool;

/**
 * Resolves the highest-priority active LinkRule that matches a given product.
 */
class LinkRuleMatcher
{
    public function __construct(
        private readonly RuleCollectionFactory $ruleCollectionFactory,
        private readonly Pool $virtualAttributePool,
    ) {}

    /**
     * Return the first matching active rule (ordered by priority DESC),
     * or null when no rule conditions are satisfied.
     *
     * A rule with no conditions configured acts as a catch-all fallback
     * only when all configured variation attributes are populated.
     */
    public function findForProduct(Product $product): ?LinkRule
    {
        $collection = $this->ruleCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('priority', 'DESC');

        foreach ($collection as $rule) {
            /** @var LinkRule $rule */
            $rule->afterLoad();

            if (!$this->hasRequiredVariationValues($rule, $product)) {
                continue;
            }

            $conditions = $rule->getConditions();
            if (!$conditions->getConditions()) {
                return $rule;
            }

            if ($conditions->validate($product)) {
                return $rule;
            }
        }

        return null;
    }

    private function hasRequiredVariationValues(LinkRule $rule, Product $product): bool
    {
        $variationAttributes = $rule->getVariationAttributeCodes();
        if (empty($variationAttributes)) {
            return false;
        }

        foreach ($variationAttributes as $attribute) {
            $attributeCode = is_array($attribute) ? (string)($attribute['attribute_code'] ?? '') : (string)$attribute;
            if ($attributeCode === '') {
                return false;
            }

            if ($this->virtualAttributePool->has($attributeCode)) {
                $value = $this->virtualAttributePool->getByCode($attributeCode)->getValueForProduct($product);
                if ($value === null || $value === '') {
                    return false;
                }
            } elseif ($this->isEmptySelectValue($product->getData($attributeCode))) {
                return false;
            }
        }

        return true;
    }

    private function isEmptySelectValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return empty($value);
    }
}
