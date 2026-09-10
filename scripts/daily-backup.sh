#!/usr/bin/env bash
# Daily LMS backup: MySQL dump + project zip (+ optional moodledata).
# Intended for staging/production Linux hosts via cron.
# Keep credentials OUT of the web tree — use /etc/iiidem/backup.env (mode 600).
#
# Cron examples (02:15 daily):
#   Staging:    15 2 * * * /var/www/html/lms_stage/scripts/daily-backup.sh >> /var/log/iiidem-backup.log 2>&1
#   Production: 15 2 * * * /var/www/html/lms/scripts/daily-backup.sh >> /var/log/iiidem-backup-prod.log 2>&1
set -euo pipefail

ENV_FILE="${BACKUP_ENV_FILE:-/etc/iiidem/backup.env}"
if [[ -f "$ENV_FILE" ]]; then
  # shellcheck disable=SC1090
  source "$ENV_FILE"
fi

# Defaults — always override in /etc/iiidem/backup.env for the real host.
SITE_ROOT="${SITE_ROOT:-/var/www/html/lms}"
MOODLEDATA="${MOODLEDATA:-/var/www/html/moodledata}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/iiidem}"
KEEP_DAYS="${KEEP_DAYS:-7}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_NAME="${DB_NAME:-}"
DB_USER="${DB_USER:-}"
DB_PASS="${DB_PASS:-}"
INCLUDE_MOODLEDATA="${INCLUDE_MOODLEDATA:-1}"
# Optional full path if mysqldump is not on PATH (e.g. /usr/bin/mysqldump).
MYSQLDUMP_BIN="${MYSQLDUMP_BIN:-}"

STAMP="$(date +%Y%m%d-%H%M%S)"
DEST="${BACKUP_ROOT}/${STAMP}"
mkdir -p "$DEST"

log() { echo "[$(date -Is)] $*"; }

resolve_mysqldump() {
  if [[ -n "$MYSQLDUMP_BIN" && -x "$MYSQLDUMP_BIN" ]]; then
    echo "$MYSQLDUMP_BIN"
    return 0
  fi
  if command -v mysqldump >/dev/null 2>&1; then
    command -v mysqldump
    return 0
  fi
  local candidate
  for candidate in \
    /usr/bin/mysqldump \
    /usr/local/bin/mysqldump \
    /usr/local/mysql/bin/mysqldump \
    /opt/rh/rh-mysql*/root/usr/bin/mysqldump \
    /usr/libexec/mysqldump
  do
    # shellcheck disable=SC2086
    for path in $candidate; do
      if [[ -x "$path" ]]; then
        echo "$path"
        return 0
      fi
    done
  done
  return 1
}

if [[ -z "$DB_NAME" || -z "$DB_USER" ]]; then
  log "ERROR: set DB_NAME and DB_USER in $ENV_FILE"
  exit 1
fi

if ! MYSQLDUMP="$(resolve_mysqldump)"; then
  log "ERROR: mysqldump not found. Install client package, e.g.:"
  log "  dnf install -y mysql  OR  yum install -y mysql  OR  dnf install -y mariadb"
  log "Or set MYSQLDUMP_BIN=/full/path/to/mysqldump in $ENV_FILE"
  exit 1
fi

log "Backup start → $DEST"
log "Using mysqldump: $MYSQLDUMP"

# --- Database ---
DUMP="${DEST}/db-${DB_NAME}.sql.gz"
export MYSQL_PWD="$DB_PASS"
"$MYSQLDUMP" \
  --host="$DB_HOST" \
  --user="$DB_USER" \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  --default-character-set=utf8mb4 \
  "$DB_NAME" | gzip -c > "$DUMP"
unset MYSQL_PWD
log "DB dump: $DUMP ($(du -h "$DUMP" | awk '{print $1}'))"

# --- Project code zip (exclude bulky / sensitive runtime paths) ---
CODE_ZIP="${DEST}/project-code.zip"
(
  cd "$(dirname "$SITE_ROOT")"
  BASE="$(basename "$SITE_ROOT")"
  zip -r -q "$CODE_ZIP" "$BASE" \
    -x "${BASE}/moodledata/*" \
    -x "${BASE}/moodledata_stage/*" \
    -x "${BASE}/backups/*" \
    -x "${BASE}/local_dev_logs/*" \
    -x "${BASE}/.git/*" \
    -x "${BASE}/node_modules/*" \
    -x "${BASE}/*/node_modules/*" \
    -x "${BASE}/vendor/*" \
    -x "*.log"
)
log "Code zip: $CODE_ZIP ($(du -h "$CODE_ZIP" | awk '{print $1}'))"

# --- Optional moodledata (large; enable only if disk allows) ---
if [[ "$INCLUDE_MOODLEDATA" == "1" && -n "$MOODLEDATA" && -d "$MOODLEDATA" ]]; then
  DATA_ZIP="${DEST}/moodledata.zip"
  (
    cd "$(dirname "$MOODLEDATA")"
    zip -r -q "$DATA_ZIP" "$(basename "$MOODLEDATA")" \
      -x "*/cache/*" \
      -x "*/localcache/*" \
      -x "*/temp/*" \
      -x "*/sessions/*" \
      -x "*/trashdir/*"
  )
  log "Data zip: $DATA_ZIP ($(du -h "$DATA_ZIP" | awk '{print $1}'))"
fi

# --- Retention ---
find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -mtime "+${KEEP_DAYS}" -exec rm -rf {} +
log "Retention: removed folders older than ${KEEP_DAYS} days under $BACKUP_ROOT"
log "Backup done."
