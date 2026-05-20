# Backup automation — Spatie laravel-backup

Date : 2026-05-20. Non installé par défaut — guide d'activation.

## Installation

```bash
composer require spatie/laravel-backup
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
```

Cette commande crée `config/backup.php`.

## Configuration recommandée pour CleanUx

### Sources à backuper

```php
// config/backup.php
'backup' => [
    'name' => env('APP_NAME', 'cleanux-v2'),
    'source' => [
        'files' => [
            'include' => [
                base_path('.env'),
                storage_path('app'),                 // documents KYB/Contracts/Fleet/Chat attachments
                storage_path('app/public'),          // public uploads
                base_path('database/seeders'),       // pour reproduire prod en staging
            ],
            'exclude' => [
                base_path('vendor'),
                base_path('node_modules'),
                base_path('storage/framework/cache'),
                base_path('storage/framework/views'),
                base_path('storage/logs'),
            ],
        ],
        'databases' => [env('DB_CONNECTION', 'mysql')],
    ],
    'database_dump_compressor' => Spatie\DbDumper\Compressors\GzipCompressor::class,
],
```

### Destinations (multi-disk pour redondance)

```php
'destination' => [
    'filename_prefix' => 'cleanux-prod-',
    'disks' => [
        'local_backup',   // disque local primaire
        's3_backup',      // S3 offsite (recommandé)
    ],
],
```

Ajouter dans `config/filesystems.php` :
```php
'disks' => [
    'local_backup' => [
        'driver' => 'local',
        'root' => storage_path('app/backups'),
        'throw' => false,
    ],
    's3_backup' => [
        'driver' => 's3',
        'bucket' => env('AWS_BACKUP_BUCKET', 'cleanux-backups'),
        // ... credentials AWS
    ],
],
```

### Rétention

```php
'cleanup' => [
    'strategy' => Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,
    'default_strategy' => [
        'keep_all_backups_for_days' => 7,
        'keep_daily_backups_for_days' => 30,
        'keep_weekly_backups_for_weeks' => 12,
        'keep_monthly_backups_for_months' => 12,
        'keep_yearly_backups_for_years' => 7,
        'delete_oldest_backups_when_using_more_megabytes_than' => 200_000, // 200 GB
    ],
],
```

### Notifications

```php
'notifications' => [
    'notifications' => [
        Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail', 'slack'],
        Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail', 'slack'],
        Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
        Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => ['slack'],
        Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => [],
        Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => [],
    ],
    'mail' => ['to' => env('BACKUP_NOTIFY_EMAIL', 'ops@cleanux.com')],
    'slack' => [
        'webhook_url' => env('BACKUP_SLACK_WEBHOOK'),
        'channel' => '#cleanux-ops-backup',
    ],
],

'monitor_backups' => [
    [
        'name' => env('APP_NAME', 'cleanux-v2'),
        'disks' => ['local_backup', 's3_backup'],
        'health_checks' => [
            \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
            \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 50_000,
        ],
    ],
],
```

## Schedule

Ajouter dans `app/Console/Kernel.php::schedule()` :

```php
$schedule->command('backup:clean')->dailyAt('01:00')->withoutOverlapping();
$schedule->command('backup:run')->dailyAt('02:00')->withoutOverlapping();
$schedule->command('backup:monitor')->dailyAt('07:00');
```

Convention CleanUx :
- 01:00 cleanup ancien (libère espace avant le nouveau backup)
- 02:00 run backup complet
- 07:00 monitor health + envoie alert si dernier backup > 24h

## Commands utiles

```bash
# Backup manuel immédiat
php artisan backup:run

# Backup files only (skip DB dump)
php artisan backup:run --only-files

# Backup DB only
php artisan backup:run --only-db

# Lister backups existants
php artisan backup:list

# Monitor health (alert si dernier > 24h)
php artisan backup:monitor

# Cleanup ancien
php artisan backup:clean
```

## Restoration (workflow manuel)

Spatie ne propose pas de restore automatique pour des raisons de sécurité.
Workflow manuel :

```bash
# 1. Télécharger le backup depuis S3
aws s3 cp s3://cleanux-backups/cleanux-prod-2026-05-20-02-00-00.zip /tmp/

# 2. Décompresser
unzip /tmp/cleanux-prod-2026-05-20-02-00-00.zip -d /tmp/restore/

# 3. Restaurer DB
gunzip -c /tmp/restore/db-dumps/mysql.sql.gz | mysql -u root -p cleanux_prod

# 4. Restaurer files
rsync -av /tmp/restore/files/ /var/www/cleanux/storage/

# 5. Cache + permissions
cd /var/www/cleanux
php artisan config:clear
php artisan cache:clear
chown -R www-data:www-data storage/
```

## Tests dry-run en staging

Avant prod :
```bash
# Set env staging
APP_ENV=staging php artisan backup:run --disable-notifications
# Vérifier le zip généré
ls -lh storage/app/backups/
```

## Monitoring & alerts à brancher Sentry

Capturer les failures Spatie via :
```php
// app/Providers/AppServiceProvider.php
\Spatie\Backup\Events\BackupHasFailed::class => function ($event) {
    \Sentry\captureMessage("Backup failed: {$event->exception->getMessage()}", \Sentry\Severity::error());
},
```

## Estimation taille backup CleanUx prod (à confirmer)

- DB dump compressé : 200 MB - 2 GB selon volume
- Documents (storage/app) : 1-50 GB selon usage Fleet/Contracts/KYB
- Total : prévoir bucket S3 50-100 GB pour rétention 1 an

## Coût S3 estimé

- 100 GB S3 standard : ~$2.3/mois
- 100 GB S3 Glacier Deep Archive (long-term) : ~$0.1/mois
- Recommandation : Standard pour les 30 derniers jours, Glacier pour > 30j
