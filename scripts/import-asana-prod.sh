#!/usr/bin/env bash
#
# Import Asana en production, une fois l'import validé en local.
#
#   1. sauvegarde la base de données et les fichiers (storage/backups/) ;
#   2. applique les migrations ;
#   3. (facultatif, --reset) efface les données de test, comptes administrateurs conservés ;
#   4. lance l'import Asana.
#
# Usage, depuis le dossier de l'application sur le serveur :
#   bash scripts/import-asana-prod.sh [--reset] [options de asana:import]
#
# Exemples :
#   bash scripts/import-asana-prod.sh
#   bash scripts/import-asana-prod.sh --reset --workspace=1234567890
#
# Le jeton Asana est lu dans ASANA_TOKEN (.env) ou demandé. Relancer le script est sans risque :
# l'import met à jour ce qui existe déjà, sans doublon. Variable PHP= pour choisir l'exécutable PHP.

set -euo pipefail
cd "$(dirname "$0")/.."

PHP="${PHP:-php}"
RESET=0
ARGS=()
for arg in "$@"; do
  if [[ "$arg" == "--reset" ]]; then RESET=1; else ARGS+=("$arg"); fi
done

env_value() {
  grep -E "^$1=" .env 2>/dev/null | tail -n 1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//" || true
}

[[ -f .env ]] || { echo "Fichier .env introuvable : lancez ce script depuis le dossier de l'application." >&2; exit 1; }

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="storage/backups"
mkdir -p "$BACKUP_DIR"

echo "→ Sauvegarde de la base de données…"
DB_CONNECTION="$(env_value DB_CONNECTION)"
if [[ "$DB_CONNECTION" == "mysql" || "$DB_CONNECTION" == "mariadb" ]]; then
  DUMP="$(command -v mysqldump || command -v mariadb-dump || true)"
  [[ -n "$DUMP" ]] || { echo "mysqldump introuvable : installez le client MySQL/MariaDB avant l'import." >&2; exit 1; }
  DB_HOST="$(env_value DB_HOST)"; DB_PORT="$(env_value DB_PORT)"
  MYSQL_PWD="$(env_value DB_PASSWORD)" "$DUMP" --single-transaction --routines --no-tablespaces \
    -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$(env_value DB_USERNAME)" \
    "$(env_value DB_DATABASE)" | gzip > "$BACKUP_DIR/base-$STAMP.sql.gz"
elif [[ "$DB_CONNECTION" == "sqlite" ]]; then
  cp "$(env_value DB_DATABASE)" "$BACKUP_DIR/base-$STAMP.sqlite"
else
  echo "Base « $DB_CONNECTION » non gérée par ce script : sauvegardez-la vous-même puis relancez avec SKIP_BACKUP=1." >&2
  [[ "${SKIP_BACKUP:-0}" == "1" ]] || exit 1
fi

echo "→ Sauvegarde des fichiers (pièces jointes, photos)…"
tar -czf "$BACKUP_DIR/fichiers-$STAMP.tar.gz" -C storage/app private
echo "  Sauvegardes : $BACKUP_DIR/base-$STAMP.* et $BACKUP_DIR/fichiers-$STAMP.tar.gz"

echo "→ Migrations…"
"$PHP" artisan migrate --force

if [[ "$RESET" == "1" ]]; then
  echo "→ Suppression des données de test…"
  "$PHP" artisan data:reset
fi

echo "→ Import Asana…"
"$PHP" artisan asana:import "${ARGS[@]+"${ARGS[@]}"}"

echo
echo "Terminé. En cas de problème, restaurez la sauvegarde :"
echo "  gunzip < $BACKUP_DIR/base-$STAMP.sql.gz | mysql -u <utilisateur> -p <base>"
echo "  tar -xzf $BACKUP_DIR/fichiers-$STAMP.tar.gz -C storage/app"
