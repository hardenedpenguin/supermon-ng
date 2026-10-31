#!/bin/bash
# Apache setup for supermon-ng, invoked by the Debian package postinst
# (and runnable by hand for reconfiguration).
#
# Environment:
#   APP_DIR              Application root (default: /usr/share/supermon-ng)
#   USER_FILES_DIR       Real user_files directory Apache serves (default: APP_DIR/user_files, symlinks resolved)
#   STATE_DIR            Where generated templates are written (default: /var/lib/supermon-ng)
#   SKIP_APACHE          true = only generate template + app-base-path, no site install
#   DISABLE_DEFAULT_SITES  root mode only: true = a2dissite 000-default and default-ssl (default: true)
#   OVERWRITE_SITE       true = replace existing sites-available/supermon-ng.conf (root mode),
#                        or retire it even if customized (subdirectory mode, which uses no site;
#                        conf-available/supermon-ng.conf is always regenerated)
#   ALLOW_APT_INSTALL    true = apt-get install apache2/ssl-cert if missing
#   CONFIGURE_LOG_ACLS   true = setfacl on Apache/Asterisk logs (default: true)
#   QUIET                true = less output

set -euo pipefail

APP_DIR="${APP_DIR:-/usr/share/supermon-ng}"
USER_FILES_DIR="${USER_FILES_DIR:-$(readlink -f "$APP_DIR/user_files" 2>/dev/null || printf '%s' "$APP_DIR/user_files")}"
STATE_DIR="${STATE_DIR:-/var/lib/supermon-ng}"
# Install location before 4.4.0; existing site files may still point here.
LEGACY_APP_DIR=/var/www/html/supermon-ng
SKIP_APACHE="${SKIP_APACHE:-false}"
DISABLE_DEFAULT_SITES="${DISABLE_DEFAULT_SITES:-true}"
OVERWRITE_SITE="${OVERWRITE_SITE:-false}"
ALLOW_APT_INSTALL="${ALLOW_APT_INSTALL:-false}"
CONFIGURE_LOG_ACLS="${CONFIGURE_LOG_ACLS:-true}"
QUIET="${QUIET:-false}"

APACHE_SITE_FILE="/etc/apache2/sites-available/supermon-ng.conf"
APACHE_TEMPLATE="${STATE_DIR}/apache-config-template.conf"
APACHE_CONF_FILE="/etc/apache2/conf-available/supermon-ng.conf"
APACHE_CONF_TEMPLATE="${STATE_DIR}/apache-conf-template.conf"
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

    export APP_DIR USER_FILES_DIR APP_BASE_PATH SUPERMON_SERVER_NAME SSL_CERT_NAME
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
    mkdir -p "$STATE_DIR"
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

# Subdirectory mode with an installed site whose RedirectMatch would match APP_BASE_PATH
# (a vhost redirect runs before the global Alias).
root_redirect_conflicts() {
    [ -f "$APACHE_CONF_TEMPLATE" ] || return 1
    local base="${APP_BASE_PATH%/}" re
    while IFS= read -r re; do
        [ -n "$re" ] || continue
        if printf '%s\n%s/\n' "$base" "$base" | grep -E -- "$re" >/dev/null 2>&1; then
            return 0
        fi
    done < <(sed -nE 's/^[[:space:]]*RedirectMatch[[:space:]]+([0-9]{3}[[:space:]]+|(permanent|temp|seeother|gone)[[:space:]]+)?("[^"]*"|[^[:space:]]+).*/\3/Ip' "$APACHE_SITE_FILE" \
        | sed -E 's/^"(.*)"$/\1/')
    return 1
}

# Site text with pre-4.4.0 install paths rewritten to the current layout.
with_current_paths() {
    local legacy_re
    legacy_re=$(re_escape "$LEGACY_APP_DIR")
    sed -E -e "s#${legacy_re}/user_files#${USER_FILES_DIR}#g" -e "s#${legacy_re}#${APP_DIR}#g" "$1"
}

# Point a kept site file at the current install (backup: <site>.legacy-path.bak).
migrate_legacy_site_paths() {
    local f="$APACHE_SITE_FILE"
    [ -f "$f" ] && grep -qF "$LEGACY_APP_DIR" "$f" || return 0
    cp -p "$f" "${f}.legacy-path.bak"
    with_current_paths "${f}.legacy-path.bak" > "$f"
    log "Updated $LEGACY_APP_DIR paths in $f (backup: ${f}.legacy-path.bak)"
}

# True when the installed site is byte-identical (after with_current_paths) to what root
# mode generates for the ServerName and certificate recorded in that site (not the current .env).
root_site_is_generated() {
    local f="$APACHE_SITE_FILE" tmp rc=1 server_name cert_name force_snakeoil=false
    server_name=$(awk 'tolower($1) == "servername" { print $2; exit }' "$f")
    cert_name=$(sed -nE 's#^[[:space:]]*SSLCertificateFile[[:space:]]+/etc/letsencrypt/live/([^/]+)/fullchain\.pem[[:space:]]*$#\1#p' "$f" | head -n 1)
    [ -n "$cert_name" ] || force_snakeoil=true
    tmp=$(mktemp) || return 1
    if APP_DIR="$APP_DIR" USER_FILES_DIR="$USER_FILES_DIR" APP_BASE_PATH=/ SUPERMON_SERVER_NAME="$server_name" SSL_CERT_NAME="$cert_name" FORCE_SNAKEOIL="$force_snakeoil" \
        bash "$APP_DIR/scripts/generate-apache-template.sh" "$tmp" >/dev/null 2>&1 \
        && with_current_paths "$f" | cmp -s "$tmp" -; then
        rc=0
    fi
    rm -f "$tmp"
    return "$rc"
}

re_escape() {
    printf '%s' "$1" | sed 's/[][\.^$*+?(){}|#/]/\\&/g'
}

# Site text with the install dir and base path replaced by placeholders.
normalize_site() {
    local app_re legacy_re prefix_re
    app_re=$(re_escape "$APP_DIR")
    legacy_re=$(re_escape "$LEGACY_APP_DIR")
    prefix_re=$(re_escape "$2")
    sed -E -e "s#${app_re}#@APP_DIR@#g" -e "s#${legacy_re}#@APP_DIR@#g" \
        -e "s#${prefix_re}([/[:space:]\")]|\$)#@PREFIX@\\1#g" "$1"
}

# sha256 of normalize_site output for every subdirectory-mode site generate-apache-template.sh
# has written (snakeoil TLS, no ServerName). The generator no longer writes these sites.
GENERATED_SUBDIR_SITE_SHA256='
519bcce8a545a67d60800922134390e87b83b6a13523b1d5bb1df5767ddfb513
ad7d7ebbcfbe073e2e08b1fd32b2cb754dd1b5d7c1f55e3fd0ad94511a0f7a86
8bb5531b464cf11d6f54af1bcf4d1ffa7e4c026c1e16afcd374c252a6018caa1
717d819481e89c6fc1b3f972be5a85d987150253bba9325dd7a0c16ec5bdde7b
'

# True when the installed site is exactly a subdirectory-mode site we generated (any past release),
# so nothing in it is customized.
subdir_site_is_generated() {
    local f="$APACHE_SITE_FILE" prefix sum
    prefix=$(sed -nE '1s/^# Supermon-NG Apache configuration \(APP_BASE_PATH=(\/[^)]*[^)/])\/*\)$/\1/p' "$f")
    [ -n "$prefix" ] || return 1
    sum=$(normalize_site "$f" "$prefix" | sha256sum | cut -d' ' -f1)
    printf '%s\n' "$GENERATED_SUBDIR_SITE_SHA256" | grep -qx "$sum"
}

site_has_host_or_tls() {
    grep -qiE '^[[:space:]]*ServerName[[:space:]]' "$APACHE_SITE_FILE" \
        || grep -qE '^[[:space:]]*SSLCertificateFile[[:space:]]+/etc/letsencrypt/' "$APACHE_SITE_FILE"
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
        if site_has_host_or_tls; then
            # Its hostname/certificate would be lost; the default sites only provide snakeoil.
            warn "configure-apache: keeping generated root-mode site $APACHE_SITE_FILE (it has a ServerName or Let's Encrypt certificate); its hostname still serves the APP_BASE_PATH=/ layout."
            warn "configure-apache: to use ${APP_BASE_PATH} there, re-run with OVERWRITE_SITE=true (or 'sudo dpkg-reconfigure supermon-ng' and choose to regenerate) to retire it."
        else
            retire_site "APP_BASE_PATH=${APP_BASE_PATH} but $APACHE_SITE_FILE is the generated root-mode site"
        fi
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
        migrate_legacy_site_paths
        install_app_conf
        ensure_default_sites
    else
        migrate_legacy_site_paths
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
