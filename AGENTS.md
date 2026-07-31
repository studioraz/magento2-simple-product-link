<!-- FOR AI AGENTS - Human readability is a side effect, not a goal -->
<!-- Managed by agent: keep sections and order; edit content, not structure -->
<!-- Last updated: 2026-07-30 | Last verified: 2026-07-30 -->

# AGENTS.md — SR_SimpleProductLink package

> Magento 2 package · modules `SR_SimpleProductLink` + `SR_SimpleProductLinkLuma` · PHP ≥8.1 · Hyvä and Luma

**Precedence:** the **closest `AGENTS.md`** to the files you're changing wins. Root holds global defaults only.

## Commands
> No QA tooling defined in `composer.json`. Commands below use DDEV (`ddev magento` = `bin/magento` inside container).

| Task | Command | ~Time |
|------|---------|-------|
| Enable modules | `ddev magento module:enable SR_SimpleProductLink SR_SimpleProductLinkLuma` | ~5s |
| DI compile | `ddev magento setup:di:compile` | ~30s |
| Run data patches | `ddev magento setup:upgrade` | ~15s |
| Flush FPC | `ddev magento cache:flush full_page` | ~3s |
| Flush all | `ddev magento cache:flush` | ~5s |

## File Map
```
src/SimpleProductLink/registration.php                                      → core/Hyvä module registration
src/SimpleProductLink/etc/module.xml                                        → core module declaration + sequence
src/SimpleProductLink/etc/di.xml                                            → plugin/preference/type wiring
src/SimpleProductLink/etc/events.xml                                        → observed events (rule save/delete, product save)
src/SimpleProductLink/etc/db_schema.xml                                     → DB tables: studioraz_simpleproductlink_rule + _variation_attribute
src/SimpleProductLink/Api/                                                  → LinkRuleInterface + LinkRuleRepositoryInterface (public API)
src/SimpleProductLink/Model/LinkRule.php                                    → entity model
src/SimpleProductLink/Model/LinkRuleMatcher.php                             → finds highest-priority active rule for a product
src/SimpleProductLink/Model/LinkRuleRepository.php                          → CRUD for LinkRule
src/SimpleProductLink/Model/Cache/GroupCacheCleaner.php                     → dispatches clean_cache_by_tags for a set of group values
src/SimpleProductLink/Model/Cache/GroupCacheTag.php                         → generates cache tags from group values
src/SimpleProductLink/Model/Product/GroupValuesResolver.php                 → resolves group attribute values from product collection
src/SimpleProductLink/Observer/InvalidateCacheOnRuleChange.php              → flushes full_page cache on rule save/delete
src/SimpleProductLink/Observer/InvalidateCacheOnProductSave.php             → invalidates sibling product cache on group value change
src/SimpleProductLink/Plugin/                                               → intercepts ProductAction + stock indexer to invalidate group cache
src/SimpleProductLink/Setup/Patch/Data/AddSimpleProductGroupAttribute.php   → creates simple_product_group product attribute on install
src/SimpleProductLink/Controller/Adminhtml/LinkRule/                        → admin CRUD controllers
src/SimpleProductLink/Ui/Component/DataProvider/LinkRuleDataProvider.php    → grid data provider
src/SimpleProductLink/Block/Product/View/LinkedProducts.php                 → shared frontend block
src/SimpleProductLink/ViewModel/LinkedProducts.php                          → shared PDP variation data
src/SimpleProductLink/view/frontend/                                        → original Hyvä layout + Tailwind template
src/SimpleProductLink/view/adminhtml/ui_component/                          → admin form + grid UI component XML
src/SimpleProductLinkLuma/registration.php                                  → Luma module registration
src/SimpleProductLinkLuma/etc/module.xml                                    → depends on SR_SimpleProductLink
src/SimpleProductLinkLuma/view/frontend/                                    → Luma layout, template, and LESS
```

## Golden Samples

| For | Reference | Key pattern |
|-----|-----------|-------------|
| Observer (FPC flush) | `src/SimpleProductLink/Observer/InvalidateCacheOnRuleChange.php` | Implement `ObserverInterface`; call `TypeListInterface::cleanType('full_page')` |
| Group cache invalidation | `src/SimpleProductLink/Model/Cache/GroupCacheCleaner.php` | Dispatch `clean_cache_by_tags` via `CacheContextFactory` + `EventManager` |
| Rule matching | `src/SimpleProductLink/Model/LinkRuleMatcher.php` | Iterate collection ordered by `priority DESC`; first matching conditions wins |
| Data patch | `src/SimpleProductLink/Setup/Patch/Data/AddSimpleProductGroupAttribute.php` | Implement `DataPatchInterface`; idempotent attribute creation |
| Admin controller | `src/SimpleProductLink/Controller/Adminhtml/LinkRule/Save.php` | ACL check, form validation, redirect to edit on error |
| View model | `src/SimpleProductLink/ViewModel/LinkedProducts.php` | Implement `ArgumentInterface`; shared data assembly |
| Hyvä template | `src/SimpleProductLink/view/frontend/templates/product/view/linked-products.phtml` | Preserve the original Tailwind implementation |
| Luma template | `src/SimpleProductLinkLuma/view/frontend/templates/product/view/linked-products.phtml` | BEM classes styled by module LESS |

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
- No frontend JS bundle
- Hyvä presentation belongs to `SR_SimpleProductLink`; Luma presentation belongs to `SR_SimpleProductLinkLuma`
- Both Magento modules are registered by the same Composer package

## Boundaries

### Always Do
- Namespace `SR\SimpleProductLink` maps to `src/SimpleProductLink/`
- Namespace `SR\SimpleProductLinkLuma` maps to `src/SimpleProductLinkLuma/`
- PHP 8.1+ syntax: constructor promotion, `readonly`, `match`, named args, union types
- Keep all data assembly in ViewModels; Blocks only wire the ViewModel into layout
- Attach cache tags via `GroupCacheTag` for any new product-display feature
- Declare core plugins/observers/preferences in `src/SimpleProductLink/etc/di.xml` or `events.xml`
- Keep Luma-specific layout, templates, and LESS inside `src/SimpleProductLinkLuma/view/frontend/`

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
