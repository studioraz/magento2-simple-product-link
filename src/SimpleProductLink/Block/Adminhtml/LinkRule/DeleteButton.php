<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Block\Adminhtml\LinkRule;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        if (!$this->getRuleId()) {
            return [];
        }

        return [
            'label' => __('Delete Rule'),
            'class' => 'delete',
            'on_click' => 'deleteConfirm(\'' . __(
                'Are you sure you want to do this?'
            ) . '\', \'' . $this->getDeleteUrl() . '\', {data: {}})',
            'sort_order' => 20,
        ];
    }

    private function getDeleteUrl(): string
    {
        return $this->getUrl('*/*/delete', ['rule_id' => $this->getRuleId()]);
    }
}
