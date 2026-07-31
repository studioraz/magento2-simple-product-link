<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Model\ResourceModel\LinkRule\Grid;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\View\Element\UiComponent\DataProvider\Document;
use Psr\Log\LoggerInterface;
use SR\SimpleProductLink\Model\ResourceModel\LinkRule as LinkRuleResource;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
    implements SearchResultInterface
{
    protected $_idFieldName = 'rule_id';

    private AggregationInterface $aggregations;

    private string $mainTableName;
    private string $resourceModelName;

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        string $mainTable = 'studioraz_simpleproductlink_rule',
        string $resourceModel = LinkRuleResource::class,
        AdapterInterface $connection = null,
        AbstractDb $resource = null
    ) {
        $this->mainTableName = $mainTable;
        $this->resourceModelName = $resourceModel;
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
    }

    protected function _construct(): void
    {
        $this->_init(Document::class, $this->resourceModelName);
        $this->setMainTable($this->mainTableName);
    }

    public function getAggregations(): AggregationInterface
    {
        return $this->aggregations;
    }

    public function setAggregations($aggregations): self
    {
        $this->aggregations = $aggregations;
        return $this;
    }

    public function getSearchCriteria(): ?SearchCriteriaInterface
    {
        return null;
    }

    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria): self
    {
        return $this;
    }

    public function getTotalCount(): int
    {
        return $this->getSize();
    }

    public function setTotalCount($totalCount): self
    {
        return $this;
    }

    public function setItems(array $items = null): self
    {
        return $this;
    }
}
