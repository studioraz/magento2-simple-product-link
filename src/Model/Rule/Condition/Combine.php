<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Model\Rule\Condition;

class Combine extends \Magento\Rule\Model\Condition\Combine
{
    private ProductFactory $productFactory;

    public function __construct(
        \Magento\Rule\Model\Condition\Context $context,
        ProductFactory $conditionFactory,
        array $data = []
    ) {
        $this->productFactory = $conditionFactory;
        parent::__construct($context, $data);
        $this->setType(self::class);
    }

    public function getNewChildSelectOptions(): array
    {
        $productAttributes = $this->productFactory->create()
            ->loadAttributeOptions()
            ->getAttributeOption();

        $attributes = [];
        foreach ($productAttributes as $code => $label) {
            $attributes[] = [
                'value' => Product::class . '|' . $code,
                'label' => $label,
            ];
        }

        $conditions = parent::getNewChildSelectOptions();
        $conditions = array_merge_recursive(
            $conditions,
            [
                [
                    'value' => self::class,
                    'label' => __('Conditions Combination'),
                ],
                [
                    'label' => __('Product Attribute'),
                    'value' => $attributes,
                ],
            ]
        );

        return $conditions;
    }

    public function collectValidatedAttributes($productCollection): self
    {
        foreach ($this->getConditions() as $condition) {
            $condition->collectValidatedAttributes($productCollection);
        }
        return $this;
    }
}
