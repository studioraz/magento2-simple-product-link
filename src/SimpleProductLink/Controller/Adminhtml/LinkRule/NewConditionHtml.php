<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Controller\Adminhtml\LinkRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\ConditionInterface;
use SR\SimpleProductLink\Model\LinkRule;
use SR\SimpleProductLink\Model\LinkRuleFactory;

class NewConditionHtml extends Action implements HttpPostActionInterface, HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'SR_SimpleProductLink::linkrule';

    private LinkRuleFactory $linkRuleFactory;

    public function __construct(
        Context $context,
        LinkRuleFactory $linkRuleFactory
    ) {
        $this->linkRuleFactory = $linkRuleFactory;
        parent::__construct($context);
    }

    public function execute(): void
    {
        $objectId = $this->getRequest()->getParam('id');
        $formNamespace = $this->getRequest()->getParam('form_namespace');
        $types = explode(
            '|',
            str_replace('-', '/', $this->getRequest()->getParam('type', ''))
        );
        $objectType = $types[0];
        $responseBody = '';

        if (class_exists($objectType) && !in_array(ConditionInterface::class, class_implements($objectType))) {
            $this->getResponse()->setBody($responseBody);
            return;
        }

        $conditionModel = $this->_objectManager->create($objectType)
            ->setId($objectId)
            ->setType($objectType)
            ->setRule($this->linkRuleFactory->create())
            ->setPrefix('conditions');

        if (!empty($types[1])) {
            $conditionModel->setAttribute($types[1]);
        }

        if ($conditionModel instanceof AbstractCondition) {
            $conditionModel->setJsFormObject($this->getRequest()->getParam('form'));
            $conditionModel->setFormName($formNamespace);
            $responseBody = $conditionModel->asHtmlRecursive();
        }

        $this->getResponse()->setBody($responseBody);
    }
}
