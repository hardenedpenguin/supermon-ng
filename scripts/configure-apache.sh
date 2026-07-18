#!/bin/bash
# Apache setup for supermon-ng, invoked by the Debian package postinst
# (and runnable by hand for reconfiguration).
#
# Environment:
#   APP_DIR              Application root (default: /var/www/html/supermon-ng)
#   SKIP_APACHE          true = only generate template + app-base-path, no site install
#   DISABLE_DEFAULT_SITES  true = a2dissite 000-default and default-ssl (default: true)
#   OVERWRITE_SITE       true = replace existing sites-available/supermon-ng.conf
#   ALLOW_APT_INSTALL    true = apt-get install apache2/ssl-cert if missing
#   CONFIGURE_LOG_ACLS   true = setfacl on Apache/Asterisk logs (default: true)
#   QUIET                true = less output

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/html/supermon-ng}"
SKIP_APACHE="${SKIP_APACHE:-false}"
DISABLE_DEFAULT_SITES="${DISABLE_DEFAULT_SITES:-true}"
OVERWRITE_SITE="${OVERWRITE_SITE:-false}"
ALLOW_APT_INSTALL="${ALLOW_APT_INSTALL:-false}"
CONFIGURE_LOG_ACLS="${CONFIGURE_LOG_ACLS:-true}"
QUIET="${QUIET:-false}"

APACHE_SITE_FILE="/etc/apache2/sites-available/supermon-ng.conf"
APACHE_TEMPLATE="${APP_DIR}/apache-config-template.conf"
WWW_GROUP="${WWW_GROUP:-www-data}"
APACHE_LOG_DIR="${APACHE_LOG_DIR:-/var/log/apache2}"
ASTERISK_LOG_DIR="${ASTERISK_LOG_DIR:-/var/log/asterisk}"

log() {
    [ "$QUIET" = true ] && return 0
    echo "$@"
}

warn() {
    echo "$@" >&2
}

load_env() {
    APP_BASE_PATH="${APP_BASE_PATH:-/supermon-ng}"
    SUPERMON_SERVER_NAME="${SUPERMON_SERVER_NAME:-}"
    SSL_CERT_NAME="${SSL_CERT_NAME:-}"

    if [ -f "$APP_DIR/.env" ]; then
        # shellcheck disable=SC1091
        set -a
        . "$APP_DIR/.env" 2>/dev/null || true
        set +a
        APP_BASE_PATH="${APP_BASE_PATH:-/supermon-ng}"
        SUPERMON_SERVER_NAME="${SUPERMON_SERVER_NAME:-$(grep -E '^SUPERMON_SERVER_NAME=' "$APP_DIR/.env" 2>/dev/null | cut -d= -f2- | tr -d '"' || true)}"
        SSL_CERT_NAME="${SSL_CERT_NAME:-$(grep -E '^SSL_CERT_NAME=' "$APP_DIR/.env" 2>/dev/null | cut -d= -f2- | tr -d '"' || true)}"
    fi

    export APP_DIR APP_BASE_PATH SUPERMON_SERVER_NAME SSL_CERT_NAME
}

configure_log_acls() {
    [ "$CONFIGURE_LOG_ACLS" = true ] || return 0
    command -v setfacl >/dev/null 2>&1 || {
        warn "configure-apache: setfacl not available; skipping log ACL setup"
        return 0
    }

    if [ -d "$APACHE_LOG_DIR" ]; then
        setfacl -R -m "g:${WWW_GROUP}:rX" "$APACHE_LOG_DIR" 2>/dev/null || warn "configure-apache: failed to set Apache log ACLs"
        setfacl -R -d -m "g:${WWW_GROUP}:rX" "$APACHE_LOG_DIR" 2>/dev/null || true
    fi

    if [ -d "$ASTERISK_LOG_DIR" ]; then
        setfacl -R -m "g:${WWW_GROUP}:rX" "$ASTERISK_LOG_DIR" 2>/dev/null || warn "configure-apache: failed to set Asterisk log ACLs"
        setfacl -R -d -m "g:${WWW_GROUP}:rX" "$ASTERISK_LOG_DIR" 2>/dev/null || true
    fi
}

ensure_apache_packages() {
    [ "$SKIP_APACHE" = true ] && return 0
    [ "$ALLOW_APT_INSTALL" = true ] || return 0

    if ! command -v apache2 >/dev/null 2>&1; then
        log "Installing Apache..."
        apt-get install -y apache2 ssl-cert
    elif [ ! -f /etc/ssl/certs/ssl-cert-snakeoil.pem ]; then
        log "Installing ssl-cert..."
        apt-get install -y ssl-cert
    fi
}

generate_template_and_paths() {
    if [ ! -x "$APP_DIR/scripts/generate-apache-template.sh" ]; then
        warn "configure-apache: missing $APP_DIR/scripts/generate-apache-template.sh"
        return 1
    fi

    log "Generating Apache template (APP_BASE_PATH=${APP_BASE_PATH})..."
    bash "$APP_DIR/scripts/generate-apache-template.sh" "$APACHE_TEMPLATE"

    if [ -x "$APP_DIR/scripts/configure-app-base-path.sh" ]; then
        bash "$APP_DIR/scripts/configure-app-base-path.sh" "$APP_DIR"
    fi
}

enable_apache_modules() {
    a2enmod -q proxy proxy_http proxy_wstunnel rewrite headers substitute ssl deflate expires 2>/dev/null || {
        warn "configure-apache: some Apache modules could not be enabled"
    }
}

install_site_config() {
    if [ ! -f "$APACHE_TEMPLATE" ]; then
        warn "configure-apache: template missing at $APACHE_TEMPLATE"
        return 1
    fi

    if [ -f "$APACHE_SITE_FILE" ] && [ "$OVERWRITE_SITE" != true ]; then
        log "Leaving existing Apache site: $APACHE_SITE_FILE"
        return 0
    fi

    log "Installing Apache site configuration..."
    cp "$APACHE_TEMPLATE" "$APACHE_SITE_FILE"
}

enable_supermon_site() {
    if [ "$DISABLE_DEFAULT_SITES" = true ]; then
        a2dissite -q 000-default 2>/dev/null || true
        a2dissite -q default-ssl 2>/dev/null || true
    fi

    a2ensite -q supermon-ng 2>/dev/null || {
        warn "configure-apache: failed to enable Apache site supermon-ng"
        return 1
    }
}

reload_apache() {
    if ! command -v apache2ctl >/dev/null 2>&1; then
        return 0
    fi

    if apache2ctl configtest >/dev/null 2>&1; then
        log "Reloading Apache..."
        systemctl reload apache2 2>/dev/null || systemctl restart apache2 2>/dev/null || {
            warn "configure-apache: Apache reload failed"
            return 1
        }
    else
        warn "configure-apache: apache2ctl configtest failed — fix $APACHE_SITE_FILE manually"
        apache2ctl configtest 2>&1 || true
        return 1
    fi
}

configure_apache_site() {
    [ "$SKIP_APACHE" = true ] && {
        log "Skipping Apache site configuration (SKIP_APACHE=true)"
        return 0
    }

    if ! command -v apache2 >/dev/null 2>&1; then
        warn "configure-apache: apache2 not installed; site configuration skipped"
        return 0
    fi

    enable_apache_modules
    install_site_config
    enable_supermon_site
    reload_apache
}

remove_apache_site() {
    if [ -d /etc/apache2/sites-available ] && [ -f "$APACHE_SITE_FILE" ]; then
        a2dissite -q supermon-ng 2>/dev/null || true
        rm -f "$APACHE_SITE_FILE"
        if command -v apache2ctl >/dev/null 2>&1; then
            apache2ctl configtest >/dev/null 2>&1 && systemctl reload apache2 2>/dev/null || true
        fi
    fi
}

main() {
    case "${1:-configure}" in
        configure)
            load_env
            ensure_apache_packages
            configure_log_acls
            generate_template_and_paths
            configure_apache_site
            ;;
        template-only)
            load_env
            generate_template_and_paths
            ;;
        remove)
            remove_apache_site
            ;;
        *)
            echo "Usage: configure-apache.sh [configure|template-only|remove]" >&2
            exit 1
            ;;
    esac
}

main "$@"
