<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Test\Unit\Model\VirtualAttribute;

use PHPUnit\Framework\TestCase;
use SR\SimpleProductLink\Api\VirtualAttributeInterface;
use SR\SimpleProductLink\Model\VirtualAttribute\Pool;

class PoolTest extends TestCase
{
    private function makeAttribute(string $code): VirtualAttributeInterface
    {
        $attr = $this->createMock(VirtualAttributeInterface::class);
        $attr->method('getAttributeCode')->willReturn($code);
        return $attr;
    }

    public function testEmptyPoolReturnsNoAttributes(): void
    {
        $pool = new Pool();
        $this->assertSame([], $pool->getAll());
        $this->assertFalse($pool->has('anything'));
        $this->assertNull($pool->getByCode('anything'));
    }

    public function testRegisteredAttributeIsRetrievable(): void
    {
        $attr = $this->makeAttribute('my_virtual');
        $pool = new Pool([$attr]);

        $this->assertTrue($pool->has('my_virtual'));
        $this->assertSame($attr, $pool->getByCode('my_virtual'));
        $this->assertCount(1, $pool->getAll());
    }

    public function testUnknownCodeReturnsNull(): void
    {
        $pool = new Pool([$this->makeAttribute('attr_a')]);
        $this->assertNull($pool->getByCode('does_not_exist'));
        $this->assertFalse($pool->has('does_not_exist'));
    }

    public function testMultipleAttributesAreAllRetrievable(): void
    {
        $a = $this->makeAttribute('attr_a');
        $b = $this->makeAttribute('attr_b');
        $pool = new Pool([$a, $b]);

        $this->assertCount(2, $pool->getAll());
        $this->assertSame($a, $pool->getByCode('attr_a'));
        $this->assertSame($b, $pool->getByCode('attr_b'));
    }

    public function testDuplicateCodeLastWins(): void
    {
        $first  = $this->makeAttribute('same_code');
        $second = $this->makeAttribute('same_code');
        $pool   = new Pool([$first, $second]);

        $this->assertSame($second, $pool->getByCode('same_code'));
        $this->assertCount(1, $pool->getAll());
    }
}
