# Production Deployment

The repository includes a reproducible Docker baseline for the production
topology: PHP-FPM behind Nginx, MySQL, Redis, one Reverb server, one queue
worker, and one Laravel scheduler. Production secrets must be supplied through the deployment secret
manager or an operator-managed `.env`; never commit them.

## Configuration

Start from the example file and provide a real application key and database
credentials:

```sh
cp .env.example .env
php artisan key:generate
```

For a production object-storage deployment, set these values in the secret
environment:

```dotenv
FILESYSTEM_PUBLIC_DRIVER=s3
FILESYSTEM_PUBLIC_BUCKET=meja-assets
FILESYSTEM_PUBLIC_ENDPOINT=https://s3.example.invalid
FILESYSTEM_PUBLIC_USE_PATH_STYLE_ENDPOINT=true
FILESYSTEM_PUBLIC_URL=https://assets.example.invalid
OPS_BACKUP_REMOTE_ENABLED=true
OPS_BACKUP_REMOTE_DISK=s3-backup
AWS_BACKUP_BUCKET=meja-backups
AWS_BACKUP_SERVER_SIDE_ENCRYPTION=AES256
```

Use a separate bucket or prefix for backups. Configure bucket versioning,
restricted write/read policies, and an off-host retention policy at the object
storage provider.

## Start

Build the application image, then run migrations once before accepting traffic:

```sh
docker compose build app
docker compose run --rm app php artisan migrate --force
docker compose up -d app reverb worker scheduler
```

The web container serves port `8080` internally. Set `APP_PORT` to expose it on
the host or put an external TLS-terminating load balancer in front of it.
The Reverb container serves WebSocket traffic on port `8080` and maps to
`REVERB_EXTERNAL_PORT` on the host. Put the public WebSocket hostname behind the
TLS-terminating proxy and set the public Reverb values before building the image;
Compose forwards them as Vite build arguments:

```dotenv
REVERB_HOST=ws.example.com
REVERB_EXTERNAL_PORT=443
REVERB_SCHEME=https
```

The service container overrides `REVERB_HOST` with its internal Docker hostname,
while the compiled frontend keeps the public hostname and port.

The frontend build runs Wayfinder generation in a PHP build stage before Vite,
so a clean CI checkout does not depend on ignored generated route files.

## Queue And Scheduler

The worker uses Redis directly:

```sh
docker compose logs -f worker
docker compose exec app php artisan ops:health --json
```

The current Laravel 13 dependency graph does not have a stable Horizon release
whose `illuminate/*` constraints include v13, so this deployment uses
`queue:work redis` plus the existing queue monitoring and health checks. Add
Horizon only after a compatible release is available and pin that release in
`composer.json`.

The scheduler must have exactly one active instance. It runs payment
reconciliation, payment expiry, subscription expiry, queue monitoring, and any
enabled backup jobs.

The Reverb process must have exactly one active instance per node unless Redis
scaling is explicitly configured. Check it with:

```sh
docker compose logs -f reverb
```

## Backup

For MySQL/MariaDB, mount the operator-managed `OPS_BACKUP_MYSQL_CREDENTIALS_FILE`
secret into the scheduler container. The backup command invokes `mysqldump`
with `--defaults-extra-file`, creates a gzip snapshot and public-asset archive,
writes checksums, and uploads the bundle to the configured private backup disk
when `OPS_BACKUP_REMOTE_ENABLED=true`.

```sh
docker compose exec scheduler php artisan ops:backup
docker compose exec scheduler php artisan ops:backup:verify /var/backups/meja/<backup-id> --restore-drill
```

Use `docs/operations/backup-recovery.md` for restore order, RPO/RTO recording,
and the required isolated restore drill. A successful upload is not a restore
test; verify the checksum and restore into a disposable target at least
quarterly.
