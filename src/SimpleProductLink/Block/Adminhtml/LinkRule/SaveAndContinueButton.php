<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Block\Adminhtml\LinkRule;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class SaveAndContinueButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        return [
            'label' => __('Save & Continue Edit'),
            'class' => 'save',
            'data_attribute' => [
                'mage-init' => [
                    'buttonAdapter' => [
                        'actions' => [
                            [
                                'targetName' => 'sr_simpleproductlink_linkrule_form.sr_simpleproductlink_linkrule_form',
                                'actionName' => 'save',
                                'params' => [true, ['back' => 'edit']],
                            ],
                        ],
                    ],
                ],
            ],
            'sort_order' => 80,
        ];
    }
}
