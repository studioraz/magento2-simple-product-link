<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use SR\SimpleProductLink\Api\Data\LinkRuleInterface;
use SR\SimpleProductLink\Api\LinkRuleRepositoryInterface;
use SR\SimpleProductLink\Model\ResourceModel\LinkRule as LinkRuleResource;

class LinkRuleRepository implements LinkRuleRepositoryInterface
{
    private LinkRuleResource $resource;
    private LinkRuleFactory $linkRuleFactory;

    public function __construct(
        LinkRuleResource $resource,
        LinkRuleFactory $linkRuleFactory
    ) {
        $this->resource = $resource;
        $this->linkRuleFactory = $linkRuleFactory;
    }

    public function getById(int $ruleId): LinkRuleInterface
    {
        $rule = $this->linkRuleFactory->create();
        $this->resource->load($rule, $ruleId);
        if (!$rule->getRuleId()) {
            throw new NoSuchEntityException(
                __('The rule with id "%1" does not exist.', $ruleId)
            );
        }
        return $rule;
    }

    public function save(LinkRuleInterface $rule): LinkRuleInterface
    {
        try {
            $this->resource->save($rule);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save the rule: %1', $e->getMessage()),
                $e
            );
        }
        return $rule;
    }

    public function delete(LinkRuleInterface $rule): bool
    {
        try {
            $this->resource->delete($rule);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete the rule: %1', $e->getMessage()),
                $e
            );
        }
        return true;
    }
}
