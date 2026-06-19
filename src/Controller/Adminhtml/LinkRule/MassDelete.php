<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Controller\Adminhtml\LinkRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;
use SR\SimpleProductLink\Model\ResourceModel\LinkRule\CollectionFactory;
use SR\SimpleProductLink\Model\ResourceModel\LinkRule as LinkRuleResource;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'SR_SimpleProductLink::linkrule';

    private Filter $filter;
    private CollectionFactory $collectionFactory;
    private LinkRuleResource $linkRuleResource;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        LinkRuleResource $linkRuleResource
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->linkRuleResource = $linkRuleResource;
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $count = 0;

        foreach ($collection as $rule) {
            try {
                $this->linkRuleResource->delete($rule);
                $count++;
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        $this->messageManager->addSuccessMessage(
            __('A total of %1 record(s) have been deleted.', $count)
        );

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
