# supermon-ng

**supermon-ng** is a modernized and extensible version of the original Supermon dashboard for managing and monitoring Asterisk-based systems such as AllStarLink nodes. It offers a streamlined web interface and compatibility with today's system environments.

## Features

- Responsive and mobile-friendly web UI
- Enhanced security and codebase modernization
- Simple installer script for quick deployment
- Easily customizable and extendable
- Compatible with Debian-based systems

## Quick Install

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
