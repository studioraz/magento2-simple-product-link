<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Controller\Adminhtml\LinkRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use SR\SimpleProductLink\Model\LinkRuleFactory;
use SR\SimpleProductLink\Model\ResourceModel\LinkRule as LinkRuleResource;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'SR_SimpleProductLink::linkrule';

    private LinkRuleFactory $linkRuleFactory;
    private LinkRuleResource $linkRuleResource;
    private DataPersistorInterface $dataPersistor;

    public function __construct(
        Context $context,
        LinkRuleFactory $linkRuleFactory,
        LinkRuleResource $linkRuleResource,
        DataPersistorInterface $dataPersistor
    ) {
        $this->linkRuleFactory = $linkRuleFactory;
        $this->linkRuleResource = $linkRuleResource;
        $this->dataPersistor = $dataPersistor;
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $ruleId = (int)($data['rule_id'] ?? 0);
        $model = $this->linkRuleFactory->create();

        if ($ruleId) {
            $this->linkRuleResource->load($model, $ruleId);
            if (!$model->getRuleId()) {
                $this->messageManager->addErrorMessage(__('This rule no longer exists.'));
                return $resultRedirect->setPath('*/*/');
            }
        }

        unset($data['form_key']);
        if (empty($data['rule_id'])) {
            unset($data['rule_id']);
        }

        $variationAttributeCodes = $this->prepareVariationAttributeCodes($data);
        unset($data['variation_attribute_codes']);

        if (empty($variationAttributeCodes)) {
            $this->messageManager->addErrorMessage(__('Please add at least one variation attribute.'));
            $this->dataPersistor->set('studioraz_simpleproductlink_rule', $data);
            return $resultRedirect->setPath(
                '*/*/edit',
                ['rule_id' => $ruleId ?: null]
            );
        }

        $model->addData($data);
        $model->setVariationAttributeCodes($variationAttributeCodes);

        if (isset($data['rule']) && is_array($data['rule'])) {
            $model->loadPost($data['rule']);
        }

        try {
            $this->linkRuleResource->save($model);
            $this->messageManager->addSuccessMessage(__('You saved the rule.'));
            $this->dataPersistor->clear('studioraz_simpleproductlink_rule');

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['rule_id' => $model->getRuleId()]);
            }
            return $resultRedirect->setPath('*/*/');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->dataPersistor->set('studioraz_simpleproductlink_rule', $data);
            return $resultRedirect->setPath(
                '*/*/edit',
                ['rule_id' => $ruleId ?: null]
            );
        }
    }

    private function prepareVariationAttributeCodes(array $data): array
    {
        $rows = $data['variation_attribute_codes'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row) || empty($row['attribute_code'])) {
                continue;
            }
            $result[] = [
                'attribute_code' => $row['attribute_code'],
                'sort_order' => isset($row['sort_order']) ? (int)$row['sort_order'] : $index,
            ];
        }

        usort($result, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return $result;
    }
}
