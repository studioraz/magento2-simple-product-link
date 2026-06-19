<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Ui\Component\Listing\Column;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use SR\SimpleProductLink\Model\ResourceModel\LinkRule as LinkRuleResource;

class VariationAttributes extends Column
{
    private ResourceConnection $resourceConnection;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ResourceConnection $resourceConnection,
        array $components = [],
        array $data = []
    ) {
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $ruleIds = array_column($dataSource['data']['items'], 'rule_id');
        if (empty($ruleIds)) {
            return $dataSource;
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(LinkRuleResource::VARIATION_ATTRIBUTE_TABLE);
        $select = $connection->select()
            ->from($table, ['rule_id', 'attribute_code'])
            ->where('rule_id IN (?)', $ruleIds)
            ->order('sort_order ASC');

        $rows = $connection->fetchAll($select);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['rule_id']][] = $row['attribute_code'];
        }

        $fieldName = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $ruleId = (int)$item['rule_id'];
            $item[$fieldName] = implode(', ', $grouped[$ruleId] ?? []);
        }

        return $dataSource;
    }
}
