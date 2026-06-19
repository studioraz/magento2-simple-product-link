<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Model\Source;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Data\OptionSourceInterface;

class VariationAttributes implements OptionSourceInterface
{
    private EavConfig $eavConfig;

    private const array ALLOWED_INPUT_TYPES = ['select'];

    public function __construct(EavConfig $eavConfig)
    {
        $this->eavConfig = $eavConfig;
    }

    public function toOptionArray(): array
    {
        $options = [];

        $attributes = $this->eavConfig->getEntityAttributes(Product::ENTITY);
        foreach ($attributes as $attribute) {
            if (!in_array($attribute->getFrontendInput(), self::ALLOWED_INPUT_TYPES, true)) {
                continue;
            }
            if (!$attribute->getAttributeCode() || !$attribute->getFrontendLabel()) {
                continue;
            }
            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => sprintf('%s (%s)', $attribute->getFrontendLabel(), $attribute->getAttributeCode()),
            ];
        }

        usort($options, fn($a, $b) => strcmp((string)$a['label'], (string)$b['label']));

        return $options;
    }
}
