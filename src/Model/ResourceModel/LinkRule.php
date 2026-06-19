<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use SR\SimpleProductLink\Api\Data\LinkRuleInterface;

class LinkRule extends AbstractDb
{
    public const VARIATION_ATTRIBUTE_TABLE = 'studioraz_simpleproductlink_rule_variation_attribute';

    protected function _construct(): void
    {
        $this->_init('studioraz_simpleproductlink_rule', 'rule_id');
    }

    protected function _afterLoad(AbstractModel $object): self
    {
        parent::_afterLoad($object);

        if ($object->getId()) {
            $connection = $this->getConnection();
            $select = $connection->select()
                ->from($this->getTable(self::VARIATION_ATTRIBUTE_TABLE), ['attribute_code', 'sort_order'])
                ->where('rule_id = ?', (int)$object->getId())
                ->order('sort_order ASC');

            $rows = $connection->fetchAll($select);
            $object->setData(LinkRuleInterface::VARIATION_ATTRIBUTE_CODES, $rows);
        }

        return $this;
    }

    protected function _afterSave(AbstractModel $object): self
    {
        parent::_afterSave($object);

        $connection = $this->getConnection();
        $table = $this->getTable(self::VARIATION_ATTRIBUTE_TABLE);
        $ruleId = (int)$object->getId();

        $connection->delete($table, ['rule_id = ?' => $ruleId]);

        $codes = $object->getData(LinkRuleInterface::VARIATION_ATTRIBUTE_CODES);
        if (is_array($codes) && !empty($codes)) {
            $insertData = [];
            foreach ($codes as $sortOrder => $row) {
                $attributeCode = is_array($row) ? ($row['attribute_code'] ?? '') : (string)$row;
                if (empty($attributeCode)) {
                    continue;
                }
                $insertData[] = [
                    'rule_id' => $ruleId,
                    'attribute_code' => $attributeCode,
                    'sort_order' => is_array($row) ? (int)($row['sort_order'] ?? $sortOrder) : $sortOrder,
                ];
            }
            if (!empty($insertData)) {
                $connection->insertMultiple($table, $insertData);
            }
        }

        return $this;
    }
}
