<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Test\Unit\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\TestCase;
use SR\SimpleProductLink\Setup\Patch\Data\AddSimpleProductGroupAttribute;
use SR\SimpleProductLink\Setup\Patch\Data\AllowAllProductTypesForGroupAttribute;

class AllowAllProductTypesForGroupAttributeTest extends TestCase
{
    public function testApplyClearsApplyToRestriction(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('startSetup');
        $connection->expects($this->once())->method('endSetup');

        $moduleDataSetup = $this->createMock(ModuleDataSetupInterface::class);
        $moduleDataSetup->method('getConnection')->willReturn($connection);

        $eavSetup = $this->createMock(EavSetup::class);
        $eavSetup->expects($this->once())
            ->method('updateAttribute')
            ->with(
                Product::ENTITY,
                AddSimpleProductGroupAttribute::ATTRIBUTE_CODE,
                'apply_to',
                ''
            );

        $eavSetupFactory = $this->createMock(EavSetupFactory::class);
        $eavSetupFactory->expects($this->once())
            ->method('create')
            ->with(['setup' => $moduleDataSetup])
            ->willReturn($eavSetup);

        $patch = new AllowAllProductTypesForGroupAttribute(
            $moduleDataSetup,
            $eavSetupFactory,
        );

        $patch->apply();
    }

    public function testPatchRunsAfterAttributeCreation(): void
    {
        $this->assertSame(
            [AddSimpleProductGroupAttribute::class],
            AllowAllProductTypesForGroupAttribute::getDependencies()
        );
    }
}
