<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Api\Data;

interface LinkRuleInterface
{
    public const RULE_ID = 'rule_id';
    public const NAME = 'name';
    public const DESCRIPTION = 'description';
    public const IS_ACTIVE = 'is_active';
    public const CONDITIONS_SERIALIZED = 'conditions_serialized';
    public const VARIATION_ATTRIBUTE_CODES = 'variation_attribute_codes';
    public const PRIORITY = 'priority';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public function getRuleId(): ?int;

    public function setRuleId(int $ruleId): self;

    public function getName(): ?string;

    public function setName(string $name): self;

    public function getDescription(): ?string;

    public function setDescription(?string $description): self;

    public function getIsActive(): bool;

    public function setIsActive(bool $isActive): self;

    /**
     * @return array [['attribute_code' => string, 'sort_order' => int], ...]
     */
    public function getVariationAttributeCodes(): array;

    /**
     * @param array $codes [['attribute_code' => string, 'sort_order' => int], ...]
     */
    public function setVariationAttributeCodes(array $codes): self;

    public function getPriority(): int;

    public function setPriority(int $priority): self;
}
