<!-- FOR AI AGENTS - Human readability is a side effect, not a goal -->
<!-- Managed by agent: keep sections and order; edit content, not structure -->
<!-- Last updated: 2026-06-22 | Last verified: 2026-06-22 -->

# AGENTS.md — SR_SimpleProductLink

> Magento 2 module · namespace `SR\SimpleProductLink` · PHP ≥8.1 · Hyva theme (Tailwind + Alpine.js)

**Precedence:** the **closest `AGENTS.md`** to the files you're changing wins. Root holds global defaults only.

## Commands
> No QA tooling defined in `composer.json`. Commands below use DDEV (`ddev magento` = `bin/magento` inside container).

| Task | Command | ~Time |
|------|---------|-------|
| Enable module | `ddev magento module:enable SR_SimpleProductLink` | ~5s |
| DI compile | `ddev magento setup:di:compile` | ~30s |
| Run data patches | `ddev magento setup:upgrade` | ~15s |
| Flush FPC | `ddev magento cache:flush full_page` | ~3s |
| Flush all | `ddev magento cache:flush` | ~5s |

## File Map
```
src/registration.php                                      → module registration (required)
src/etc/module.xml                                        → module declaration + sequence
src/etc/di.xml                                            → plugin/preference/type wiring
src/etc/events.xml                                        → observed events (rule save/delete, product save)
src/etc/db_schema.xml                                     → DB tables: studioraz_simpleproductlink_rule + _variation_attribute
src/Api/                                                  → LinkRuleInterface + LinkRuleRepositoryInterface (public API)
src/Model/LinkRule.php                                    → entity model
src/Model/LinkRuleMatcher.php                             → finds highest-priority active rule for a product
src/Model/LinkRuleRepository.php                          → CRUD for LinkRule
src/Model/Cache/GroupCacheCleaner.php                     → dispatches clean_cache_by_tags for a set of group values
src/Model/Cache/GroupCacheTag.php                         → generates cache tags from group values
src/Model/Product/GroupValuesResolver.php                 → resolves group attribute values from product collection
src/Observer/InvalidateCacheOnRuleChange.php              → flushes full_page cache on rule save/delete
src/Observer/InvalidateCacheOnProductSave.php             → invalidates sibling product cache on group value change
src/Plugin/                                               → intercepts ProductAction + stock indexer to invalidate group cache
src/Setup/Patch/Data/AddSimpleProductGroupAttribute.php   → creates simple_product_group product attribute on install
src/Controller/Adminhtml/LinkRule/                        → admin CRUD controllers (Index/New/Edit/Save/Delete/MassDelete)
src/Ui/Component/DataProvider/LinkRuleDataProvider.php    → grid data provider
src/Block/Product/View/LinkedProducts.php                 → frontend block injecting ViewModel into layout
src/ViewModel/LinkedProducts.php                          → builds swatch+link data for PDP variant switcher
src/view/frontend/templates/product/view/linked-products.phtml → Hyva template (Tailwind + Alpine.js)
src/view/adminhtml/ui_component/                          → admin form + grid UI component XML
```

## Golden Samples

| For | Reference | Key pattern |
|-----|-----------|-------------|
| Observer (FPC flush) | `src/Observer/InvalidateCacheOnRuleChange.php` | Implement `ObserverInterface`; call `TypeListInterface::cleanType('full_page')` |
| Group cache invalidation | `src/Model/Cache/GroupCacheCleaner.php` | Dispatch `clean_cache_by_tags` via `CacheContextFactory` + `EventManager` |
| Rule matching | `src/Model/LinkRuleMatcher.php` | Iterate collection ordered by `priority DESC`; first matching conditions wins |
| Data patch | `src/Setup/Patch/Data/AddSimpleProductGroupAttribute.php` | Implement `DataPatchInterface`; idempotent attribute creation |
| Admin controller | `src/Controller/Adminhtml/LinkRule/Save.php` | ACL check, form validation, redirect to edit on error |
| View model | `src/ViewModel/LinkedProducts.php` | Implement `ArgumentInterface`; pure data assembly — no rendering |
| Phtml template | `src/view/frontend/templates/product/view/linked-products.phtml` | Use `$block->getViewModel()`; Tailwind classes; Alpine.js `x-data` |

## Terminology

| Term | Means |
|------|-------|
| `LinkRule` | Admin-defined rule: maps a set of variation attributes + conditions to a priority |
| `simple_product_group` | Product EAV attribute (text) that groups sibling simple products |
| `VariationAttribute` | An ordered `attribute_code` entry on a rule; drives one switcher row |
| `GroupCacheTag` | Cache tag derived from a group value; used to purge all sibling PDPs together |
| `LinkRuleMatcher` | Service that walks active rules by `priority DESC` and returns the first match |

## Heuristics

| When | Do |
|------|-----|
| New cache-aware feature | Register cache tags via `GroupCacheTag`; clean via `GroupCacheCleaner::cleanGroupValues()` |
| Rule cache flush (not product) | Use `TypeListInterface::cleanType('full_page')` in an Observer — see `InvalidateCacheOnRuleChange` |
| New product attribute | Always create via a `DataPatchInterface` data patch — never raw SQL or install schema |
| New admin page | Follow Controller + UI Component pattern; declare in `di.xml` + `menu.xml` + `acl.xml` |
| Plugin vs Observer | Plugin: wrap/modify a return value. Observer: react to a dispatched event, no return needed |
| Altering DB schema | Edit `db_schema.xml` only; run `bin/magento setup:upgrade` to apply |
| Committing | Conventional Commits: `feat(cache): ...`, `fix(matcher): ...`, `docs(readme): ...` |

## Codebase State

- No test suite configured — no PHPUnit or PHPStan in `composer.json`; add before introducing any
- Two DB tables: `studioraz_simpleproductlink_rule` (rules) + `studioraz_simpleproductlink_rule_variation_attribute` (FK → rule)
- `variation_attribute` rows have a `sort_order` column — always preserve ordering when saving
- No frontend JS bundle — Alpine.js and Tailwind come entirely from Hyva theme

## Boundaries

### Always Do
- Namespace: `SR\SimpleProductLink` (PSR-4, maps to `src/`)
- PHP 8.1+ syntax: constructor promotion, `readonly`, `match`, named args, union types
- Keep all data assembly in ViewModels; Blocks only wire the ViewModel into layout
- Attach cache tags via `GroupCacheTag` for any new product-display feature
- Declare new plugins/observers/preferences in `src/etc/di.xml` or `src/etc/events.xml`

### Ask First
- Adding Composer dependencies (none in `require-dev` yet — pick tooling carefully)
- Changing the `simple_product_group` attribute code or type (breaks existing group data)
- Modifying `LinkRuleRepositoryInterface` or `LinkRuleInterface` (public API contract)
- Adding new variation-attribute storage — `db_schema.xml` change needed

### Never Do
- Modify Magento core files or override core templates outside this module's `view/`
- Hard-code EAV attribute IDs, store IDs, or website IDs — always look up by code
- Bypass ACL in admin controllers
- Skip cache tag registration on features that affect product pages

## When instructions conflict
Nearest `AGENTS.md` wins. Explicit user prompt overrides file.
References: [Magento DevDocs](https://developer.adobe.com/commerce/docs/) · [Hyva docs](https://docs.hyva.io/)
