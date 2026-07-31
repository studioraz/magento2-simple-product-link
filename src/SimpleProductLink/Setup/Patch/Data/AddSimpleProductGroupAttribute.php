<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddSimpleProductGroupAttribute implements DataPatchInterface
{
    public const ATTRIBUTE_CODE = 'simple_product_group';

    private ModuleDataSetupInterface $moduleDataSetup;
    private EavSetupFactory $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function apply(): void
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->addAttribute(
            Product::ENTITY,
            self::ATTRIBUTE_CODE,
            [
                'type' => 'varchar',
                'label' => 'Simple Product Group',
                'input' => 'text',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'apply_to' => 'simple',
                'user_defined' => false,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'sort_order' => 200,
            ]
        );

        $attributeSetId = $eavSetup->getDefaultAttributeSetId(Product::ENTITY);
        $defaultGroupId = $eavSetup->getDefaultAttributeGroupId(Product::ENTITY, $attributeSetId);
        $eavSetup->addAttributeToGroup(
            Product::ENTITY,
            $attributeSetId,
            $defaultGroupId,
            self::ATTRIBUTE_CODE,
            null
        );
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
