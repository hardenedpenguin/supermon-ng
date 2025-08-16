# supermon-ng

**supermon-ng** is a modernized, containerized dashboard for managing and monitoring Asterisk-based systems such as AllStarLink nodes. It is now deployed exclusively via Docker for simplicity, security, and maintainability.

## Features
- Responsive and mobile-friendly web UI
- Enhanced security and codebase modernization
- Docker-only deployment for easy setup and upgrades
- Easily customizable and extendable
- Compatible with all modern Linux systems supporting Docker

## 🐳 Docker Deployment (Production & Development)

### Quick Start
```bash
git clone https://github.com/your-org/supermon-ng.git
cd supermon-ng
cp env.production .env  # Edit .env with your settings
# Prepare your user_files directory (see below)
docker-compose up -d
```

### How to use and persist user_files
- All configuration and persistent data (such as `global.inc`, `allmon.ini`, etc.) should be placed in the `user_files` directory in your project root **before** running Docker.
- The `docker-compose.yml` file mounts this directory into the container, so any changes you make on the host are instantly reflected in the running app.
- To get started, copy the example files:
  ```bash
  cp user_files/global.inc.example user_files/global.inc
  cp user_files/allmon.ini.example user_files/allmon.ini
  # ...copy any other needed example files...
  # Then edit them with your settings
  nano user_files/global.inc
  nano user_files/allmon.ini
  ```
- You can back up, restore, or edit these files at any time without rebuilding the Docker image.

### Environment Variables
- Copy `env.production` to `.env` and edit as needed.
- **Key variables:**
  - `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_NAME`, `APP_VERSION`
  - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
  - `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`
  - `CALL`, `NAME`, `LOCATION` (for AllStar)
  - See `env.production` for all options and documentation.

### Web Login Setup (Required)
Supermon-ng uses a password file at `user_files/.htpasswd` for web login authentication.
- **You must run:**
  ```bash
  ./user_files/set_password.sh
  ```
  to create or manage your web login credentials.
- The web interface will not allow login until this file exists.

### Stopping, Rebuilding, and Restarting
```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Troubleshooting & FAQ
- **How do I update my configuration?**
  - Edit files in `user_files/` or `.env` and restart the container.
- **How do I back up my data?**
  - Back up the `user_files/` directory and your database/redis volumes.
- **How do I reset my password?**
  - Run `./user_files/set_password.sh` again.
- **How do I see logs?**
  - `docker-compose logs -f supermon-ng`
- **How do I update Supermon-ng?**
  - Pull the latest code, rebuild the image, and restart.

### Documentation
- See the [docs/](docs/) directory for advanced configuration, monitoring, and deployment guides.

## License
[MIT](LICENSE)
