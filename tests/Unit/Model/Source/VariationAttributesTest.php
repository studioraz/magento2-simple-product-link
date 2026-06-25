<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Test\Unit\Model\Source;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use PHPUnit\Framework\TestCase;
use SR\SimpleProductLink\Api\VirtualAttributeInterface;
use SR\SimpleProductLink\Model\Source\VariationAttributes;
use SR\SimpleProductLink\Model\VirtualAttribute\Pool;

class VariationAttributesTest extends TestCase
{
    private function makeEavConfig(array $attributes): EavConfig
    {
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getEntityAttributes')
            ->with(Product::ENTITY)
            ->willReturn($attributes);
        return $eavConfig;
    }

    /**
     * Create a mock AbstractAttribute that supports the magic-getter methods needed.
     * PHPUnit cannot stub `__call()` magic methods directly — we add them via addMethods().
     */
    private function makeEavAttribute(string $code, string $label, string $input): AbstractAttribute
    {
        $attr = $this->getMockBuilder(AbstractAttribute::class)
            ->disableOriginalConstructor()
            ->addMethods(['getFrontendLabel'])
            ->onlyMethods(['getAttributeCode', 'getFrontendInput'])
            ->getMock();

        $attr->method('getAttributeCode')->willReturn($code);
        $attr->method('getFrontendLabel')->willReturn($label);
        $attr->method('getFrontendInput')->willReturn($input);

        return $attr;
    }

    public function testSelectAttributesAppearInOptions(): void
    {
        $eavAttr = $this->makeEavAttribute('color', 'Color', 'select');
        $source  = new VariationAttributes($this->makeEavConfig([$eavAttr]), new Pool());

        $options = $source->toOptionArray();

        $values = array_column($options, 'value');
        $this->assertContains('color', $values);
    }

    public function testNonSelectAttributesAreExcluded(): void
    {
        $textAttr = $this->makeEavAttribute('description', 'Description', 'text');
        $source   = new VariationAttributes($this->makeEavConfig([$textAttr]), new Pool());

        $this->assertEmpty($source->toOptionArray());
    }

    public function testVirtualAttributeAppearsInOptions(): void
    {
        $virtual = $this->createMock(VirtualAttributeInterface::class);
        $virtual->method('getAttributeCode')->willReturn('sr_baseprice_amount_unit');
        $virtual->method('getAdminLabel')->willReturn('Product Amount and Unit');

        $pool   = new Pool([$virtual]);
        $source = new VariationAttributes($this->makeEavConfig([]), $pool);

        $options = $source->toOptionArray();
        $values  = array_column($options, 'value');

        $this->assertContains('sr_baseprice_amount_unit', $values);

        $labelEntry = array_filter($options, fn($o) => $o['value'] === 'sr_baseprice_amount_unit');
        $labelEntry = array_values($labelEntry);
        $this->assertSame('Product Amount and Unit (sr_baseprice_amount_unit, [virtual])', $labelEntry[0]['label']);
    }

    public function testVirtualAttributesAreAppendedAfterEavAttributes(): void
    {
        $eavAttr = $this->makeEavAttribute('color', 'Color', 'select');
        $virtual = $this->createMock(VirtualAttributeInterface::class);
        $virtual->method('getAttributeCode')->willReturn('sr_virtual');
        $virtual->method('getAdminLabel')->willReturn('Virtual Attr');

        $pool   = new Pool([$virtual]);
        $source = new VariationAttributes($this->makeEavConfig([$eavAttr]), $pool);

        $options = $source->toOptionArray();
        $this->assertCount(2, $options);
        // Last entry is the virtual attribute
        $this->assertSame('sr_virtual', $options[1]['value']);
    }
}
