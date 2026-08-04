<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AllowAllProductTypesForGroupAttribute implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
    ) {}

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        try {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
            $eavSetup->updateAttribute(
                Product::ENTITY,
                AddSimpleProductGroupAttribute::ATTRIBUTE_CODE,
                'apply_to',
                ''
            );
        } finally {
            $this->moduleDataSetup->getConnection()->endSetup();
        }
    }

    public static function getDependencies(): array
    {
        return [AddSimpleProductGroupAttribute::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
