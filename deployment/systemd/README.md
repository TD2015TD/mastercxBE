# Laravel Systemd Services for Ubuntu

This directory contains systemd service files for running Laravel background workers and schedulers on Ubuntu servers.

## 📋 Available Services

### 1. **laravel-queue.service**
Main queue worker for processing default queue jobs.
- Queue: `default`
- Auto-restart on failure
- Max execution time: 1 hour
- Retry failed jobs: 3 times

### 2. **laravel-queue-notifications.service**
Dedicated queue worker for notification jobs.
- Queue: `notifications`
- Auto-restart on failure
- Processes: ticket notifications, SLA alerts, email notifications

### 3. **laravel-scheduler.service**
Laravel task scheduler (replaces cron).
- Runs `php artisan schedule:run` every minute
- Handles: scheduled tasks, maintenance jobs, cleanup tasks

### 4. **laravel-email-processor.service**
Email processor for IMAP email-to-ticket conversion.
- Runs `php artisan email:process` continuously
- Check interval: 60 seconds
- Auto-restart on failure

### 5. **laravel-websocket.service**
WebSocket server for real-time notifications.
- Default port: 6001
- Supports Laravel Reverb or Soketi
- Auto-restart on failure

## 🚀 Quick Installation

### Option 1: Automated Installation (Recommended)

```bash
# Navigate to systemd directory
cd /path/to/jkdm-crm/crm-api/deployment/systemd

# Make install script executable
chmod +x install.sh

# Run installation script with sudo
sudo ./install.sh
```

The script will:
- ✅ Prompt for your Laravel project path
- ✅ Update service files with correct paths
- ✅ Copy services to `/etc/systemd/system/`
- ✅ Enable services to start on boot
- ✅ Start all services
- ✅ Show status of each service

### Option 2: Manual Installation

```bash
# 1. Edit service files and update WorkingDirectory path
# Replace /var/www/jkdm-crm/crm-api with your actual path

# 2. Copy service files to systemd directory
sudo cp laravel-queue.service /etc/systemd/system/
sudo cp laravel-queue-notifications.service /etc/systemd/system/
sudo cp laravel-scheduler.service /etc/systemd/system/
sudo cp laravel-email-processor.service /etc/systemd/system/
sudo cp laravel-websocket.service /etc/systemd/system/

# 3. Set correct permissions
sudo chmod 644 /etc/systemd/system/laravel-*.service

# 4. Reload systemd daemon
sudo systemctl daemon-reload

# 5. Enable services (start on boot)
sudo systemctl enable laravel-queue.service
sudo systemctl enable laravel-queue-notifications.service
sudo systemctl enable laravel-scheduler.service
sudo systemctl enable laravel-email-processor.service
sudo systemctl enable laravel-websocket.service

# 6. Start services
sudo systemctl start laravel-queue.service
sudo systemctl start laravel-queue-notifications.service
sudo systemctl start laravel-scheduler.service
sudo systemctl start laravel-email-processor.service
sudo systemctl start laravel-websocket.service
```

## ⚙️ Configuration

### Update Paths

Before installation, update the following in each `.service` file:

```ini
# Change this line to your actual Laravel project path
WorkingDirectory=/var/www/jkdm-crm/crm-api
```

### Update PHP Version

If using a different PHP version:

```ini
# Change PHP version
Environment="PHP_VERSION=8.2"

# Update ExecStart path if needed
ExecStart=/usr/bin/php artisan queue:work ...
```

### Update Service User

Default user is `www-data`. To use a different user:

```ini
User=your-user
Group=your-group
```

## 📊 Managing Services

### Check Service Status

```bash
# Check all Laravel services
sudo systemctl status laravel-*.service

# Check specific service
sudo systemctl status laravel-queue.service
```

### View Logs

```bash
# View logs in real-time
sudo journalctl -u laravel-queue.service -f

# View last 100 lines
sudo journalctl -u laravel-queue.service -n 100

# View logs for all Laravel services
sudo journalctl -u 'laravel-*' -f
```

### Start/Stop/Restart Services

```bash
# Start a service
sudo systemctl start laravel-queue.service

# Stop a service
sudo systemctl stop laravel-queue.service

# Restart a service
sudo systemctl restart laravel-queue.service

# Restart all Laravel services
sudo systemctl restart laravel-*.service
```

### Enable/Disable Auto-start

```bash
# Enable service to start on boot
sudo systemctl enable laravel-queue.service

# Disable auto-start
sudo systemctl disable laravel-queue.service
```

### Reload After Config Changes

```bash
# After editing service files
sudo systemctl daemon-reload
sudo systemctl restart laravel-queue.service
```

## 🔍 Troubleshooting

### Service Won't Start

```bash
# Check detailed status
sudo systemctl status laravel-queue.service

# View error logs
sudo journalctl -u laravel-queue.service -n 50

# Test command manually
cd /var/www/jkdm-crm/crm-api
sudo -u www-data php artisan queue:work --queue=default
```

### Permission Issues

```bash
# Ensure correct ownership
sudo chown -R www-data:www-data /var/www/jkdm-crm/crm-api/storage
sudo chown -R www-data:www-data /var/www/jkdm-crm/crm-api/bootstrap/cache

# Set correct permissions
sudo chmod -R 775 /var/www/jkdm-crm/crm-api/storage
sudo chmod -R 775 /var/www/jkdm-crm/crm-api/bootstrap/cache
```

### Check Dependencies

```bash
# Ensure MySQL is running
sudo systemctl status mysql

# Ensure Redis is running
sudo systemctl status redis-server

# Test database connection
cd /var/www/jkdm-crm/crm-api
php artisan migrate:status
```

## 🎯 Best Practices

### 1. Monitor Queue Health

```bash
# Check queue status
php artisan queue:monitor default,notifications

# View failed jobs
php artisan queue:failed
```

### 2. Log Rotation

Add to `/etc/logrotate.d/laravel`:

```
/var/www/jkdm-crm/crm-api/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

### 3. Resource Monitoring

```bash
# Check resource usage
sudo systemctl status laravel-queue.service | grep -i memory
top -u www-data
```

### 4. Scaling Workers

To run multiple queue workers, create numbered instances:

```bash
# Copy service file
sudo cp /etc/systemd/system/laravel-queue.service \
        /etc/systemd/system/laravel-queue@.service

# Start multiple instances
sudo systemctl start laravel-queue@1.service
sudo systemctl start laravel-queue@2.service
sudo systemctl start laravel-queue@3.service
```

## 📈 Production Recommendations

### Minimum Setup (Small Server)
- ✅ laravel-queue.service (1 worker)
- ✅ laravel-scheduler.service
- ✅ laravel-email-processor.service

### Recommended Setup (Medium Server)
- ✅ laravel-queue.service (2-3 workers)
- ✅ laravel-queue-notifications.service (1 worker)
- ✅ laravel-scheduler.service
- ✅ laravel-email-processor.service
- ✅ laravel-websocket.service

### High-Load Setup (Large Server)
- ✅ laravel-queue.service (5+ workers)
- ✅ laravel-queue-notifications.service (2-3 workers)
- ✅ laravel-scheduler.service
- ✅ laravel-email-processor.service (2 workers)
- ✅ laravel-websocket.service

## 🔗 Related Documentation

- [Laravel Queue Documentation](https://laravel.com/docs/queues)
- [Laravel Scheduler Documentation](https://laravel.com/docs/scheduling)
- [Systemd Documentation](https://www.freedesktop.org/software/systemd/man/systemd.service.html)

## 📝 Notes

- All services are configured to restart automatically on failure
- Services use journal for logging (viewable with `journalctl`)
- Default timeout for queue jobs is 90 seconds
- Workers restart every hour to prevent memory leaks
- WebSocket service port 6001 must be open in firewall

## ⚠️ Important

After deployment updates:
1. Restart queue workers to load new code
2. Clear application cache
3. Run migrations if needed

```bash
# Deployment script
cd /var/www/jkdm-crm/crm-api
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl restart laravel-queue.service
sudo systemctl restart laravel-queue-notifications.service
```
