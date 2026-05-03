#!/bin/bash
# =============================================================================
# Bulk Deploy — bootstrap new servers or update existing ones in parallel.
#
# Usage:
#   bash bulk-deploy.sh [options] [domain1 domain2 ...]
#
# Options:
#   --dry-run              Show pending commits + migrations per server, no changes
#   --concurrency=N        Max parallel deployments (default: all at once)
#   --skip-unreachable     Continue even if some servers fail the SSH pre-check
#
# Examples:
#   bash bulk-deploy.sh                              # deploy all servers
#   bash bulk-deploy.sh school1.com school2.com      # deploy specific servers
#   bash bulk-deploy.sh --dry-run                    # preview only
#   bash bulk-deploy.sh --concurrency=3              # max 3 at a time
#
# Requires:
#   - sshpass  (apt install sshpass / brew install hudochenkov/sshpass/sshpass)
#   - servers.txt in this directory (see servers.txt.example)
#   - bulk-deploy.env (optional) for Telegram credentials
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVERS_FILE="$SCRIPT_DIR/servers.txt"
SCHOOL_CONFIGS_DIR="$SCRIPT_DIR/school-configs"
LOGS_DIR="$SCRIPT_DIR/logs"
HISTORY_LOG="$LOGS_DIR/deploy-history.log"
ENV_FILE="$SCRIPT_DIR/bulk-deploy.env"
RUN_TS="$(date +%Y%m%d-%H%M%S)"
RUN_LABEL="$(date '+%Y-%m-%d %H:%M')"

# ── Defaults ──────────────────────────────────────────────────────────────────
DRY_RUN=false
CONCURRENCY=0        # 0 = unlimited
SKIP_UNREACHABLE=false
BUILD_FRONTEND=false
TARGET_DOMAINS=()    # empty = all

# ── Parse arguments ───────────────────────────────────────────────────────────
for arg in "$@"; do
  case "$arg" in
    --dry-run)           DRY_RUN=true ;;
    --concurrency=*)     CONCURRENCY="${arg#*=}" ;;
    --skip-unreachable)  SKIP_UNREACHABLE=true ;;
    --build-frontend)    BUILD_FRONTEND=true ;;
    --*)                 echo "Unknown flag: $arg"; exit 1 ;;
    *)                   TARGET_DOMAINS+=("$arg") ;;
  esac
done

# ── Load Telegram config (optional) ──────────────────────────────────────────
TELEGRAM_BOT_TOKEN=""
TELEGRAM_CHAT_ID=""
if [ -f "$ENV_FILE" ]; then
  env_get_local() {
    awk -F= -v k="$1" '$1==k{sub(/^[^=]*=/,"");sub(/^"/,"");sub(/"$/,"");print;exit}' "$ENV_FILE"
  }
  TELEGRAM_BOT_TOKEN="$(env_get_local TELEGRAM_BOT_TOKEN)"
  TELEGRAM_CHAT_ID="$(env_get_local TELEGRAM_CHAT_ID)"
fi

# ── Helpers ───────────────────────────────────────────────────────────────────
log()  { echo "  $*"; }
info() { echo "[$(date '+%H:%M:%S')] $*"; }

ssh_cmd() {
  local user="$1" pass="$2" host="$3"
  shift 3
  sshpass -p "$pass" ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=no -tt "$user@$host" "$@" 2>&1
}

scp_cmd() {
  local pass="$1" src="$2" dst="$3"
  sshpass -p "$pass" scp -o StrictHostKeyChecking=no -o ConnectTimeout=10 "$src" "$dst" 2>&1
}

send_telegram() {
  local msg="$1"
  [ -z "$TELEGRAM_BOT_TOKEN" ] || [ -z "$TELEGRAM_CHAT_ID" ] && return 0
  curl -s -X POST "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage" \
    -d chat_id="$TELEGRAM_CHAT_ID" \
    -d text="$msg" \
    -d parse_mode="HTML" > /dev/null 2>&1 || true
}

# ── Preflight: sshpass ────────────────────────────────────────────────────────
if ! command -v sshpass &>/dev/null; then
  echo "ERROR: sshpass is not installed."
  echo "  Ubuntu/Debian : sudo apt install sshpass"
  echo "  macOS         : brew install hudochenkov/sshpass/sshpass"
  exit 1
fi

# ── Preflight: servers.txt ────────────────────────────────────────────────────
if [ ! -f "$SERVERS_FILE" ]; then
  echo "ERROR: servers.txt not found at $SERVERS_FILE"
  echo "  Copy servers.txt.example to servers.txt and fill in your server details."
  exit 1
fi

mkdir -p "$LOGS_DIR"

# ── Parse servers.txt ─────────────────────────────────────────────────────────
# Returns array of "MODE DOMAIN USER PASS PATH REPO" lines (# and blank lines skipped)
parse_servers() {
  local -n _out=$1
  while IFS= read -r line; do
    [[ "$line" =~ ^[[:space:]]*# ]] && continue
    [[ -z "${line// }" ]] && continue
    read -r mode domain user pass path repo <<< "$line" 2>/dev/null || true
    [ -z "$mode" ] || [ -z "$domain" ] && continue

    # If TARGET_DOMAINS is set, filter to only those
    if [ ${#TARGET_DOMAINS[@]} -gt 0 ]; then
      local found=false
      for t in "${TARGET_DOMAINS[@]}"; do
        [ "$t" = "$domain" ] && found=true && break
      done
      $found || continue
    fi

    _out+=("$mode|$domain|$user|$pass|$path|$repo")
  done < "$SERVERS_FILE"
}

declare -a SERVERS=()
parse_servers SERVERS

if [ ${#SERVERS[@]} -eq 0 ]; then
  echo "No servers to deploy (check servers.txt or your domain filter)."
  exit 0
fi

# ── Phase 0: SSH connectivity pre-check ──────────────────────────────────────
info "Checking SSH connectivity for ${#SERVERS[@]} server(s)..."
declare -a UNREACHABLE=()
for entry in "${SERVERS[@]}"; do
  IFS='|' read -r mode domain user pass path repo <<< "$entry"
  if sshpass -p "$pass" ssh -o StrictHostKeyChecking=no -o ConnectTimeout=8 \
       -o BatchMode=no "$user@$domain" "echo ok" &>/dev/null; then
    echo "  [+] $domain — reachable"
  else
    echo "  [-] $domain — UNREACHABLE"
    UNREACHABLE+=("$domain")
  fi
done

if [ ${#UNREACHABLE[@]} -gt 0 ]; then
  echo ""
  echo "WARNING: ${#UNREACHABLE[@]} server(s) unreachable: ${UNREACHABLE[*]}"
  if ! $SKIP_UNREACHABLE; then
    echo "  Aborting. Use --skip-unreachable to deploy to reachable servers only."
    exit 1
  fi
  echo "  --skip-unreachable set — continuing with reachable servers."
  # Filter out unreachable servers
  declare -a FILTERED=()
  for entry in "${SERVERS[@]}"; do
    IFS='|' read -r mode domain user pass path repo <<< "$entry"
    local_skip=false
    for u in "${UNREACHABLE[@]}"; do [ "$u" = "$domain" ] && local_skip=true && break; done
    $local_skip || FILTERED+=("$entry")
  done
  SERVERS=("${FILTERED[@]}")
fi

echo ""

# ── Dry-run mode ──────────────────────────────────────────────────────────────
if $DRY_RUN; then
  info "DRY RUN — no changes will be made."
  echo ""
  for entry in "${SERVERS[@]}"; do
    IFS='|' read -r mode domain user pass path repo <<< "$entry"
    echo "=== $domain ($mode) ==="
    if [ "$mode" = "deploy" ]; then
      ssh_cmd "$user" "$pass" "$domain" \
        "cd '$path' && git fetch origin 2>/dev/null; \
         echo '--- Pending commits ---'; \
         git log HEAD..origin/main --oneline 2>/dev/null || echo '(none)'; \
         echo '--- Pending migrations ---'; \
         php artisan migrate:status 2>/dev/null | grep -E 'Pending|No' || echo '(none)'"
    else
      echo "  Mode: bootstrap — will clone $repo into $path"
      if [ -f "$SCHOOL_CONFIGS_DIR/$domain.xlsx" ]; then
        echo "  Config: school-configs/$domain.xlsx found"
      else
        echo "  Config: WARNING — school-configs/$domain.xlsx NOT found"
      fi
    fi
    echo ""
  done
  exit 0
fi

# ── Per-server deploy function ────────────────────────────────────────────────
declare -A RESULTS=()
declare -A DURATIONS=()

deploy_server() {
  local mode="$1" domain="$2" user="$3" pass="$4" path="$5" repo="$6"
  local log_file="$LOGS_DIR/${domain}-${RUN_TS}.log"
  local start_ts="$(date +%s)"
  local status="SUCCESS"

  {
    echo "=== Deploy log: $domain | mode=$mode | $RUN_LABEL ==="
    echo ""

    if [ "$mode" = "bootstrap" ]; then
      # ── Validate xlsx config ───────────────────────────────────────────────
      local xlsx="$SCHOOL_CONFIGS_DIR/$domain.xlsx"
      if [ ! -f "$xlsx" ]; then
        echo "ERROR: school-configs/$domain.xlsx not found — skipping bootstrap."
        echo "  Create it by copying school-setup.example.xlsx and filling in the school details."
        exit 2
      fi

      # ── Clone repo if not present ─────────────────────────────────────────
      echo "--- Checking remote directory ---"
      local git_exists
      git_exists=$(sshpass -p "$pass" ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 \
        -o BatchMode=no "$user@$domain" "[ -d '$path/.git' ] && echo yes || echo no" 2>&1)

      if echo "$git_exists" | grep -q "^no"; then
        echo "--- Cloning $repo into $path ---"
        # Directory may exist but be empty (CloudPanel creates it on site add) — clone into it
        ssh_cmd "$user" "$pass" "$domain" \
          "mkdir -p '$path' && git clone '$repo' '$path' 2>&1 || (rm -rf '$path' && git clone '$repo' '$path')"
      else
        echo "--- Git repo already present at $path — pulling latest code ---"
        ssh_cmd "$user" "$pass" "$domain" "cd '$path' && git pull origin main 2>&1"
      fi

      # ── Upload school-setup.xlsx ──────────────────────────────────────────
      echo "--- Uploading school-setup.xlsx ---"
      scp_cmd "$pass" "$xlsx" "$user@$domain:$path/school-setup.xlsx"

      # ── Run bootstrap.sh ──────────────────────────────────────────────────
      echo "--- Running bootstrap.sh ---"
      ssh_cmd "$user" "$pass" "$domain" "cd '$path' && bash bootstrap.sh"

    else
      # ── PHP syntax pre-check ─────────────────────────────────────────────
      echo "--- PHP syntax pre-check ---"
      local syntax_errors
      syntax_errors=$(ssh_cmd "$user" "$pass" "$domain" \
        "cd '$path' && git fetch origin 2>/dev/null; \
         git diff HEAD..origin/main --name-only 2>/dev/null \
           | grep '\.php$' \
           | xargs -I{} php -l '{}' 2>&1 \
           | grep -v '^No syntax errors' || true")

      if echo "$syntax_errors" | grep -qi "parse error\|syntax error"; then
        echo "ERROR: PHP syntax errors detected in incoming changes:"
        echo "$syntax_errors"
        echo "Aborting deploy for $domain — fix errors and push again."
        exit 3
      fi
      echo "  Syntax OK"

      # ── Ensure git remote points to the correct repo ─────────────────────
      # Handles cases where the server was previously cloned from a different
      # organisation / fork. Always sync the remote to what servers.txt says.
      echo "--- Ensuring git remote is correct ---"
      ssh_cmd "$user" "$pass" "$domain" \
        "cd '$path' && git remote set-url origin '$repo' && echo '  remote OK: $repo'"

      # ── Run deploy.sh ─────────────────────────────────────────────────────
      echo "--- Running deploy.sh ---"
      local deploy_flags=""
      $BUILD_FRONTEND && deploy_flags="--build-frontend"
      ssh_cmd "$user" "$pass" "$domain" "cd '$path' && bash deploy.sh $deploy_flags"

      # ── Backup cleanup (>7 days) ──────────────────────────────────────────
      echo "--- Cleaning up old backups (>7 days) ---"
      ssh_cmd "$user" "$pass" "$domain" \
        "find '$path/storage/backups' -name '*.sql.gz' -mtime +7 -delete 2>/dev/null || true"
      echo "  Cleanup done"
    fi

    # ── Health check ─────────────────────────────────────────────────────────
    echo "--- Health check ---"
    local http_code
    http_code=$(curl -s -o /dev/null -w "%{http_code}" \
      --max-time 15 "https://$domain/up" 2>/dev/null || echo "000")

    if [ "$http_code" != "200" ]; then
      echo "HEALTH CHECK FAILED — HTTP $http_code from https://$domain/up"

      if [ "$mode" = "deploy" ]; then
        echo "--- Initiating rollback ---"
        ssh_cmd "$user" "$pass" "$domain" "bash -s" << 'ROLLBACK'
          set -e
          php artisan down || true
          git reset --hard HEAD~1
          LATEST=$(ls -t storage/backups/*.sql.gz 2>/dev/null | head -1)
          if [ -n "$LATEST" ]; then
            echo "Restoring backup: $LATEST"
            env_get() { awk -F= -v k="$1" '$1==k{sub(/^[^=]*=/,"");sub(/^"/,"");sub(/"$/,"");print;exit}' .env; }
            DB_HOST=$(env_get DB_HOST)
            DB_PORT=$(env_get DB_PORT)
            DB_DATABASE=$(env_get DB_DATABASE)
            DB_USERNAME=$(env_get DB_USERNAME)
            DB_PASSWORD=$(env_get DB_PASSWORD)
            gunzip < "$LATEST" | mysql -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"
            php artisan migrate:rollback --force || true
          else
            echo "No backup found — DB not restored. Manual intervention required."
          fi
          php artisan config:cache
          php artisan up
          echo "Rollback complete"
ROLLBACK
        exit 4
      fi
      exit 5
    fi

    echo "  Health check passed (HTTP 200)"

    # ── Post-bootstrap: verify cron + worker were set up ────────────────────
    if [ "$mode" = "bootstrap" ]; then
      echo "--- Verifying cron + worker ---"

      CRON_COUNT=$(ssh_cmd "$user" "$pass" "$domain" \
        "crontab -l 2>/dev/null | grep -c 'artisan schedule:run' || echo 0" \
        | grep -o '[0-9]*' | head -1)

      WORKER_COUNT=$(ssh_cmd "$user" "$pass" "$domain" \
        "pgrep -fc 'artisan queue:work' 2>/dev/null || echo 0" \
        | grep -o '[0-9]*' | head -1)

      if [ "${CRON_COUNT:-0}" -gt 0 ]; then
        echo "  ✓ Cron:   configured"
      else
        echo "  ✗ Cron:   NOT found — SSH in and run: crontab -e"
      fi

      if [ "${WORKER_COUNT:-0}" -gt 0 ]; then
        echo "  ✓ Worker: running (${WORKER_COUNT} process)"
      else
        echo "  ✗ Worker: NOT running — check bootstrap log for setup instructions"
      fi
    fi

    echo ""
    echo "=== DONE ==="

  } > "$log_file" 2>&1
  local exit_code=$?
  local end_ts="$(date +%s)"
  local elapsed=$(( end_ts - start_ts ))

  # Write result back via temp file (subshell can't write to parent associative array)
  if [ $exit_code -eq 4 ]; then
    echo "ROLLED_BACK|$elapsed" > "$LOGS_DIR/.result_${domain}"
  elif [ $exit_code -ne 0 ]; then
    echo "FAILED|$elapsed"     > "$LOGS_DIR/.result_${domain}"
  else
    echo "SUCCESS|$elapsed"    > "$LOGS_DIR/.result_${domain}"
  fi
}

# ── Parallel execution with concurrency limit ─────────────────────────────────
info "Starting deployment of ${#SERVERS[@]} server(s)..."
$DRY_RUN && echo "(dry-run mode)"
echo ""

declare -a PIDS=()
declare -a DOMAINS_ORDERED=()

for entry in "${SERVERS[@]}"; do
  IFS='|' read -r mode domain user pass path repo <<< "$entry"
  DOMAINS_ORDERED+=("$domain")

  deploy_server "$mode" "$domain" "$user" "$pass" "$path" "$repo" &
  PIDS+=($!)
  info "  -> $domain ($mode) started [PID $!]"

  # Concurrency throttle
  if [ "$CONCURRENCY" -gt 0 ] && [ "${#PIDS[@]}" -ge "$CONCURRENCY" ]; then
    wait "${PIDS[0]}"
    PIDS=("${PIDS[@]:1}")
  fi
done

# Wait for remaining
for pid in "${PIDS[@]}"; do
  wait "$pid" || true
done

# ── Collect results ───────────────────────────────────────────────────────────
echo ""
echo "================================================================"
echo "  Bulk Deploy Summary — $RUN_LABEL"
echo "================================================================"

TOTAL=0; SUCCEEDED=0; ROLLED_BACK=0; FAILED=0
TELEGRAM_MSG="<b>Bulk Deploy — ${RUN_LABEL}</b>"$'\n'

for domain in "${DOMAINS_ORDERED[@]}"; do
  result_file="$LOGS_DIR/.result_${domain}"
  result="FAILED|0"
  [ -f "$result_file" ] && result="$(cat "$result_file")"
  rm -f "$result_file"

  IFS='|' read -r status elapsed <<< "$result"
  log_file="$LOGS_DIR/${domain}-${RUN_TS}.log"

  # Format duration
  if [ "$elapsed" -ge 60 ]; then
    dur="$((elapsed/60))m $((elapsed%60))s"
  else
    dur="${elapsed}s"
  fi

  TOTAL=$(( TOTAL + 1 ))
  case "$status" in
    SUCCESS)
      SUCCEEDED=$(( SUCCEEDED + 1 ))
      echo "  OK   $domain  ($dur)  -> $log_file"
      TELEGRAM_MSG+=$'\n'"✅ ${domain} — ${dur}"
      ;;
    ROLLED_BACK)
      ROLLED_BACK=$(( ROLLED_BACK + 1 ))
      echo "  BACK $domain  ($dur)  -> $log_file  [rolled back]"
      TELEGRAM_MSG+=$'\n'"⏪ ${domain} — rolled back  (${dur})"
      ;;
    *)
      FAILED=$(( FAILED + 1 ))
      echo "  FAIL $domain  ($dur)  -> $log_file"
      TELEGRAM_MSG+=$'\n'"❌ ${domain} — FAILED  (${dur})"
      ;;
  esac

  # Append to deploy history log
  echo "$RUN_LABEL  $status  $domain  ${dur}" >> "$HISTORY_LOG"
done

echo "================================================================"
echo "  Total: $TOTAL | Succeeded: $SUCCEEDED | Rolled back: $ROLLED_BACK | Failed: $FAILED"
echo "================================================================"

TELEGRAM_MSG+=$'\n\n'"Total: ${TOTAL} | OK: ${SUCCEEDED} | Rolled back: ${ROLLED_BACK} | Failed: ${FAILED}"

# ── Git tag on full success ───────────────────────────────────────────────────
if [ "$FAILED" -eq 0 ] && [ "$ROLLED_BACK" -eq 0 ]; then
  TAG="deploy-$(date +%Y%m%d-%H%M)"
  git -C "$SCRIPT_DIR" tag "$TAG" 2>/dev/null && info "Git tag created: $TAG" || true
fi

# ── Telegram notification ─────────────────────────────────────────────────────
send_telegram "$TELEGRAM_MSG"

# ── Exit code ─────────────────────────────────────────────────────────────────
[ "$FAILED" -gt 0 ] && exit 1 || exit 0
