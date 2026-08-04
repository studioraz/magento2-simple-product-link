<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Block\Product\View;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use SR\SimpleProductLink\Model\Cache\GroupCacheTag;
use SR\SimpleProductLink\Setup\Patch\Data\AddSimpleProductGroupAttribute;

class LinkedProducts extends Template implements IdentityInterface
{
    public function __construct(
        Context $context,
        private readonly CatalogHelper $catalogHelper,
        private readonly GroupCacheTag $groupCacheTag,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getProduct(): ?Product
    {
        return $this->catalogHelper->getProduct();
    }

    public function getIdentities(): array
    {
        $product = $this->getProduct();
        if (!$product) {
            return [];
        }

        $groupValue = $product->getData(AddSimpleProductGroupAttribute::ATTRIBUTE_CODE);
        if (!$this->groupCacheTag->isValidGroupValue($groupValue)) {
            return [];
        }

        return [$this->groupCacheTag->getTag((string)$groupValue)];
    }
}
