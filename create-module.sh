#!/usr/bin/env bash
# =============================================================================
# create-module.sh — Replace template placeholders in-place in this repository
#
# Usage:
#   ./create-module.sh --module-name=MyModule [--description="My description"]
#
# Placeholders replaced in all files under src/ and in composer.json:
#   {ModuleName}   → PascalCase module name   (e.g. MyModule)
#   {module-name}  → kebab-case module name   (e.g. my-module)
#   {description}  → composer description
# =============================================================================

set -euo pipefail

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

usage() {
  cat <<EOF
Usage:
  $(basename "$0") --module-name=<PascalCaseName> [--description="<text>"]

Options:
  --module-name   Required. PascalCase name (e.g. MyModule).
  --description   Optional. Composer package description. Default: "SR_{ModuleName} Magento 2 module"
  --help          Show this help message.

Example:
  $(basename "$0") --module-name=ProductImport --description="Product import module"
EOF
}

to_kebab() {
  # PascalCase → kebab-case  (MyModule → my-module)
  echo "$1" | sed 's/\([A-Z]\)/-\1/g' | sed 's/^-//' | tr '[:upper:]' '[:lower:]'
}

replace_in_file() {
  local file="$1"
  # Use a temp file for portability (sed -i differs between macOS and Linux)
  local tmp
  tmp=$(mktemp)
  sed \
    -e "s/{ModuleName}/${MODULE_NAME}/g" \
    -e "s/{module-name}/${MODULE_NAME_KEBAB}/g" \
    -e "s/{description}/${DESCRIPTION}/g" \
    "$file" > "$tmp"
  mv "$tmp" "$file"
  echo "  Updated: $file"
}

# ---------------------------------------------------------------------------
# Parse arguments
# ---------------------------------------------------------------------------

MODULE_NAME=""
DESCRIPTION=""

for arg in "$@"; do
  case "$arg" in
    --module-name=*) MODULE_NAME="${arg#*=}" ;;
    --description=*) DESCRIPTION="${arg#*=}" ;;
    --help|-h)       usage; exit 0 ;;
    *) echo "Unknown option: $arg" >&2; usage; exit 1 ;;
  esac
done

# ---------------------------------------------------------------------------
# Validate
# ---------------------------------------------------------------------------

if [[ -z "$MODULE_NAME" ]]; then
  echo "Error: --module-name is required." >&2
  usage
  exit 1
fi

if [[ ! "$MODULE_NAME" =~ ^[A-Z][A-Za-z0-9]+$ ]]; then
  echo "Error: --module-name must be PascalCase (e.g. MyModule)." >&2
  exit 1
fi

MODULE_NAME_KEBAB=$(to_kebab "$MODULE_NAME")
DESCRIPTION="${DESCRIPTION:-SR_${MODULE_NAME} Magento 2 module}"

# ---------------------------------------------------------------------------
# Paths
# ---------------------------------------------------------------------------

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMPLATE_SRC="$REPO_ROOT/src"
TEMPLATE_COMPOSER="$REPO_ROOT/composer.json"

if [[ ! -d "$TEMPLATE_SRC" ]]; then
  echo "Error: src/ directory not found: $TEMPLATE_SRC" >&2
  exit 1
fi

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------

echo ""
echo "Replacing placeholders for module SR_${MODULE_NAME}"
echo "  PascalCase : $MODULE_NAME"
echo "  kebab-case : $MODULE_NAME_KEBAB"
echo "  description: $DESCRIPTION"
echo ""

# ---------------------------------------------------------------------------
# Replace in-place in all files under src/
# ---------------------------------------------------------------------------

while IFS= read -r -d '' file; do
  replace_in_file "$file"
done < <(find "$TEMPLATE_SRC" -type f -print0)

# ---------------------------------------------------------------------------
# Replace in composer.json
# ---------------------------------------------------------------------------

if [[ -f "$TEMPLATE_COMPOSER" ]]; then
  replace_in_file "$TEMPLATE_COMPOSER"
fi

# ---------------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------------

echo ""
echo "Done! All placeholders replaced in-place."
echo ""
echo "Next steps:"
echo "  1. Review the changes (git diff)"
echo "  2. Delete this file (create-module.sh)"
echo "  3. Implement your module logic in src/ directory"
echo ""
