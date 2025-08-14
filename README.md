# supermon-ng

**supermon-ng** is a modernized and extensible version of the original Supermon dashboard for managing and monitoring Asterisk-based systems such as AllStarLink nodes. It offers a streamlined web interface and compatibility with today's system environments.

## Features

- Responsive and mobile-friendly web UI
- Enhanced security and codebase modernization
- Simple installer script for quick deployment
- Easily customizable and extendable
- Compatible with Debian-based systems

## Upgrading from <2.0.3 instructions
If you are updating from anything before 2.0.3 you will need to modify two config files, these are global.inc and node_info.ini

$HAMCLOCK_URL is no longer and should be updated to the following, user_files/global.inc
```bash
// URL for users accessing from your local network (e.g., 192.168.x.x)
$HAMCLOCK_URL_INTERNAL = "http://YOUR_INTERNAL_IP_OR_HOSTNAME/hamclock/live.html";
// URL for users accessing from the internet
$HAMCLOCK_URL_EXTERNAL = "http://YOUR_EXTERNAL_IP_OR_HOSTNAME/hamclock/live.html";
```

ADD custom link for SkyWarn Alerts to user_files/sbin/node_info.ini, add to bottom of autosky stanza
```bash
CUSTOM_LINK = https://alerts.weather.gov/cap/wwaatmget.php?x=TXC039&y=1
```
You will want to update TX039 for your county code, this should match the county code used in SkyWarn Plus

## Quick Install

**System Requirements:** Debian-based system (Debian, Ubuntu, or AllStarLink distribution)

First, ensure `rsync` and other necessary tools are installed:

```bash
sudo apt update && sudo apt install -y rsync acl
```

Then, download and run the installer script:

```bash
wget -q -O supermon-ng-installer.sh "https://raw.githubusercontent.com/hardenedpenguin/supermon-ng/refs/heads/main/supermon-ng-installer.sh"
chmod +x supermon-ng-installer.sh
sudo ./supermon-ng-installer.sh
```

> ⚠️ **Note:** This installer is designed for Debian-based systems (e.g., Debian, Ubuntu, or AllStarLink distributions). Run as root or with `sudo`.

## Creating Releases

To create a release tarball with proper versioning:

```bash
./scripts/create-release.sh
```

This will:
- Extract version information from `includes/common.inc`
- Create a compressed `.tar.xz` package
- Generate comprehensive documentation (INSTALL.md, RELEASE_NOTES.md)
- Create checksums (SHA256, SHA512, MD5)
- Validate the release package

> ⚠️ **Note:** This installer is designed for Debian-based systems (e.g., Debian, Ubuntu, or AllStarLink distributions). Run as root or with `sudo`.
> ⚠️ **Note:** authusers.inc can be enabled/disabled during initial install, you will be prompted for a response.

## Post-Installation

After the installer completes and you have configured an initial user, it is recommended to review and customize user permissions.

You can do this by editing the `authusers.inc` file, which is typically located in your web server's directory for supermon-ng (e.g., `/var/www/html/supermon-ng/`).
Choose the option that works best for you, the sed statement is best if you are the sole admin or the lead admin.
```bash
sudo nano /var/www/html/supermon-ng/user_files/authusers.inc
```
```bash
sudo sed -i 's/admin/username/g' /var/www/html/supermon-ng/user_files/authusers.inc
```
If you are using the sed method, please ensure you replace username with the username you have created for your supermon-ng login.

## Documentation

For detailed configuration and deployment guides, see the [docs/](docs/) directory:

- **[DEPLOYMENT_CONFIGURATION.md](docs/DEPLOYMENT_CONFIGURATION.md)** - Reverse proxy setup, HamClock integration, and advanced configuration
- **[DEVELOPER_GUIDE.md](docs/DEVELOPER_GUIDE.md)** - Development and architecture information
- **[CONTRIBUTING.md](docs/CONTRIBUTING.md)** - How to contribute to the project

## Themes

You can find a few themes I have thrown together to speed up customizing your install

```bash
https://w5gle.us/~anarchy/supermon-ng_themes/
```
Once you download the file you must copy it to /var/www/html/supermon-ng/supermon-ng.css, make sure you do not leave it named as it is downloaded!
> ⚠️ **Note:** Themes are being updated to have full support, Everything but seafoamgreen themese have been updated already..
> 

## Contributions

Contributions, issues, and feature requests are welcome! Please fork the repository and submit a pull request.

## License

[MIT](LICENSE)
