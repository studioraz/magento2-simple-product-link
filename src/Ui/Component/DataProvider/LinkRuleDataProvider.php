<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Ui\Component\DataProvider;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use SR\SimpleProductLink\Model\LinkRule;
use SR\SimpleProductLink\Model\ResourceModel\LinkRule\CollectionFactory;

class LinkRuleDataProvider extends AbstractDataProvider
{
    private ?array $loadedData = null;
    private DataPersistorInterface $dataPersistor;

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        $this->loadedData = [];
        $items = $this->collection->getItems();

        /** @var LinkRule $rule */
        foreach ($items as $rule) {
            $rule->load($rule->getId());
            $ruleData = $rule->getData();
            $ruleData['variation_attribute_codes'] = $rule->getVariationAttributeCodes();
            $this->loadedData[$rule->getId()] = $ruleData;
        }

        $data = $this->dataPersistor->get('studioraz_simpleproductlink_rule');
        if (!empty($data)) {
            $rule = $this->collection->getNewEmptyItem();
            $rule->setData($data);
            $this->loadedData[$rule->getId()] = $rule->getData();
            $this->dataPersistor->clear('studioraz_simpleproductlink_rule');
        }

        return $this->loadedData ?? [];
    }
}
