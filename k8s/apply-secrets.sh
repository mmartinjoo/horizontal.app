#!/bin/bash
set -e

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="$PROJECT_ROOT/api/.env"
TEMPLATE_FILE="$SCRIPT_DIR/app-secrets.yaml.template"
OUTPUT_FILE="tmp_app-secrets.yaml"

# Check if .env file exists
if [ ! -f "$ENV_FILE" ]; then
    echo -e "${RED}Error: .env file not found at $ENV_FILE${NC}"
    exit 1
fi

# Check if template file exists
if [ ! -f "$TEMPLATE_FILE" ]; then
    echo -e "${RED}Error: Template file not found at $TEMPLATE_FILE${NC}"
    exit 1
fi

echo -e "${YELLOW}Loading environment variables from $ENV_FILE...${NC}"

# Export all variables from .env file
# This uses set -a to automatically export, and source to handle the .env file
# including multiline quoted values
set -a
source "$ENV_FILE" 2>/dev/null || {
    echo -e "${RED}Error: Failed to source .env file. It may have syntax errors.${NC}"
    exit 1
}
set +a

echo -e "${GREEN}Environment variables loaded.${NC}"
echo -e "${YELLOW}Substituting and base64 encoding variables...${NC}"

# Function to get base64-encoded value of a variable
# Handles both regular and multiline values
get_base64_value() {
    local var_name="$1"
    local var_value="${!var_name}"

    # If the variable is empty or unset, return empty base64 encoded string
    if [ -z "$var_value" ]; then
        echo -n "" | base64
        return
    fi

    # Base64 encode the value (handles multiline automatically)
    echo -n "$var_value" | base64 | tr -d '\n'
}

# Process the template file
> "$OUTPUT_FILE"  # Clear output file

while IFS= read -r line || [ -n "$line" ]; do
    # Check if line contains a variable placeholder like $VARIABLE or "$VARIABLE"
    if [[ $line =~ \$[A-Z_][A-Z0-9_]* ]]; then
        # Extract variable name (without the $)
        var_name=$(echo "$line" | grep -o '\$[A-Z_][A-Z0-9_]*' | sed 's/\$//')

        # Get the base64 encoded value
        base64_value=$(get_base64_value "$var_name")

        # Replace the placeholder with the base64 value
        # Remove quotes around the placeholder if present
        processed_line=$(echo "$line" | sed "s/\"\$$var_name\"/$base64_value/" | sed "s/\$$var_name/$base64_value/")

        echo "$processed_line" >> "$OUTPUT_FILE"
    else
        # No variable in this line, write as-is
        echo "$line" >> "$OUTPUT_FILE"
    fi
done < "$TEMPLATE_FILE"

echo -e "${GREEN}✓ Secrets file generated at: $OUTPUT_FILE${NC}"
echo -e "${GREEN}✓ All values have been base64 encoded${NC}"

# Check if --apply flag is passed
if [ "$1" == "--apply" ]; then
    echo -e "${YELLOW}Applying secrets to Kubernetes cluster...${NC}"
    kubectl apply -f "$OUTPUT_FILE"
    echo -e "${GREEN}✓ Secrets applied successfully!${NC}"
    echo ""
    echo -e "${YELLOW}Verify with:${NC}"
    echo "  kubectl get secret app-secrets"
    echo "  kubectl describe secret app-secrets"
    echo ""
    echo -e "${YELLOW}To decode a secret value:${NC}"
    echo "  kubectl get secret app-secrets -o jsonpath='{.data.APP_KEY}' | base64 --decode"
else
    echo ""
    echo -e "${YELLOW}Preview the generated secrets (first 20 lines):${NC}"
    head -20 "$OUTPUT_FILE"
    echo ""
    echo -e "${YELLOW}To apply these secrets to your cluster, run:${NC}"
    echo "  $0 --apply"
    echo ""
    echo -e "${YELLOW}Or manually apply with:${NC}"
    echo "  kubectl apply -f $OUTPUT_FILE"
    echo ""
    echo -e "${YELLOW}To view the full generated file:${NC}"
    echo "  cat $OUTPUT_FILE"
fi
