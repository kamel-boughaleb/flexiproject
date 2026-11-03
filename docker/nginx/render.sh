#!/usr/bin/env bash
# Render flexiproject.conf.template with the values from ../.env.
#
# Usage:
#   sudo ./render.sh /etc/nginx/conf.d/flexiproject.conf
#   sudo nginx -t && sudo systemctl reload nginx
#
# FLEXIPROJECT_SSL_CERT/KEY set (server): the vhost names its certificate.
# Both empty (local): the ssl_certificate lines are dropped and the vhost
# inherits the per-domain cert from `local-tls` (~/dotfiles/scripts/local-tls).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMPLATE="${SCRIPT_DIR}/flexiproject.conf.template"
ENV_FILE="${SCRIPT_DIR}/../.env"

OUT="${1:-}"
[[ -n "$OUT" ]] || { echo "usage: $0 <output-path>" >&2; exit 2; }
[[ -f "$ENV_FILE" ]] || { echo "missing env: $ENV_FILE" >&2; exit 1; }

env_value() { sed -n "s/^$1=//p" "$ENV_FILE" | tr -d '"' | tail -n 1; }

REQUIRED_VARS=(
    FLEXIPROJECT_APP_DOMAIN
    INTERNAL_FLEXIPROJECT_APP_IP
)
CERT_VARS=(
    FLEXIPROJECT_SSL_CERT
    FLEXIPROJECT_SSL_KEY
)

for v in "${REQUIRED_VARS[@]}" "${CERT_VARS[@]}"; do
    export "$v=$(env_value "$v")"
done

for v in "${REQUIRED_VARS[@]}"; do
    [[ -n "${!v}" ]] || { echo "missing in $ENV_FILE: $v" >&2; exit 1; }
done

if [[ -n "$FLEXIPROJECT_SSL_CERT" && -n "$FLEXIPROJECT_SSL_KEY" ]]; then
    drop_cert_lines() { cat; }
elif [[ -z "$FLEXIPROJECT_SSL_CERT" && -z "$FLEXIPROJECT_SSL_KEY" ]]; then
    drop_cert_lines() { grep -vE '^[[:space:]]*ssl_certificate(_key)?[[:space:]]'; }
    command -v local-tls >/dev/null 2>&1 && ! grep -q "^${FLEXIPROJECT_APP_DOMAIN} " <<< "$(local-tls ls)" \
        && echo "note: no local cert for ${FLEXIPROJECT_APP_DOMAIN} yet — run: local-tls add ${FLEXIPROJECT_APP_DOMAIN}" >&2
else
    echo "set both FLEXIPROJECT_SSL_CERT and FLEXIPROJECT_SSL_KEY in $ENV_FILE, or neither" >&2
    exit 1
fi

envsubst "$(printf '${%s} ' "${REQUIRED_VARS[@]}" "${CERT_VARS[@]}")" < "$TEMPLATE" | drop_cert_lines > "$OUT"
echo "rendered → $OUT" >&2
