<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Controller\Adminhtml\LinkRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use SR\SimpleProductLink\Model\LinkRuleFactory;
use SR\SimpleProductLink\Model\ResourceModel\LinkRule as LinkRuleResource;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'SR_SimpleProductLink::linkrule';

    private LinkRuleFactory $linkRuleFactory;
    private LinkRuleResource $linkRuleResource;

    public function __construct(
        Context $context,
        LinkRuleFactory $linkRuleFactory,
        LinkRuleResource $linkRuleResource
    ) {
        $this->linkRuleFactory = $linkRuleFactory;
        $this->linkRuleResource = $linkRuleResource;
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $ruleId = (int)$this->getRequest()->getParam('rule_id');

        if (!$ruleId) {
            $this->messageManager->addErrorMessage(__('We can\'t find a rule to delete.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $model = $this->linkRuleFactory->create();
            $this->linkRuleResource->load($model, $ruleId);
            $this->linkRuleResource->delete($model);
            $this->messageManager->addSuccessMessage(__('You deleted the rule.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
