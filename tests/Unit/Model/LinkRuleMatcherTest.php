<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Test\Unit\Model;

use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;
use SR\SimpleProductLink\Api\VirtualAttributeInterface;
use SR\SimpleProductLink\Model\LinkRule;
use SR\SimpleProductLink\Model\LinkRuleMatcher;
use SR\SimpleProductLink\Model\ResourceModel\LinkRule\Collection as RuleCollection;
use SR\SimpleProductLink\Model\ResourceModel\LinkRule\CollectionFactory;
use SR\SimpleProductLink\Model\VirtualAttribute\Pool;

class LinkRuleMatcherTest extends TestCase
{
    private function buildMatcher(Pool $pool, array $rules = []): LinkRuleMatcher
    {
        $collection = $this->createMock(RuleCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($rules));

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new LinkRuleMatcher($factory, $pool);
    }

    private function buildRule(array $variationCodes, bool $conditionsMatch = true): LinkRule
    {
        $rule = $this->createMock(LinkRule::class);
        $rule->method('afterLoad')->willReturnSelf();
        $rule->method('getVariationAttributeCodes')->willReturn(
            array_map(fn(string $c) => ['attribute_code' => $c], $variationCodes)
        );

        $conditions = $this->createMock(\Magento\Rule\Model\Condition\Combine::class);
        $conditions->method('getConditions')->willReturn([]);
        $rule->method('getConditions')->willReturn($conditions);

        return $rule;
    }

    private function buildProduct(array $data = []): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getData')->willReturnCallback(fn(string $key) => $data[$key] ?? null);
        return $product;
    }

    public function testVirtualAttributeWithNullValueExcludesRule(): void
    {
        $virtual = $this->createMock(VirtualAttributeInterface::class);
        $virtual->method('getAttributeCode')->willReturn('sr_virtual');
        $virtual->method('getValueForProduct')->willReturn(null);
        $pool = new Pool([$virtual]);

        $rule    = $this->buildRule(['sr_virtual']);
        $matcher = $this->buildMatcher($pool, [$rule]);

        $this->assertNull($matcher->findForProduct($this->buildProduct()));
    }

    public function testVirtualAttributeWithEmptyStringExcludesRule(): void
    {
        $virtual = $this->createMock(VirtualAttributeInterface::class);
        $virtual->method('getAttributeCode')->willReturn('sr_virtual');
        $virtual->method('getValueForProduct')->willReturn('');
        $pool = new Pool([$virtual]);

        $rule    = $this->buildRule(['sr_virtual']);
        $matcher = $this->buildMatcher($pool, [$rule]);

        $this->assertNull($matcher->findForProduct($this->buildProduct()));
    }

    public function testVirtualAttributeWithNonNullValueMatchesRule(): void
    {
        $virtual = $this->createMock(VirtualAttributeInterface::class);
        $virtual->method('getAttributeCode')->willReturn('sr_virtual');
        $virtual->method('getValueForProduct')->willReturn('3 kg');
        $pool = new Pool([$virtual]);

        $rule    = $this->buildRule(['sr_virtual']);
        $matcher = $this->buildMatcher($pool, [$rule]);

        $result = $matcher->findForProduct($this->buildProduct());
        $this->assertSame($rule, $result);
    }

    public function testRealEavAttributeStillWorksWithEmptyPool(): void
    {
        $pool    = new Pool();
        $rule    = $this->buildRule(['color']);
        $matcher = $this->buildMatcher($pool, [$rule]);

        // Product has a non-empty 'color' value → rule should match
        $product = $this->buildProduct(['color' => '42']);
        $this->assertSame($rule, $matcher->findForProduct($product));
    }

    public function testRealEavAttributeWithNullValueExcludesRule(): void
    {
        $pool    = new Pool();
        $rule    = $this->buildRule(['color']);
        $matcher = $this->buildMatcher($pool, [$rule]);

        $product = $this->buildProduct(['color' => null]);
        $this->assertNull($matcher->findForProduct($product));
    }
}
