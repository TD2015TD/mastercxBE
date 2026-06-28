#!/bin/bash
# Ensures email:idle daemon is running. Called every minute from crontab.
ARTISAN="/var/www/cxsuite.telediant.com.my/api/artisan"
LOG="/var/www/cxsuite.telediant.com.my/api/storage/logs/email-idle.log"

if ! pgrep -f "email:idle" > /dev/null 2>&1; then
    echo "[$(date)] email:idle not running — starting" >> "$LOG"
    nohup /usr/bin/php "$ARTISAN" email:idle >> "$LOG" 2>&1 &
fi
