<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Block\Adminhtml\Edit\Tab;

use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset;
use Magento\Framework\Registry;
use Magento\Framework\Data\FormFactory;
use Magento\Backend\Block\Template\Context;
use Magento\Rule\Block\Conditions as ConditionsBlock;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Ui\Component\Layout\Tabs\TabInterface;
use SR\SimpleProductLink\Model\LinkRule;
use SR\SimpleProductLink\Model\LinkRuleFactory;

class Conditions extends Generic implements TabInterface
{
    private Fieldset $rendererFieldset;
    private ConditionsBlock $conditions;
    private LinkRuleFactory $linkRuleFactory;

    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        ConditionsBlock $conditions,
        Fieldset $rendererFieldset,
        LinkRuleFactory $linkRuleFactory,
        array $data = []
    ) {
        $this->rendererFieldset = $rendererFieldset;
        $this->conditions = $conditions;
        $this->linkRuleFactory = $linkRuleFactory;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    public function getTabLabel(): \Magento\Framework\Phrase
    {
        return __('Conditions');
    }

    public function getTabTitle(): \Magento\Framework\Phrase
    {
        return __('Conditions');
    }

    public function canShowTab(): bool
    {
        return true;
    }

    public function isHidden(): bool
    {
        return false;
    }

    public function getTabClass(): ?string
    {
        return null;
    }

    public function getTabUrl(): ?string
    {
        return null;
    }

    public function isAjaxLoaded(): bool
    {
        return false;
    }

    protected function _prepareForm(): self
    {
        $model = $this->_coreRegistry->registry('current_link_rule');
        if (!$model) {
            $model = $this->linkRuleFactory->create();
        }

        $formName = 'sr_simpleproductlink_linkrule_form';
        $conditionsFieldSetId = $model->getConditionsFieldSetId($formName);

        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create();
        $form->setHtmlIdPrefix('rule_');

        $newChildUrl = $this->getUrl(
            'sr_simpleproductlink/linkrule/newConditionHtml/form/' . $conditionsFieldSetId,
            ['form_namespace' => $formName]
        );

        $renderer = $this->getLayout()->createBlock(Fieldset::class);
        $renderer->setTemplate('Magento_CatalogRule::promo/fieldset.phtml')
            ->setNewChildUrl($newChildUrl)
            ->setFieldSetId($conditionsFieldSetId);

        $fieldset = $form->addFieldset(
            'conditions_fieldset',
            ['legend' => __('Conditions (leave empty to apply to all products)')]
        )->setRenderer($renderer);

        $fieldset->addField(
            'conditions',
            'text',
            [
                'name' => 'conditions',
                'label' => __('Conditions'),
                'title' => __('Conditions'),
                'required' => true,
                'data-form-part' => $formName,
            ]
        )
            ->setRule($model)
            ->setRenderer($this->conditions);

        $form->setValues($model->getData());
        $this->setConditionFormName($model->getConditions(), $formName, $conditionsFieldSetId);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    private function setConditionFormName(
        AbstractCondition $conditions,
        string $formName,
        string $jsFormName
    ): void {
        $conditions->setFormName($formName);
        $conditions->setJsFormObject($jsFormName);

        if ($conditions->getConditions() && is_array($conditions->getConditions())) {
            foreach ($conditions->getConditions() as $condition) {
                $this->setConditionFormName($condition, $formName, $jsFormName);
            }
        }
    }
}
