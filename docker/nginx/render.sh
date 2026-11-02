#!/usr/bin/env bash
# Render flexiproject.conf.template with the values from ../.env.
#
# Usage:
#   sudo ./render.sh /etc/nginx/conf.d/flexiproject.conf
#   sudo nginx -t && sudo systemctl reload nginx

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMPLATE="${SCRIPT_DIR}/flexiproject.conf.template"
ENV_FILE="${SCRIPT_DIR}/../.env"

OUT="${1:-}"
[[ -n "$OUT" ]] || { echo "usage: $0 <output-path>" >&2; exit 2; }
[[ -f "$ENV_FILE" ]] || { echo "missing env: $ENV_FILE" >&2; exit 1; }

VARS=(
    FLEXIPROJECT_APP_DOMAIN
    INTERNAL_FLEXIPROJECT_APP_IP
    FLEXIPROJECT_SSL_CERT
    FLEXIPROJECT_SSL_KEY
)

for v in "${VARS[@]}"; do
    value="$(sed -n "s/^${v}=//p" "$ENV_FILE" | tr -d '"' | tail -n 1)"
    [[ -n "$value" ]] || { echo "missing in $ENV_FILE: $v" >&2; exit 1; }
    export "$v=$value"
done

envsubst "$(printf '${%s} ' "${VARS[@]}")" < "$TEMPLATE" > "$OUT"
echo "rendered → $OUT" >&2
