#!/bin/bash
set -eu
MARKER="/var/moodledata/.moodle-dataroot-ready"
OLD="/var/www/html/moodledata"
NEW="/var/moodledata"

sudo mkdir -p "$NEW"
sudo chown -R "$(id -u):$(id -g)" "$NEW"

if [ -f "$MARKER" ]; then
    exit 0
fi

if [ -d "$OLD" ] && [ -n "$(ls -A "$OLD" 2>/dev/null || true)" ]; then
    echo "Migrating Moodle dataroot to Docker volume..."
    rsync -a "$OLD/" "$NEW/"
fi

touch "$MARKER"
echo "Moodle dataroot ready at $NEW"
