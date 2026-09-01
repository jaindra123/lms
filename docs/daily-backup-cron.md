# Daily database + project + moodledata backup (staging cron)

## Staging paths (corrected)

```text
Code root       = /var/www/html/lms_stage
$CFG->dataroot  = /var/www/html/moodledata_stage   # sibling of lms_stage, NOT inside it
$CFG->dbhost    = 10.247.137.82
$CFG->dbname    = lms_stage
$CFG->dbuser    = stage_user
```

## `/etc/iiidem/backup.env`

```bash
SITE_ROOT=/var/www/html/lms_stage
MOODLEDATA=/var/www/html/moodledata_stage
BACKUP_ROOT=/var/backups/iiidem
KEEP_DAYS=7
INCLUDE_MOODLEDATA=1

DB_HOST=10.247.137.82
DB_NAME=lms_stage
DB_USER=stage_user
DB_PASS=…   # from config.php — do not commit
```

## Fix on server + re-run

```bash
# Confirm moodledata path
ls -ld /var/www/html/moodledata_stage

# Edit env
sudo nano /etc/iiidem/backup.env
# set: MOODLEDATA=/var/www/html/moodledata_stage

# Run backup again
sudo /var/www/html/lms_stage/scripts/daily-backup.sh
ls -lh /var/backups/iiidem/*/
# Expect: db-lms_stage.sql.gz  project-code.zip  moodledata.zip
```

## Daily cron (once)

```bash
crontab -e
```

```cron
15 2 * * * /var/www/html/lms_stage/scripts/daily-backup.sh >> /var/log/iiidem-backup.log 2>&1
```

Then it runs automatically every day at 02:15. No daily manual command.
