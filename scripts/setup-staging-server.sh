#!/usr/bin/env bash
# ============================================================
# Theja — Staging Server Setup
# File: scripts/setup-staging-server.sh
#
# Target: Ubuntu 24.04 LTS (ARM64 o x86_64) su AWS EC2
# Esegui come root o con sudo: bash setup-staging-server.sh
#
# Cosa installa:
#   - Nginx 1.24+
#   - PHP 8.3 + estensioni (FPM, PgSQL, Redis, ecc.)
#   - Composer 2
#   - Node.js 20 LTS
#   - pnpm 10
#   - Redis CLI (redis-tools)
#   - PM2 (process manager per Next.js)
#   - Certbot (Let's Encrypt)
#   - Supervisord (queue workers Laravel)
#
# Uso:
#   1. Lancia questo script su un EC2 Ubuntu 24.04 fresh
#   2. Copia .env: cp /var/www/theja/infra/staging.env.example /var/www/theja/apps/api/.env
#   3. Edita .env con i valori reali AWS
#   4. Esegui: php artisan key:generate && php artisan migrate --force
#   5. Configura Nginx: ln -s /etc/nginx/sites-available/theja-api /etc/nginx/sites-enabled/
#   6. Ottieni SSL: certbot --nginx -d api-staging.theja.it
# ============================================================

set -euo pipefail

# ─── Colori ───────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
log()  { echo -e "${GREEN}[SETUP]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC}  $1"; }
err()  { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

[[ $EUID -ne 0 ]] && err "Eseguire come root: sudo bash $0"

# ─── Variabili configurabili ──────────────────────────────────
DEPLOY_USER="${DEPLOY_USER:-ubuntu}"
APP_DIR="${APP_DIR:-/var/www/theja}"
PHP_VERSION="8.3"
NODE_VERSION="20"
GIT_REPO="${GIT_REPO:-https://github.com/mavhart/theja.git}"
DOMAIN_API="${DOMAIN_API:-api-staging.theja.it}"

log "=== Theja Staging Server Setup ==="
log "Utente deploy: $DEPLOY_USER"
log "Directory app: $APP_DIR"
log "Dominio API:   $DOMAIN_API"
echo ""

# ─── 1. Aggiorna sistema ──────────────────────────────────────
log "1/10 — Aggiornamento sistema"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq
apt-get install -y -qq \
    curl wget git unzip zip \
    build-essential software-properties-common \
    ca-certificates gnupg lsb-release \
    supervisor \
    redis-tools \
    acl

# ─── 2. Nginx ─────────────────────────────────────────────────
log "2/10 — Installazione Nginx"
apt-get install -y -qq nginx
systemctl enable nginx

# ─── 3. PHP 8.3 + estensioni ─────────────────────────────────
log "3/10 — Installazione PHP $PHP_VERSION"
add-apt-repository -y ppa:ondrej/php
apt-get update -qq
apt-get install -y -qq \
    "php${PHP_VERSION}-fpm" \
    "php${PHP_VERSION}-cli" \
    "php${PHP_VERSION}-pgsql" \
    "php${PHP_VERSION}-redis" \
    "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-zip" \
    "php${PHP_VERSION}-bcmath" \
    "php${PHP_VERSION}-intl" \
    "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-imagick" \
    "php${PHP_VERSION}-tokenizer" \
    "php${PHP_VERSION}-fileinfo" \
    "php${PHP_VERSION}-openssl"

# Configura PHP-FPM per produzione
PHP_FPM_CONF="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
sed -i "s/^user = www-data/user = $DEPLOY_USER/"           "$PHP_FPM_CONF"
sed -i "s/^group = www-data/group = $DEPLOY_USER/"         "$PHP_FPM_CONF"
sed -i "s/^listen.owner = www-data/listen.owner = $DEPLOY_USER/" "$PHP_FPM_CONF"
sed -i "s/^listen.group = www-data/listen.group = $DEPLOY_USER/" "$PHP_FPM_CONF"
sed -i 's/^pm = dynamic/pm = dynamic/'                     "$PHP_FPM_CONF"
sed -i 's/^pm.max_children = .*/pm.max_children = 20/'     "$PHP_FPM_CONF"
sed -i 's/^pm.start_servers = .*/pm.start_servers = 4/'    "$PHP_FPM_CONF"
sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 2/' "$PHP_FPM_CONF"
sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 8/' "$PHP_FPM_CONF"

# PHP production settings
PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 32M/'   "$PHP_INI"
sed -i 's/^post_max_size = .*/post_max_size = 32M/'               "$PHP_INI"
sed -i 's/^memory_limit = .*/memory_limit = 256M/'                "$PHP_INI"
sed -i 's/^max_execution_time = .*/max_execution_time = 120/'     "$PHP_INI"
sed -i 's/^expose_php = On/expose_php = Off/'                     "$PHP_INI"

systemctl enable "php${PHP_VERSION}-fpm"

# ─── 4. Composer 2 ───────────────────────────────────────────
log "4/10 — Installazione Composer 2"
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
composer --version

# ─── 5. Node.js 20 LTS ───────────────────────────────────────
log "5/10 — Installazione Node.js $NODE_VERSION"
curl -fsSL "https://deb.nodesource.com/setup_${NODE_VERSION}.x" | bash -
apt-get install -y -qq nodejs
node --version

# ─── 6. pnpm ─────────────────────────────────────────────────
log "6/10 — Installazione pnpm"
npm install -g pnpm@10
pnpm --version

# ─── 7. PM2 (process manager Next.js) ────────────────────────
log "7/10 — Installazione PM2"
npm install -g pm2
pm2 startup systemd -u "$DEPLOY_USER" --hp "/home/$DEPLOY_USER" | tail -1 | bash || true
systemctl enable pm2-"$DEPLOY_USER" 2>/dev/null || true

# ─── 8. Certbot (Let's Encrypt) ──────────────────────────────
log "8/10 — Installazione Certbot"
apt-get install -y -qq certbot python3-certbot-nginx
# Rinnovo automatico già configurato da certbot tramite systemd timer

# ─── 9. Directory applicazione ───────────────────────────────
log "9/10 — Setup directory $APP_DIR"
mkdir -p "$APP_DIR"
chown -R "$DEPLOY_USER:$DEPLOY_USER" "$APP_DIR"

# Clone repository (se non esiste già)
if [[ ! -d "$APP_DIR/.git" ]]; then
    log "  → Clone repository"
    sudo -u "$DEPLOY_USER" git clone "$GIT_REPO" "$APP_DIR"
else
    log "  → Repository già presente, skip clone"
fi

# Crea cartelle necessarie a Laravel con permessi corretti
mkdir -p "$APP_DIR/apps/api/storage/"{logs,framework/{cache,sessions,views}}
mkdir -p "$APP_DIR/apps/api/bootstrap/cache"
chown -R "$DEPLOY_USER:www-data" "$APP_DIR/apps/api/storage"
chown -R "$DEPLOY_USER:www-data" "$APP_DIR/apps/api/bootstrap/cache"
chmod -R 775 "$APP_DIR/apps/api/storage"
chmod -R 775 "$APP_DIR/apps/api/bootstrap/cache"

# ─── 10. Supervisord — Queue Worker Laravel ──────────────────
log "10/10 — Configurazione Supervisord (queue worker)"
cat > /etc/supervisor/conf.d/theja-worker.conf << EOF
[program:theja-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${APP_DIR}/apps/api/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=${DEPLOY_USER}
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/theja-worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600
EOF

supervisorctl reread
supervisorctl update

# ─── Configura Nginx ─────────────────────────────────────────
log "Copia configurazione Nginx"
cp "$APP_DIR/infra/nginx/staging-api.conf" /etc/nginx/sites-available/theja-api
ln -sf /etc/nginx/sites-available/theja-api /etc/nginx/sites-enabled/theja-api

# Rimuove default site
rm -f /etc/nginx/sites-enabled/default

nginx -t && systemctl reload nginx

# ─── Configura cron per Laravel Scheduler ────────────────────
log "Configura cron per Laravel Scheduler"
CRON_JOB="* * * * * $DEPLOY_USER php ${APP_DIR}/apps/api/artisan schedule:run >> /dev/null 2>&1"
if ! grep -q "artisan schedule:run" /etc/crontab; then
    echo "$CRON_JOB" >> /etc/crontab
    log "  → Cron aggiunto a /etc/crontab"
fi

# ─── Riepilogo ───────────────────────────────────────────────
echo ""
log "=== SETUP COMPLETATO ==="
echo ""
echo -e "  ${GREEN}PHP:${NC}      $(php -r 'echo PHP_VERSION;')"
echo -e "  ${GREEN}Nginx:${NC}    $(nginx -v 2>&1 | grep -oP '(?<=nginx/)[0-9.]+')"
echo -e "  ${GREEN}Node:${NC}     $(node --version)"
echo -e "  ${GREEN}pnpm:${NC}     $(pnpm --version)"
echo -e "  ${GREEN}Composer:${NC} $(composer --version --no-ansi | head -1)"
echo -e "  ${GREEN}PM2:${NC}      $(pm2 --version)"
echo ""
echo -e "${YELLOW}PROSSIMI PASSI MANUALI:${NC}"
echo "  1. Copia e configura .env:"
echo "     cp $APP_DIR/infra/staging.env.example $APP_DIR/apps/api/.env"
echo "     nano $APP_DIR/apps/api/.env"
echo "  2. Genera APP_KEY:"
echo "     cd $APP_DIR/apps/api && php artisan key:generate"
echo "  3. Installa dipendenze PHP:"
echo "     cd $APP_DIR/apps/api && composer install --no-dev --optimize-autoloader"
echo "  4. Esegui migration:"
echo "     php artisan migrate --force"
echo "  5. Ottieni certificato SSL:"
echo "     certbot --nginx -d $DOMAIN_API"
echo "  6. Installa dipendenze Node e build web:"
echo "     cd $APP_DIR && pnpm install && cd apps/web && pnpm build"
echo "  7. Avvia Next.js con PM2:"
echo "     pm2 start 'node_modules/.bin/next start -p 3000' --name theja-web --cwd $APP_DIR/apps/web"
echo "     pm2 save"
echo ""
echo -e "${GREEN}Server staging pronto!${NC}"
