# Studio Raz Magento 2 Module Template

A template repository for creating new Magento 2 modules under the `SR_` vendor namespace.

## Creating a New Module

Use the included `create-module.sh` script to generate a new module from this template.
It copies all files from `src/` and `composer.json` into an `output/SR_<ModuleName>/` directory,
replacing all placeholders with the values you provide.

### 1. Clone this repository

```bash
git clone https://github.com/studioraz/magento2-module-template.git
cd magento2-module-template
```

### 2. Make the script executable

```bash
chmod +x create-module.sh
```

### 3. Run the script

```bash
./create-module.sh --module-name=<PascalCaseName> [--description="<text>"]
```

**Options:**

| Option | Required | Description |
|---|---|---|
| `--module-name` | ✅ Yes | PascalCase module name (e.g. `MyModule`) |
| `--description` | No | Composer package description. Defaults to `SR_<ModuleName> Magento 2 module` |
| `--help` | No | Show usage information |

**Example:**

```bash
./create-module.sh --module-name=ProductImport --description="Product import module"
```

This generates `output/SR_ProductImport/` with all placeholders replaced:

| Placeholder | Replaced with | Example |
|---|---|---|
| `{ModuleName}` | PascalCase name | `ProductImport` |
| `{module-name}` | kebab-case name | `product-import` |
| `{description}` | Composer description | `Product import module` |

```

[MIT](LICENCE)
