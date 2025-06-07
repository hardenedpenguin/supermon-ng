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
sudo apt update && sudo apt install -y rsync
```

Then, download and run the installer script:

```bash
wget https://raw.githubusercontent.com/hardenedpenguin/supermon-ng/refs/heads/main/supermon-ng-installer.sh
chmod +x supermon-ng-installer.sh
sudo ./supermon-ng-installer.sh
```

> ⚠️ **Note:** This installer is designed for Debian-based systems (e.g., Debian, Ubuntu, or AllStarLink distributions). Run as root or with `sudo`.

## Post-Installation

After the installer completes and you have configured an initial user, it is recommended to review and customize user permissions.

You can do this by editing the `authuser.inc` file, which is typically located in your web server's directory for supermon-ng (e.g., `/var/www/html/supermon-ng/`).
Choose the option that works best for you, the sed statement is best if you are the sole admin or the lead admin.
```bash
sudo nano /var/www/html/supermon-ng/user_files/authuser.inc
```
```bash
sudo sed -i 's/admin/username/g' /var/www/html/supermon-ng/user_files/authuser.inc
```
If you are using the sed method, please ensure you replace username with the username you have created for your supermon-ng login.

## Requirements

- Debian-based Linux system
- Apache2 or other compatible web server
- Asterisk with AllStarLink node configured
- PHP 7.4 or later
- Rsync

## Contributions

Contributions, issues, and feature requests are welcome! Please fork the repository and submit a pull request.

## License

[MIT](LICENSE)
