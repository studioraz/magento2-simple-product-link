<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Model\VirtualAttribute;

use SR\SimpleProductLink\Api\VirtualAttributeInterface;

/**
 * Collects all registered VirtualAttributeInterface implementations.
 *
 * Register additional attributes via di.xml argument injection.
 * The pool is empty by default — if no third-party module registers anything,
 * SR_SimpleProductLink behaves exactly as before.
 */
class Pool
{
    /** @var VirtualAttributeInterface[] keyed by attribute code */
    private array $attributesByCode;

    /**
     * @param VirtualAttributeInterface[] $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributesByCode = [];
        foreach ($attributes as $attribute) {
            $this->attributesByCode[$attribute->getAttributeCode()] = $attribute;
        }
    }

    /**
     * @return VirtualAttributeInterface[]
     */
    public function getAll(): array
    {
        return array_values($this->attributesByCode);
    }

    public function getByCode(string $code): ?VirtualAttributeInterface
    {
        return $this->attributesByCode[$code] ?? null;
    }

    public function has(string $code): bool
    {
        return isset($this->attributesByCode[$code]);
    }
}
