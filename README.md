# Magento 2 Simple Product Link — Variant Switcher for Simple Products

A custom Magento 2 module that links independent simple products together and renders an interactive variant switcher on the Product Detail Page (PDP). Unlike Magento's native configurable product approach, every product remains a standalone simple product while the storefront delivers a seamless variation-selection experience.

> **Supports Hyvä and Luma-based storefronts** — each theme family has an isolated Magento frontend module.

---

## Table of Contents

- [Features](#features)
- [How It Works](#how-it-works)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [1. Create a Link Rule](#1-create-a-link-rule)
  - [2. Assign Products to a Group](#2-assign-products-to-a-group)
- [Frontend Behavior](#frontend-behavior)
  - [Display Rules](#display-rules)
  - [Swatch Support](#swatch-support)
  - [Out-of-Stock Handling](#out-of-stock-handling)
  - [Sorting & Display Order](#sorting--display-order)
- [Cache Invalidation](#cache-invalidation)
- [License](#license)

---

## Features

- **Group simple products** via a shared text attribute — no configurable products required.
- **Admin-managed Link Rules** with variation attributes, catalog-rule conditions, and priority.
- **Multiple variation attributes** per rule (e.g., Color + Size) with drag-and-drop ordering.
- **Color, image, and text swatches** using Magento's native Swatches module.
- **Out-of-stock indicators** — diagonal strikethrough + tooltip, respects catalog display setting.
- **Zero theme modifications** — theme-specific layout XML injects the variant switcher on the PDP.
- **Group-aware cache invalidation** — rule changes, product group changes, and stock updates automatically purge the correct FPC entries.

---

## How It Works

1. Products are grouped by assigning the same value to the `simple_product_group` attribute.
2. An admin-defined **Link Rule** specifies which variation attributes to display and which products the rule applies to (via catalog-rule conditions).
3. On the storefront, the active theme implementation renders a variant switcher. Each variant is a clickable link to the sibling product's PDP.

---

## Requirements

| Requirement         | Version / Notes                |
|---------------------|-------------------------------|
| Magento             | 2.4.x                        |
| PHP                 | Per Magento 2.4 requirements  |
| Theme               | Hyvä or Luma-based theme      |
| `SR_Base` module    | Must be installed and enabled |

---

## Installation

### Via Composer (private repository)

```bash
composer require studioraz/magento2-simple-product-link
bin/magento module:enable SR_SimpleProductLink SR_SimpleProductLinkLuma
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### Manual installation

1. Copy `src/SimpleProductLink/` to `app/code/SR/SimpleProductLink/`.
2. Copy `src/SimpleProductLinkLuma/` to `app/code/SR/SimpleProductLinkLuma/`.
3. Run:

```bash
bin/magento module:enable SR_SimpleProductLink SR_SimpleProductLinkLuma
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

The data patch will automatically create the `simple_product_group` product attribute on first install.

---

## Configuration

### 1. Create a Link Rule

Navigate to **Admin → Studio Raz → Simple Product Link → Link Rules** and click **Add New Rule**.

| Field                   | Description |
|-------------------------|-------------|
| **Name**                | Human-readable rule name (required). |
| **Description**         | Optional notes about the rule's purpose. |
| **Active**              | Enable or disable the rule. |
| **Variation Attributes**| One or more `select`-type attributes (e.g., Color, Size). Drag-and-drop to set display order. |
| **Priority**            | Integer — higher value = higher priority. When multiple rules match a product, only the highest-priority rule is applied. |
| **Conditions**          | Standard Magento catalog-rule conditions to target specific products. Leave empty to match **all** products. |

### 2. Assign Products to a Group

1. Open a simple product in **Admin → Catalog → Products**.
2. Set the **Simple Product Group** attribute to a shared group identifier (any arbitrary string, e.g., `blue-widget-family`).
3. Repeat for every sibling product in the group. Ensure each product has a **different** value for the variation attribute(s) defined in the matching rule.

---

## Frontend Behavior

### Display Rules

| Condition | Result |
|-----------|--------|
| Product has no `simple_product_group` value | No switcher displayed |
| Group has only 1 product | No switcher displayed |
| An attribute has only 1 unique option across group products | That attribute row is hidden |
| Product is not a simple product (configurable, bundle, etc.) | No switcher displayed |
| No active rule matches the product | No switcher displayed |

When conditions are met, the switcher renders in the product information area with one row per variation attribute.

### Swatch Support

| Type            | Rendering |
|-----------------|-----------|
| **Color swatch** | Colored square with the configured background color. |
| **Image swatch** | Square with the swatch image as background. |
| **Text / Dropdown** | Text label inside a bordered box. |

### Out-of-Stock Handling

Behavior depends on **Stores → Configuration → Catalog → Inventory → Display Out of Stock Products**:

- **Yes** — The option is visible but **not clickable** (rendered as `<span>`). It shows reduced opacity, a diagonal strikethrough line, and a tooltip reading _"Option Label – Out of Stock"_.
- **No** — The option is completely hidden.

### Sorting & Display Order

| What is sorted | Controlled by | Where to change |
|----------------|---------------|-----------------|
| **Attribute group order** (e.g., Color row above Size row) | Variation Attributes sort order in the Link Rule | Admin → Link Rules → Drag-and-drop |
| **Options within a group** (e.g., Red, Blue, Green) | Attribute option sort order | Admin → Stores → Attributes → Product → [Attribute] → Manage Options |

---

## Cache Invalidation

The module implements a three-layer cache strategy:

1. **Rule changes** (create / update / delete) — Flushes the full-page cache entirely via observers on `studioraz_simpleproductlink_rule_save_after` and `_delete_after`.
2. **Product group changes** — When a product's `simple_product_group` value is modified, all sibling products in both the **old** and **new** groups are invalidated.
3. **Cache tag propagation** — The `ProductIdentitiesExtender` plugin adds sibling product cache tags to each product's identities, so Varnish / FPC purges cascade to all related variant pages.

---


## Troubleshooting

| Symptom | Possible Cause | Solution |
|---------|----------------|----------|
| Variant switcher does not appear | Product has no `simple_product_group` value | Set the attribute on the product and its siblings |
| Variant switcher does not appear | Group contains only 1 product | Add at least one more sibling with the same group value |
| Variant switcher does not appear | No active Link Rule matches | Verify rule is active and its conditions include the product |
| Stale switcher after rule change | FPC not flushed | Flush cache: `bin/magento cache:flush full_page` |
| Attribute row missing | Only 1 unique option exists in the group for that attribute | Ensure siblings have different values for the variation attribute |
| Swatches show as text labels | Swatch data not configured | Configure swatches under Admin → Stores → Attributes → Product → [Attribute] → Manage Options |

---

## License

MIT — © Studio Raz. All rights reserved.
