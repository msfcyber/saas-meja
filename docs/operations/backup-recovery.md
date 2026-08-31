# Backup & Recovery Runbook

This runbook covers the MVP baseline for protecting the Meja database and
tenant-owned assets. It is written for an operator-managed production
environment; no backup provider or credential is configured in this
repository.

## Scope

- Back up the relational database, including migration state.
- Back up `storage/app/public`, which contains product images and QR artifacts.
- Do not include `.env`, payment credentials, session secrets, or application
  logs in an application backup archive.
- Store backups outside the application host with encryption and restricted
  access.

## Targets

Set the production targets before launch. A reasonable starting point for the
MVP is an RPO of 24 hours and an RTO of 4 hours. Reduce both targets when the
business and infrastructure can support them.

## Database Backup

### Application-managed snapshot

The repository includes an opt-in `ops:backup` command. SQLite deployments use
a consistent `VACUUM INTO` snapshot. MySQL and MariaDB deployments use
`mysqldump --defaults-extra-file` with a single transaction and gzip the SQL
stream. Both modes also create a ZIP archive of the configured public asset disk
and a `SHA256SUMS` manifest. The command removes incomplete output when any
step fails and prunes only timestamped backup directories older than the
configured retention period.

```dotenv
OPS_BACKUP_ENABLED=true
OPS_BACKUP_DESTINATION=/var/backups/meja
OPS_BACKUP_RETENTION_DAYS=30
OPS_BACKUP_RESTORE_DRILL_ENABLED=true
OPS_BACKUP_REMOTE_ENABLED=true
OPS_BACKUP_REMOTE_DISK=s3-backup
OPS_BACKUP_MYSQL_CREDENTIALS_FILE=/run/secrets/meja-mysql.cnf
```

When enabled, the Laravel scheduler runs the command daily at 02:00 UTC. A
manual snapshot can be created with `php artisan ops:backup`. When
`OPS_BACKUP_REMOTE_ENABLED=true`, the command uploads every file to the
configured private filesystem disk and verifies that each remote object exists;
the local destination remains a staging location.

Verify a backup before relying on it for recovery. The optional restore drill
copies the database, validates the SQLite database or MySQL gzip stream, and
extracts the asset archive into an isolated temporary directory, then removes
the staging data without changing the live application:

```sh
php artisan ops:backup:verify /var/backups/meja/20260830_090324Z --restore-drill
```

Run this verification after backup format or storage changes. With
`OPS_BACKUP_RESTORE_DRILL_ENABLED=true`, the scheduler runs
`ops:backup:verify-latest` on the first day of each quarter. Record its output
and measured restore time with the operations record.

### MySQL or MariaDB

The application-managed command uses a protected client option file or secret
mount. Do not put the database password in a shell command, repository file, or
backup filename. The option file must contain the credentials for the database
connection used by the scheduler.

```sh
BACKUP_DIR="/var/backups/meja/$(date -u +%Y-%m-%dT%H%M%SZ)"
install -d -m 700 "$BACKUP_DIR"

mysqldump \
  --defaults-extra-file="/run/secrets/meja-mysql.cnf" \
  --single-transaction \
  --routines \
  --triggers \
  --hex-blob \
  --host="${DB_HOST}" \
  --port="${DB_PORT:-3306}" \
  "${DB_DATABASE}" | gzip -9 > "$BACKUP_DIR/database.sql.gz"

gzip -t "$BACKUP_DIR/database.sql.gz"
sha256sum "$BACKUP_DIR/database.sql.gz" > "$BACKUP_DIR/SHA256SUMS"
```

Run this from a host that can reach the database and upload the resulting
directory to versioned, encrypted object storage. Keep at least daily copies
for 30 days and monthly copies for the agreed retention period.

### SQLite local or single-host deployment

Stop application writes before copying the database. The SQLite backup command
creates a consistent snapshot while the source database is available.

```sh
BACKUP_DIR="/var/backups/meja/$(date -u +%Y-%m-%dT%H%M%SZ)"
install -d -m 700 "$BACKUP_DIR"
sqlite3 database/database.sqlite ".backup '$BACKUP_DIR/database.sqlite'"
sha256sum "$BACKUP_DIR/database.sqlite" > "$BACKUP_DIR/SHA256SUMS"
```

For production, prefer MySQL or MariaDB with managed snapshots and point-in-
time recovery rather than relying on a live SQLite file copy.

## Asset Backup

Back up tenant-owned assets separately from the database. For a local disk,
create an archive that contains only the public application assets:

```sh
tar -C storage/app -czf "$BACKUP_DIR/storage-app-public.tar.gz" public
sha256sum "$BACKUP_DIR/storage-app-public.tar.gz" >> "$BACKUP_DIR/SHA256SUMS"
```

For object storage, enable bucket versioning, server-side encryption, and a
retention policy. Verify that the Laravel filesystem disk and the backup job
use the same bucket and prefix.

## Restore Procedure

Restore into a new or isolated environment first. Never overwrite the only
production copy during an incident response.

1. Put the application into maintenance mode or route traffic away from the
   target instance.
2. Verify the backup checksum and, for compressed files, run `gzip -t`.
3. Restore the database into an empty database or a disposable recovery
   instance:

```sh
gzip -dc database.sql.gz | mysql \
  --defaults-extra-file="/run/secrets/meja-mysql.cnf" \
  --host="${DB_HOST}" \
  --port="${DB_PORT:-3306}" \
  "${DB_DATABASE}"
```

4. Restore `storage/app/public` to the configured filesystem disk.
5. Deploy the matching application version and run `php artisan migrate:status`.
6. Run `php artisan storage:link` when the deployment uses the public local
   disk.
7. Verify authentication, tenant switching, QR menu access, report access,
   and receipt rendering in the isolated environment.
8. For payment recovery, use the provider reconciliation flow. Do not replay
   arbitrary webhook payloads without signature verification and an incident
   record.
9. Record the restore timestamp, backup identifier, checksums, observed data
   loss, and follow-up actions.

## Restore Drill

Run a restore drill at least quarterly and after any database or storage
topology change. The drill is successful only when the isolated environment
can pass the relevant feature tests and a representative QR-to-receipt smoke
flow. Record the measured RTO and the oldest recoverable data point so the
configured targets remain honest.

## Operational Checklist

- Confirm the backup job exits non-zero on database, archive, checksum, or
  upload failure.
- Alert when the latest successful backup exceeds the RPO window.
- Monitor backup size and object-storage retention for unexpected changes.
- Restrict restore credentials to the incident or operations team.
- Rotate backup encryption keys according to the infrastructure policy.
- Keep this runbook alongside deployment documentation and review it before
  launch.
