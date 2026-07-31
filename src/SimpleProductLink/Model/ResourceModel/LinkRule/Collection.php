<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Model\ResourceModel\LinkRule;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use SR\SimpleProductLink\Api\Data\LinkRuleInterface;
use SR\SimpleProductLink\Model\LinkRule;
use SR\SimpleProductLink\Model\ResourceModel\LinkRule as LinkRuleResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'rule_id';

    protected function _construct(): void
    {
        $this->_init(LinkRule::class, LinkRuleResource::class);
    }

    protected function _afterLoad(): self
    {
        parent::_afterLoad();

        $ruleIds = $this->getColumnValues('rule_id');
        if (empty($ruleIds)) {
            return $this;
        }

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(
                $this->getTable(LinkRuleResource::VARIATION_ATTRIBUTE_TABLE),
                ['rule_id', 'attribute_code', 'sort_order']
            )
            ->where('rule_id IN (?)', $ruleIds)
            ->order('sort_order ASC');

        $rows = $connection->fetchAll($select);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['rule_id']][] = [
                'attribute_code' => $row['attribute_code'],
                'sort_order' => (int)$row['sort_order'],
            ];
        }

        foreach ($this->getItems() as $item) {
            $itemId = (int)$item->getId();
            $item->setData(
                LinkRuleInterface::VARIATION_ATTRIBUTE_CODES,
                $grouped[$itemId] ?? []
            );
        }

        return $this;
    }
}
