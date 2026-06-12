# Debian package (supermon-ng)

Internal `.deb` packaging for ASL3+ nodes. Apache is configured automatically on install (same behavior as `install.sh`), with debconf prompts to opt out or refresh the site on upgrade.

## Build dependencies

On the build host:

```bash
sudo apt-get install -y debhelper composer npm nodejs \
  php-cli php-xml php-mbstring php-curl php-sqlite3
```

## Build

From the repository root:

```bash
./scripts/build-deb.sh
```

Output: `../supermon-ng_<version>_all.deb` (one directory above the repo).

Optional:

```bash
NODE_STATUS_INTERVAL_MINUTES=10 ./scripts/build-deb.sh
DEB_SKIP_FRONTEND_BUILD=1 ./scripts/build-deb.sh   # reuse existing frontend/dist
```

## Install

```bash
sudo dpkg -i ../supermon-ng_*_all.deb
sudo apt-get install -f   # if dependencies are missing
```

Install location: `/var/www/html/supermon-ng` (matches tarball installs and sudoers paths).

During install, debconf asks whether to:

- configure Apache (default: yes)
- disable `000-default` / `default-ssl` (default: yes)
- regenerate `/etc/apache2/sites-available/supermon-ng.conf` on upgrade (default: no)

To skip Apache setup non-interactively:

```bash
echo "supermon-ng supermon-ng/configure-apache boolean false" | sudo debconf-set-selections
sudo dpkg -i ../supermon-ng_*_all.deb
```

## Configure

1. Edit `.env` (created from `.env.example` on first install).
2. Set `APP_BASE_PATH` (`/supermon-ng` for subdirectory, `/` for dedicated vhost).
3. Set `SUPERMON_SERVER_NAME` and `SSL_CERT_NAME` if using a custom certificate (see `.env.example`).
4. Re-apply Apache + paths:

   ```bash
   sudo /var/www/html/supermon-ng/scripts/configure-apache.sh configure
   ```

   Or template only (no site changes):

   ```bash
   sudo SKIP_APACHE=true /var/www/html/supermon-ng/scripts/configure-apache.sh template-only
   ```

5. Complete setup via the web UI or existing `user_files` configs.

### Apache files

| Path | Purpose |
|------|---------|
| `/var/www/html/supermon-ng/apache-config-template.conf` | Generated vhost template |
| `/etc/apache2/sites-available/supermon-ng.conf` | Installed site (created by postinst; preserved unless refresh) |

Required modules are enabled automatically: `proxy`, `proxy_http`, `proxy_wstunnel`, `rewrite`, `headers`, `substitute`, `ssl`, `deflate`, `expires`.

Log ACLs for `www-data` are applied when the `acl` package is present (same as `install.sh`).

## Services

```bash
sudo systemctl status supermon-ng-backend supermon-ng-websocket
sudo systemctl status supermon-ng-node-status.timer supermon-ng-database-update.timer
```

## Upgrade

```bash
sudo dpkg -i ../supermon-ng_<new>_all.deb
```

User configs listed in `debian/supermon-ng.conffiles` are preserved. To replace a customized Apache site with a freshly generated one, answer **Yes** to the debconf refresh prompt, or run:

```bash
sudo OVERWRITE_SITE=true /var/www/html/supermon-ng/scripts/configure-apache.sh configure
```

## Remove / purge

- **remove**: stops services, disables the `supermon-ng` Apache site; keeps the site file on disk.
- **purge**: also removes `/etc/apache2/sites-available/supermon-ng.conf` and sudoers drop-in.

## Tarball vs .deb

| | Tarball + `install.sh` | `.deb` |
|--|------------------------|--------|
| Apache setup | `install.sh` (optional `--skip-apache`) | debconf + `postinst` |
| Config backup | `update.sh` | `dpkg`/conffiles |
| Composer vendor | On target | Bundled at build time |

Both can coexist on the same path; do not mix upgrade methods without backing up `user_files/`.
