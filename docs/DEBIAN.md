# Debian package (supermon-ng)

`.deb` packaging for ASL3+ nodes. The `.deb` (via apt or a local install) is the only supported way to install and update supermon-ng; the old tarball `install.sh` / `update.sh` flows have been removed. Apache is configured automatically on install, with debconf prompts to opt out or refresh the site on upgrade.

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

Output: `../supermon-ng_<version>_all.deb` (one directory above the repo). Tagged releases also publish the `.deb` on [GitHub Releases](https://github.com/hardenedpenguin/supermon-ng/releases) (built by CI when a `V*` tag is pushed).

Optional:

```bash
NODE_STATUS_INTERVAL_MINUTES=10 ./scripts/build-deb.sh
DEB_SKIP_FRONTEND_BUILD=1 ./scripts/build-deb.sh   # reuse existing frontend/dist
```

The build rebuilds the frontend by default. Set `DEB_SKIP_FRONTEND_BUILD=1` only when you intentionally want to reuse an existing `frontend/dist`.

## APT repository

Published packages are in the [hardenedpenguin APT repository](https://hardenedpenguin.github.io/hardenedpenguin-apt/) (`stable`, `amd64` / `arm64`). New releases are published there automatically when a `V*` tag is pushed.

**One-time setup** on the node:

```bash
cd /tmp
curl -fsSLO https://hardenedpenguin.github.io/hardenedpenguin-apt/pool/main/h/hardenedpenguin-archive-keyring/hardenedpenguin-archive-keyring_1.0_all.deb
sudo apt install ./hardenedpenguin-archive-keyring_1.0_all.deb
sudo apt update
```

The `hardenedpenguin-archive-keyring` package installs the GPG key and `/etc/apt/sources.list.d/hardenedpenguin.list`.

**Install or upgrade:**

```bash
sudo apt install supermon-ng
```

## Install

**From the APT repository** (after one-time setup above):

```bash
sudo apt install supermon-ng
```

**From a local `.deb`** (GitHub Releases or `./scripts/build-deb.sh` output):

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

On a **fresh install**, `postinst` also:

- creates `.env` from `.env.example`
- runs `generate_local_allmon.php` (`--if-missing` when `allmon.ini` exists, else `--force`)
- installs the Apache site from the current `.env` (using `OVERWRITE_SITE=true`)
- applies `NODE_STATUS_INTERVAL_MINUTES` from `.env` via a systemd drop-in
- sets `www-data` ownership and file modes under the app tree

Systemd units are enabled and started by the package maintainer scripts (`dh_installsystemd`).

## Migrating from a legacy tarball install to apt

Older sites installed from the (now removed) `install.sh` tarball should move to the package. To migrate an existing tarball tree:

1. **Back up** configs:

   ```bash
   sudo tar -czf /root/supermon-ng-pre-apt-backup.tar.gz \
     /var/www/html/supermon-ng/user_files \
     /var/www/html/supermon-ng/.env \
     /var/www/html/supermon-ng/database \
     /var/www/html/supermon-ng/astdb.txt
   ```

2. **Remove** the old install (stop services, delete `/var/www/html/supermon-ng`, Apache site, `/etc/sudoers.d/011_www-nopasswd`, systemd unit files under `/etc/systemd/system/supermon-ng-*`).

3. **Add the APT repository** if not already configured (see [APT repository](#apt-repository) above), then **install** the package:

   ```bash
   sudo apt install supermon-ng
   ```

   Or install a `.deb` from [GitHub Releases](https://github.com/hardenedpenguin/supermon-ng/releases) with `sudo apt install ./supermon-ng_*_all.deb`.

4. **Restore** your backup over the package tree:

   ```bash
   sudo tar -xzf /root/supermon-ng-pre-apt-backup.tar.gz -C /
   ```

5. **Re-apply** configuration from the restored `.env` (required for dedicated vhosts such as `APP_BASE_PATH=/` and `SUPERMON_SERVER_NAME`):

   ```bash
   sudo dpkg-reconfigure supermon-ng
   ```

   Answer **Yes** to refresh the Apache site, or run:

   ```bash
   sudo OVERWRITE_SITE=true /var/www/html/supermon-ng/scripts/configure-apache.sh configure
   ```

6. Remove the old tarball sudoers file if still present:

   ```bash
   sudo rm -f /etc/sudoers.d/011_www-nopasswd
   ```

7. Remove duplicate systemd units under `/etc/systemd/system/supermon-ng-*` if the tarball left copies there (the package uses `/usr/lib/systemd/system/`).

## Configure

1. Edit `.env` (created from `.env.example` on first install).
2. Set `APP_BASE_PATH` (`/supermon-ng` for subdirectory, `/` for dedicated vhost).
3. Set `SUPERMON_SERVER_NAME` and `SSL_CERT_NAME` if using a custom certificate (see `.env.example`).
4. Set `NODE_STATUS_INTERVAL_MINUTES` if you want a non-default node-status timer interval.
5. Re-apply configuration after `.env` changes:

   ```bash
   sudo dpkg-reconfigure supermon-ng
   ```

   Or re-run Apache + paths only:

   ```bash
   sudo /var/www/html/supermon-ng/scripts/configure-apache.sh configure
   ```

   To refresh only the node-status timer from `.env`:

   ```bash
   sudo dpkg-reconfigure supermon-ng
   ```

   The interval is stored in `/etc/systemd/system/supermon-ng-node-status.timer.d/interval.conf`.

6. Complete setup via the web UI or existing `user_files` configs.

### Apache files

| Path | Purpose |
|------|---------|
| `/var/www/html/supermon-ng/apache-config-template.conf` | Generated vhost template |
| `/etc/apache2/sites-available/supermon-ng.conf` | Installed site (refreshed on first install; preserved on upgrade unless debconf refresh) |

Required modules are enabled automatically: `proxy`, `proxy_http`, `proxy_wstunnel`, `rewrite`, `headers`, `substitute`, `ssl`, `deflate`, `expires`.

If you use Let's Encrypt, install `certbot` separately (it is not a package dependency). Apache setup auto-detects certs under `/etc/letsencrypt/live/` when present; otherwise the generated vhost uses the Debian `ssl-cert` snakeoil certificate until you add real TLS.

Log ACLs for `www-data` are applied when the `acl` package is present.

## Services

```bash
sudo systemctl status supermon-ng-backend supermon-ng-websocket
sudo systemctl status supermon-ng-node-status.timer supermon-ng-database-update.timer
```

## Upgrade

**From the APT repository:**

```bash
sudo apt update
sudo apt install supermon-ng
```

**From a local `.deb`:**

```bash
sudo dpkg -i ../supermon-ng_<new>_all.deb
```

User configs listed in `debian/supermon-ng.conffiles` (including `favini.inc`) are preserved. To replace a customized Apache site with a freshly generated one, answer **Yes** to the debconf refresh prompt, or run:

```bash
sudo OVERWRITE_SITE=true /var/www/html/supermon-ng/scripts/configure-apache.sh configure
```

`/etc/sudoers.d/011-supermon-ng` is also a conffile. If a package upgrade adds new sudo rules, dpkg may prompt to keep your file or install the package maintainer version. **Announcements** upgrades add `announce-*.sh` sudo lines — choose the package maintainer version unless you have custom edits.

## Announcements (optional)

See **[docs/ANNOUNCEMENTS.md](ANNOUNCEMENTS.md)** for permissions, packages, local/global/EchoLink playback, scheduling, and troubleshooting.

On upgrade, if dpkg prompts about `/etc/sudoers.d/011-supermon-ng`, install the **maintainer version** to pick up `announce-*.sh` rules (or merge manually).

## Remove / purge

- **remove**: stops services, disables the `supermon-ng` Apache site; keeps the site file on disk.
- **purge**: also removes `/etc/apache2/sites-available/supermon-ng.conf`, sudoers drop-in, and the node-status timer drop-in.

## Legacy tarball note

Older installs used a tarball with `install.sh` / `update.sh` and a `/etc/sudoers.d/011_www-nopasswd` sudoers file. Those flows are gone; the package uses `/etc/sudoers.d/011-supermon-ng` and manages Apache, composer vendoring, `allmon.ini` generation, and the node-status interval through debconf and the maintainer scripts. See [Migrating from a legacy tarball install to apt](#migrating-from-a-legacy-tarball-install-to-apt).
