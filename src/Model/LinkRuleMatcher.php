<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Model;

use Magento\Catalog\Model\Product;
use SR\SimpleProductLink\Model\ResourceModel\LinkRule\CollectionFactory as RuleCollectionFactory;

/**
 * Resolves the highest-priority active LinkRule that matches a given product.
 */
class LinkRuleMatcher
{
    public function __construct(
        private readonly RuleCollectionFactory $ruleCollectionFactory,
    ) {}

    /**
     * Return the first matching active rule (ordered by priority DESC),
     * or null when no rule conditions are satisfied.
     *
     * A rule with no conditions configured acts as a catch-all fallback.
     */
    public function findForProduct(Product $product): ?LinkRule
    {
        $collection = $this->ruleCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('priority', 'DESC');

        foreach ($collection as $rule) {
            /** @var LinkRule $rule */
            $rule->afterLoad();

            if (!$rule->getConditions()->getConditions()) {
                return $rule;
            }

            if ($rule->getConditions()->validate($product)) {
                return $rule;
            }
        }

        return null;
    }
}

