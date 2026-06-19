<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Model\Rule\Condition;

class Product extends \Magento\Rule\Model\Condition\Product\AbstractProduct
{
    public function validate(\Magento\Framework\Model\AbstractModel $model): bool
    {
        $attrCode = $this->getAttribute();

        if ($attrCode === 'category_ids') {
            return parent::validate($model);
        }

        $oldAttrValue = $model->getData($attrCode);
        if ($oldAttrValue === null) {
            return $this->getOperator() === '<=>';
        }

        $this->_setAttributeValue($model);
        $result = $this->validateAttribute($model->getData($attrCode));
        $this->_restoreOldAttrValue($model, $oldAttrValue);

        return (bool)$result;
    }

    private function _setAttributeValue(\Magento\Framework\Model\AbstractModel $model): void
    {
        $storeId = $model->getStoreId();
        $defaultStoreId = \Magento\Store\Model\Store::DEFAULT_STORE_ID;

        if (!isset($this->_entityAttributeValues[$model->getId()])) {
            return;
        }

        $productValues = $this->_entityAttributeValues[$model->getId()];
        if (!isset($productValues[$storeId]) && !isset($productValues[$defaultStoreId])) {
            return;
        }

        $value = $productValues[$storeId] ?? $productValues[$defaultStoreId];
        $model->setData($this->getAttribute(), $value);
    }

    private function _restoreOldAttrValue(
        \Magento\Framework\Model\AbstractModel $model,
        $oldAttrValue
    ): void {
        if ($oldAttrValue === null) {
            $model->unsetData($this->getAttribute());
        } else {
            $model->setData($this->getAttribute(), $oldAttrValue);
        }
    }
}
