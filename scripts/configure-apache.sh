#!/bin/bash
# Apache setup for supermon-ng, invoked by the Debian package postinst
# (and runnable by hand for reconfiguration).
#
# Environment:
#   APP_DIR              Application root (default: /var/www/html/supermon-ng)
#   SKIP_APACHE          true = only generate template + app-base-path, no site install
#   DISABLE_DEFAULT_SITES  root mode only: true = a2dissite 000-default and default-ssl (default: true)
#   OVERWRITE_SITE       true = replace existing sites-available/supermon-ng.conf (root mode),
#                        or retire it even if customized (subdirectory mode, which uses no site;
#                        conf-available/supermon-ng.conf is always regenerated)
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
APACHE_CONF_FILE="/etc/apache2/conf-available/supermon-ng.conf"
APACHE_CONF_TEMPLATE="${APP_DIR}/apache-conf-template.conf"
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
    bash "$APP_DIR/scripts/generate-apache-template.sh" "$APACHE_TEMPLATE" "$APACHE_CONF_TEMPLATE"

    if [ -x "$APP_DIR/scripts/configure-app-base-path.sh" ]; then
        bash "$APP_DIR/scripts/configure-app-base-path.sh" "$APP_DIR"
    fi
}

enable_apache_modules() {
    a2enmod -q proxy proxy_http proxy_wstunnel rewrite headers substitute ssl deflate expires setenvif 2>/dev/null || {
        warn "configure-apache: some Apache modules could not be enabled"
    }
}

# Subdirectory mode with an installed root-mode site whose legacy redirect covers APP_BASE_PATH.
root_redirect_conflicts() {
    [ -f "$APACHE_CONF_TEMPLATE" ] || return 1
    case "${APP_BASE_PATH%/}" in
        /supermon-ng|/supermon-ng/*) ;;
        *) return 1 ;;
    esac
    grep -qE '^[[:space:]]*RedirectMatch[[:space:]].*\^/supermon-ng' "$APACHE_SITE_FILE"
}

# True when the installed site is byte-identical to what root mode would generate now.
root_site_is_generated() {
    local tmp rc=1
    tmp=$(mktemp) || return 1
    if APP_BASE_PATH=/ bash "$APP_DIR/scripts/generate-apache-template.sh" "$tmp" >/dev/null 2>&1 \
        && cmp -s "$tmp" "$APACHE_SITE_FILE"; then
        rc=0
    fi
    rm -f "$tmp"
    return "$rc"
}

# Every directive generate-apache-template.sh has ever emitted in a subdirectory-mode site
# (Header/Include only appeared with ServerName/Let's Encrypt, which are rejected separately).
SUBDIR_SITE_DIRECTIVES='Alias|AllowOverride|CustomLog|Directory|DirectoryIndex|DocumentRoot|ErrorLog|IncludeOptional|Options|ProxyPass|ProxyPassReverse|ProxyPreserveHost|RequestHeader|Require|RewriteCond|RewriteEngine|RewriteRule|SSLCertificateFile|SSLCertificateKeyFile|SSLEngine|VirtualHost'

# True when the installed site is a subdirectory-mode site we generated and nobody customized:
# no ServerName, snakeoil TLS only (default-ssl provides the same), DocumentRoot /var/www/html,
# and no directives beyond what the generator writes.
subdir_site_is_generated() {
    local f="$APACHE_SITE_FILE" unknown
    grep -q '^# Generated by scripts/generate-apache-template.sh' "$f" || return 1
    grep -qE '^# Supermon-NG Apache configuration \(APP_BASE_PATH=/[^)]+\)' "$f" || return 1
    grep -qiE '^[[:space:]]*Server(Name|Alias)[[:space:]]' "$f" && return 1
    grep -iE '^[[:space:]]*SSLCertificate(Key)?File[[:space:]]' "$f" | grep -vE 'ssl-cert-snakeoil\.(pem|key)[[:space:]]*$' >/dev/null && return 1
    grep -iE '^[[:space:]]*DocumentRoot[[:space:]]' "$f" | grep -vE 'DocumentRoot[[:space:]]+"?/var/www/html"?[[:space:]]*$' >/dev/null && return 1
    unknown=$(awk '{ sub(/^[ \t]+/, "") } !/^(#|$)/ { d = $1; sub(/^<\/?/, "", d); sub(/>.*/, "", d); print d }' "$f" \
        | grep -vxiE "$SUBDIR_SITE_DIRECTIVES" || true)
    [ -z "$unknown" ]
}

retire_site() {
    log "$1; disabling and moving it to ${APACHE_SITE_FILE}.bak"
    a2dissite -q supermon-ng 2>/dev/null || true
    mv -f "$APACHE_SITE_FILE" "${APACHE_SITE_FILE}.bak"
}

# Subdirectory mode serves the app from the global conf under the existing (default) sites,
# so our own site is retired when it is ours and unmodified; customized sites are left alone.
retire_subdir_site() {
    [ -f "$APACHE_SITE_FILE" ] || return 0

    if [ "$OVERWRITE_SITE" = true ]; then
        retire_site "Site regeneration requested in subdirectory mode (no site needed)"
    elif root_site_is_generated; then
        retire_site "APP_BASE_PATH=${APP_BASE_PATH} but $APACHE_SITE_FILE is the generated root-mode site"
    elif subdir_site_is_generated; then
        retire_site "$APACHE_SITE_FILE is an unmodified generated site; the default sites serve ${APP_BASE_PATH} via the global conf"
    elif root_redirect_conflicts; then
        # Vhost RedirectMatch is applied before the global Alias and would send the app back to /.
        warn "configure-apache: $APACHE_SITE_FILE is a customized root-mode (APP_BASE_PATH=/) site whose RedirectMatch sends ${APP_BASE_PATH} to /."
        warn "configure-apache: remove that RedirectMatch or the site (OVERWRITE_SITE=true, or 'sudo dpkg-reconfigure supermon-ng' and choose to regenerate), then re-run."
        return 1
    else
        log "Leaving customized Apache site: $APACHE_SITE_FILE (${APP_BASE_PATH} is served by the global conf)"
    fi
}

enabled_site_listens_on() {
    grep -qsE "^[[:space:]]*<VirtualHost[^>]*:$1([[:space:]>])" /etc/apache2/sites-enabled/*
}

# Subdirectory mode needs some vhost on :80/:443; reuse the Debian/ASL defaults only when nothing else is there.
ensure_default_sites() {
    if ! enabled_site_listens_on 80 && [ -f /etc/apache2/sites-available/000-default.conf ]; then
        log "No enabled site on port 80; enabling 000-default"
        a2ensite -q 000-default 2>/dev/null || warn "configure-apache: failed to enable 000-default"
    fi
    if ! enabled_site_listens_on 443 && [ -f /etc/apache2/sites-available/default-ssl.conf ]; then
        log "No enabled site on port 443; enabling default-ssl (snakeoil; use certbot --apache for a real certificate)"
        a2ensite -q default-ssl 2>/dev/null || warn "configure-apache: failed to enable default-ssl"
    fi
}

install_site_config() {
    if [ ! -f "$APACHE_TEMPLATE" ]; then
        warn "configure-apache: template missing at $APACHE_TEMPLATE"
        return 1
    fi

    if [ -f "$APACHE_SITE_FILE" ] && [ "$OVERWRITE_SITE" != true ]; then
        if grep -q 'conf-available/supermon-ng.conf' "$APACHE_SITE_FILE"; then
            # 4.3.6 host-only subdirectory vhost has no app routes; the conf is about to be removed.
            log "APP_BASE_PATH=/ but $APACHE_SITE_FILE is the subdirectory host-only site; replacing (backup: ${APACHE_SITE_FILE}.bak)"
            cp -p "$APACHE_SITE_FILE" "${APACHE_SITE_FILE}.bak"
        else
            log "Leaving existing Apache site: $APACHE_SITE_FILE"
            return 0
        fi
    fi

    log "Installing Apache site configuration..."
    cp "$APACHE_TEMPLATE" "$APACHE_SITE_FILE"
}

# Subdirectory mode: app routes live in a global conf snippet, regenerated from
# .env every time (it holds no admin-editable host/TLS settings).
# Root mode uses a dedicated vhost, so the snippet is removed.
install_app_conf() {
    if [ -f "$APACHE_CONF_TEMPLATE" ]; then
        log "Installing Apache conf: $APACHE_CONF_FILE"
        cp "$APACHE_CONF_TEMPLATE" "$APACHE_CONF_FILE"
        a2enconf -q supermon-ng 2>/dev/null || {
            warn "configure-apache: failed to enable Apache conf supermon-ng"
            return 1
        }
    else
        a2disconf -q supermon-ng 2>/dev/null || true
        rm -f "$APACHE_CONF_FILE"
    fi
}

# ASL3 appliance 3.x installs 000-asl3-appliance sites without a ServerName,
# which become the default host. Root mode needs its own ServerName to be reachable.
warn_root_mode_shadowed() {
    [ -f "$APACHE_CONF_TEMPLATE" ] && return 0
    [ -n "${SUPERMON_SERVER_NAME:-}" ] && return 0
    grep -qiE '^[[:space:]]*ServerName[[:space:]]' "$APACHE_SITE_FILE" 2>/dev/null && return 0
    if ls /etc/apache2/sites-enabled/000-asl3-appliance*.conf >/dev/null 2>&1; then
        warn "configure-apache: APP_BASE_PATH=/ without SUPERMON_SERVER_NAME is shadowed by the ASL3 appliance's default site; set SUPERMON_SERVER_NAME or use APP_BASE_PATH=/supermon-ng"
    fi
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
        warn "configure-apache: apache2ctl configtest failed — fix $APACHE_SITE_FILE / $APACHE_CONF_FILE manually"
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
    if [ -f "$APACHE_CONF_TEMPLATE" ]; then
        retire_subdir_site
        install_app_conf
        ensure_default_sites
    else
        install_site_config
        enable_supermon_site
        install_app_conf
        warn_root_mode_shadowed
    fi
    reload_apache
}

remove_apache_site() {
    local changed=false
    if [ -d /etc/apache2/sites-available ] && [ -f "$APACHE_SITE_FILE" ]; then
        a2dissite -q supermon-ng 2>/dev/null || true
        rm -f "$APACHE_SITE_FILE"
        changed=true
    fi
    if [ -f "$APACHE_CONF_FILE" ]; then
        a2disconf -q supermon-ng 2>/dev/null || true
        rm -f "$APACHE_CONF_FILE"
        changed=true
    fi
    if [ "$changed" = true ] && command -v apache2ctl >/dev/null 2>&1; then
        apache2ctl configtest >/dev/null 2>&1 && systemctl reload apache2 2>/dev/null || true
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
