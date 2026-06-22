<!-- FOR AI AGENTS - Human readability is a side effect, not a goal -->
<!-- Managed by agent: keep sections and order; edit content, not structure -->
<!-- Last updated: 2026-06-22 | Last verified: 2026-06-22 -->

# AGENTS.md — SR SimpleProductLink (Magento 2 Module)

**Precedence:** the **closest `AGENTS.md`** to the files you're changing wins. Root holds global defaults only.

## Quick Facts

| Fact | Value |
|------|-------|
| **Type** | Magento 2 module (PSR-4 autoload) |
| **Namespace** | `SR\SimpleProductLink` |
| **Package** | `studioraz/magento2-simple-product-link` |
| **PHP** | ≥8.1 |
| **Theme** | Hyva (Tailwind CSS + Alpine.js) |
| **Magento** | 2.4.x |
| **Dependencies** | `magento/framework`, `studioraz/magento2-base` |
| **License** | MIT |

## Commands (unverified)
> Source: composer.json — add CI-sourced commands as you discover them

| Task | Command | ~Time |
|------|---------|-------|
| Dry-run lint | `vendor/bin/phpstan analyse src/ --no-progress` | ~15s |
| List Composer scripts | `composer run-script --list` | ~2s |
| Module enable | `bin/magento module:enable SR_SimpleProductLink` | ~5s |
| DI compile | `bin/magento setup:di:compile` | ~30s |
| Cache flush | `bin/magento cache:flush full_page` | ~3s |

> If commands fail, verify against composer.json or ask user to update.

## Workflow
1. **Before coding**: Read this AGENTS.md + check Golden Samples for the area you're touching
2. **After each change**: Run at minimum a lint check on changed files
3. **Before committing**: Verify all **Boundaries → Always Do** items are met

## File Map
```
src/registration.php              → Magento module registration (required)
src/Api/                           → Data interfaces & repositories (public API)
src/Block/                         → Template blocks (view layer)
src/Controller/Adminhtml/LinkRule/ → Admin UI controllers
src/Model/                         → Domain logic, repositories, cache (business layer)
src/Observer/                      → Event observers (cache invalidation)
src/Plugin/                        → Plugins to intercept Magento calls
src/Setup/Patch/                   → Data patches (attribute creation)
src/Ui/Component/                  → Admin UI components (grids, forms)
src/ViewModel/                     → View models for templates
src/view/                          → Phtml templates
src/etc/                           → Magento config (routes, di, acl, events, cron, etc.)
README.md                          → User documentation
composer.json                      → Project metadata & dependencies
```

## Golden Samples (follow these patterns)

| For | Reference | Key patterns |
|-----|-----------|--------------|
| **Cache invalidation** | `src/Observer/InvalidateCacheOnRuleChange.php` | Use cache tags, observer pattern, observe Magento lifecycle events |
| **Plugin interceptor** | `src/Plugin/Magento/Catalog/Model/ProductActionGroupCacheInvalidator.php` | Intercept product save to invalidate sibling cache tags |
| **Data patch** | `src/Setup/Patch/Data/AddSimpleProductGroupAttribute.php` | Create product attributes on install; use `DataPatchInterface` |
| **Admin controller** | `src/Controller/Adminhtml/LinkRule/Save.php` | Handle ACL, form validation, forward errors to edit action |
| **Admin grid** | `src/Ui/Component/DataProvider/LinkRuleDataProvider.php` | Use Magento's DataProvider pattern for grid binding |
| **View model** | `src/ViewModel/LinkedProducts.php` | Retrieve linked products; bind to Phtml blocks via module layout XML |
| **Template block** | `src/Block/LinkedProductsSwitcher.php` | Extend `AbstractBlock`; inject repositories; expose public methods for templates |
| **Repository** | `src/Model/LinkRuleRepository.php` | Implement Magento `ResourceModel\AbstractResourceModel`; support save/load/delete |

## Heuristics (quick decisions)

| When | Do |
|------|-----|
| **Adding a class** | Place in appropriate `src/` subfolder (Model/, Block/, etc.) matching Magento's layer conventions |
| **Creating cache invalidation** | Add an Observer or Plugin that calls `getCacheManager()->invalidate()` with appropriate cache tags |
| **Modifying product attributes** | Always use a Data Patch; attribute changes are idempotent and install-safe |
| **Modifying admin pages** | Use Magento UI Components (grid, form) tied to Controllers + DataProviders |
| **Frontend rendering** | Use Phtml templates with ViewModels injected; leverage Hyva's Tailwind + Alpine |
| **Intercepting Magento** | Use Plugins (`di.xml`) when you need to wrap/modify return values; use Observers when you only care about the event |
| **Committing** | Use Conventional Commits: `feat(module): ...`, `fix(cache): ...`, `docs(readme): ...` |

## Repository Settings
- **Default branch:** `master`
- **Merge strategy:** squash or rebase (keep history clean)
- **Committer:** Include `Co-authored-by: Copilot` trailer in commit messages

## Boundaries

### Always Do
- Follow **PSR-4** autoload namespace (`SR\SimpleProductLink`)
- Use PHP 8.1+ type hints (`string`, `?int`, `self`, etc.)
- Add cache tags and cache tag handling for any feature that impacts product display
- Respect Magento's event lifecycle (before_save, after_save, etc.)
- Test Admin UI logic against ACL permissions
- Keep all template logic in ViewModels, not Blocks

### Ask First
- Adding new Magento dependencies (framework, base module version bump)
- Modifying the `simple_product_group` attribute definition
- Changing cache invalidation strategy (impacts performance)
- Adding or modifying public API interfaces
- Introducing new frontend libraries beyond Hyva's Tailwind + Alpine
- Changing default rule priority or condition logic

### Never Do
- Commit `var/`, `vendor/`, `pub/`, or any generated directories
- Modify Magento core files or override core templates outside this module
- Hard-code attribute IDs, website IDs, or store view IDs (use lookup methods)
- Bypass Magento's ACL for admin controllers
- Cache data without attaching cache tags for invalidation
- Commit database credentials or API keys
- Use `eval()` or `$_REQUEST` directly in controllers
- Modify XML files outside `src/etc/` without understanding DI & routing

## Testing & QA Checklist

Before committing:

- [ ] **Lint**: Run PHPStan or similar on changed files
- [ ] **Admin UI**: Verify Link Rule CRUD works (create, read, update, delete)
- [ ] **Frontend**: Check variant switcher renders on PDP with correct cache behavior
- [ ] **Cache**: Confirm cache tags invalidate on rule change and product stock update
- [ ] **Attribute**: Verify `simple_product_group` is created on first install (Data Patch)
- [ ] **Conditions**: Test Catalog Rule conditions targeting subset of products
- [ ] **Out of Stock**: Verify diagonal strikethrough + tooltip per config setting
- [ ] **Swatch Types**: Check color, image, and text swatches render correctly
- [ ] **Composer Install**: Confirm `composer require` works; dependencies resolve

## Key Concepts

### LinkRule Model
- Admin-defined rules that specify which variation attributes to display
- Rules have **Priority** (higher = wins when multiple match)
- Rules have **Conditions** (standard Magento catalog-rule conditions; empty = all products)
- Rules have **VariationAttributes** (ordered list of product attributes to show as switcher rows)

### Simple Product Group
- Product attribute (`simple_product_group`) that groups independent simple products
- Multiple products share same group value → displayed as variants on each product's PDP
- Each variant in the group must have different values for the variation attributes

### Cache Strategy
- **Rule changes** → Invalidate full FPC
- **Product group changes** → Invalidate sibling products in old + new groups
- **Stock updates** → Use product cache tags to propagate to variant switchers
- **Swatch options** → Cached in product identities; clear when attribute options change

### Hyva Frontend
- Variant switcher uses Tailwind CSS utility classes for styling
- Alpine.js handles click handlers and product URL routing (no page reload)
- Swatches rendered as color boxes, images, or text labels
- Out-of-stock state shows reduced opacity + diagonal strikethrough + tooltip

## When instructions conflict
The nearest `AGENTS.md` wins. Explicit user prompts override files.
- For Magento-specific questions, check Magento DevDocs
- For Hyva-specific questions, check Hyva documentation
- For PSR-4 / PHP standards, use PSR-12 code style
