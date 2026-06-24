<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Model;

use Magento\Framework\Data\FormFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Rule\Model\AbstractModel;
use SR\SimpleProductLink\Api\Data\LinkRuleInterface;
use Magento\Rule\Model\Action\CollectionFactory as ActionCollectionFactory;
use SR\SimpleProductLink\Model\Rule\Condition\CombineFactory;

class LinkRule extends AbstractModel implements LinkRuleInterface
{
    protected $_eventPrefix = 'studioraz_simpleproductlink_rule';
    protected $_eventObject = 'rule';

    private CombineFactory $combineFactory;
    private ActionCollectionFactory $actionCollectionFactory;

    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        TimezoneInterface $localeDate,
        CombineFactory $combineFactory,
        ActionCollectionFactory $actionCollectionFactory,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->combineFactory = $combineFactory;
        $this->actionCollectionFactory = $actionCollectionFactory;
        parent::__construct($context, $registry, $formFactory, $localeDate, $resource, $resourceCollection, $data);
    }

    protected function _construct(): void
    {
        $this->_init(ResourceModel\LinkRule::class);
    }

    public function getConditionsInstance(): \Magento\Rule\Model\Condition\Combine
    {
        return $this->combineFactory->create();
    }

    public function getActionsInstance(): \Magento\Rule\Model\Action\Collection
    {
        return $this->actionCollectionFactory->create();
    }

    public function getRuleId(): ?int
    {
        $id = $this->getData(self::RULE_ID);
        return $id !== null ? (int)$id : null;
    }

    public function setRuleId(int $ruleId): LinkRuleInterface
    {
        return $this->setData(self::RULE_ID, $ruleId);
    }

    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    public function setName(string $name): LinkRuleInterface
    {
        return $this->setData(self::NAME, $name);
    }

    public function getDescription(): ?string
    {
        return $this->getData(self::DESCRIPTION);
    }

    public function setDescription(?string $description): LinkRuleInterface
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    public function getIsActive(): bool
    {
        return (bool)$this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): LinkRuleInterface
    {
        return $this->setData(self::IS_ACTIVE, $isActive);
    }

    public function getVariationAttributeCodes(): array
    {
        return $this->getData(self::VARIATION_ATTRIBUTE_CODES) ?? [];
    }

    public function setVariationAttributeCodes(array $codes): LinkRuleInterface
    {
        return $this->setData(self::VARIATION_ATTRIBUTE_CODES, $codes);
    }

    public function getPriority(): int
    {
        return (int)$this->getData(self::PRIORITY);
    }

    public function setPriority(int $priority): LinkRuleInterface
    {
        return $this->setData(self::PRIORITY, $priority);
    }

    public function getConditionsFieldSetId(string $formName = ''): string
    {
        return $formName . 'rule_conditions_fieldset_' . $this->getId();
    }
}
