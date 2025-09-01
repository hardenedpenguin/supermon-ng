# Supermon-NG - ASL3+ Management Dashboard

A modern web-based management interface for AllStar Link (ASL3+) nodes, built with Vue.js and PHP.

## 🚀 Quick Installation

### For ASL3+ Server Installation

1. **Download the code to your ASL3+ server:**
   ```bash
   cd /var/www/html
   git clone https://github.com/your-repo/supermon-ng.git
   cd supermon-ng
   ```

2. **Run the installation script:**
   ```bash
   sudo ./install.sh
   ```

3. **Access your Supermon-NG dashboard:**
   ```
   http://your-server-ip
   ```

That's it! The installation script will:
- Install all required dependencies (PHP, Node.js, etc.)
- Build the frontend with correct server settings
- Create systemd services for automatic startup
- Set up proper permissions and directories
- Start the application automatically

## 🔧 Management Commands

After installation, use these commands to manage Supermon-NG:

```bash
# Start the application
sudo /var/www/html/supermon-ng/start.sh

# Stop the application
sudo /var/www/html/supermon-ng/stop.sh

# Check status
sudo /var/www/html/supermon-ng/status.sh

# View logs
sudo journalctl -u supermon-ng-backend -f
sudo journalctl -u supermon-ng-frontend -f
```

## 📋 System Requirements

- **OS**: Debian/Ubuntu (tested on Debian 11+)
- **PHP**: 7.4+ with SQLite3, cURL, and mbstring extensions
- **Node.js**: 16+ and npm
- **RAM**: 512MB minimum, 1GB recommended
- **Storage**: 100MB free space

## 🔧 Configuration

### AMI Settings
Configure your Asterisk Manager Interface settings in `/var/www/html/supermon-ng/user_files/`:

1. Copy `user_files/allmon.ini.example` to `user_files/allmon.ini`
2. Edit the file with your node configurations
3. Set your AMI credentials and connection details

### Node Configuration
Add your ASL nodes to the configuration file:

```ini
[node_546050]
host=localhost
port=5038
username=admin
password=your_password
```

## 🌐 Access URLs

- **Dashboard**: `http://your-server-ip` (standard HTTP port 80)
- **SSL Dashboard**: `https://your-server-ip` (if SSL certificate is configured)

## 🔒 Security Notes

- Change default passwords in your AMI configuration
- Configure firewall rules to restrict access if needed
- The application runs as `www-data` user for security

## 🐛 Troubleshooting

### Application won't start
```bash
# Check service status
sudo systemctl status supermon-ng-backend
sudo systemctl status supermon-ng-frontend

# View logs
sudo journalctl -u supermon-ng-backend -f
```

### Can't access the web interface
1. Check if services are running: `sudo /var/www/html/supermon-ng/status.sh`
2. Verify firewall settings: `sudo ufw status`
3. Check if Apache is running: `sudo systemctl status apache2`
4. Check Apache logs: `sudo tail -f /var/log/apache2/supermon-ng_error.log`

### AMI connection issues
1. Verify Asterisk is running: `sudo systemctl status asterisk`
2. Check AMI configuration in `/etc/asterisk/manager.conf`
3. Test AMI connection: `telnet localhost 5038`

## 📝 Development

For development on your workstation:

```bash
# Install dependencies
composer install
cd frontend && npm install

# Start development servers
./scripts/start-dev.sh
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

For support and questions:
- Create an issue on GitHub
- Check the troubleshooting section above
- Review the logs for error messages
