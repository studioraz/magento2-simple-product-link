<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Controller\Adminhtml\LinkRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'SR_SimpleProductLink::linkrule';

    private PageFactory $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\View\Result\Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('SR_SimpleProductLink::linkrule');
        $resultPage->getConfig()->getTitle()->prepend(__('Simple Product Link Rules'));
        return $resultPage;
    }
}
