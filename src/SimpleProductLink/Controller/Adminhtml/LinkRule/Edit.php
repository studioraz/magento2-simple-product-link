<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Controller\Adminhtml\LinkRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use SR\SimpleProductLink\Model\LinkRuleFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'SR_SimpleProductLink::linkrule';

    private PageFactory $resultPageFactory;
    private LinkRuleFactory $linkRuleFactory;
    private Registry $registry;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        LinkRuleFactory $linkRuleFactory,
        Registry $registry
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->linkRuleFactory = $linkRuleFactory;
        $this->registry = $registry;
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
    {
        $ruleId = (int)$this->getRequest()->getParam('rule_id');
        $model = $this->linkRuleFactory->create();

        if ($ruleId) {
            $model->load($ruleId);
            if (!$model->getRuleId()) {
                $this->messageManager->addErrorMessage(__('This rule no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }

        $this->registry->register('current_link_rule', $model);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('SR_SimpleProductLink::linkrule');
        $resultPage->getConfig()->getTitle()->prepend(
            $ruleId ? __('Edit Rule: %1', $model->getName()) : __('New Link Rule')
        );

        return $resultPage;
    }
}
