<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Api;

use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Represents a virtual variation attribute whose value is computed at runtime.
 *
 * Implement this interface and register the implementation in the Pool via di.xml
 * to expose a computed attribute as a selectable variation axis in SR_SimpleProductLink rules.
 *
 * The attribute code must not collide with any real EAV attribute code.
 * Recommended convention: use a module-prefixed code, e.g. "sr_baseprice_amount_unit".
 *
 * @api
 */
interface VirtualAttributeInterface
{
    /**
     * Unique attribute code used in rule configuration.
     * Must not collide with any real EAV attribute code.
     */
    public function getAttributeCode(): string;

    /**
     * Label shown in the admin rule form's Variation Attribute dropdown.
     * Should be descriptive for store administrators.
     */
    public function getAdminLabel(): string;

    /**
     * Label shown on the storefront as the variation axis heading.
     *
     * Receives the current (viewable) product so the label can be derived from
     * product data (e.g. the unit category of the current product determines
     * whether the axis is labelled "Weight", "Volume", etc.).
     */
    public function getFrontendLabel(ProductInterface $product): string;

    /**
     * Compute and return the display value for the given product.
     *
     * Return null when the value is unavailable or not applicable —
     * the product will be excluded from the group for this axis.
     */
    public function getValueForProduct(ProductInterface $product): ?string;

    /**
     * Return the real EAV attribute codes that must be loaded on the product
     * collection for getValueForProduct() and getFrontendLabel() to work correctly.
     * These codes are added to addAttributeToSelect() when building the group collection.
     *
     * @return string[]
     */
    public function getDependsOnAttributeCodes(): array;
}
