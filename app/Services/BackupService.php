<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

final class BackupService
{
    /**
     * @return array{backup_id: string, directory: string, database: string, database_driver: string, assets: string, checksums: string, remote_path: string|null, pruned: int}
     */
    public function create(?string $destination = null, ?string $connectionName = null): array
    {
        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new RuntimeException("Automated application backup belum mendukung driver {$driver}.");
        }

        $root = $this->resolveDestination(
            $destination ?? (string) config('operations.backup.destination', storage_path('backups')),
        );
        $backupId = now()->utc()->format('Ymd_His\\Z');
        $directory = $root.DIRECTORY_SEPARATOR.$backupId;

        if (is_dir($directory)) {
            $backupId .= '_'.Str::lower(Str::random(6));
            $directory = $root.DIRECTORY_SEPARATOR.$backupId;
        }

        $this->ensureDirectory($directory);

        try {
            $databaseFilename = $driver === 'sqlite' ? 'database.sqlite' : 'database.sql.gz';
            $databasePath = $directory.DIRECTORY_SEPARATOR.$databaseFilename;
            $this->snapshotDatabase($connection, $databasePath);

            $assetsPath = $directory.DIRECTORY_SEPARATOR.'storage-app-public.zip';
            $this->archiveAssets($assetsPath);

            $checksumsPath = $directory.DIRECTORY_SEPARATOR.'SHA256SUMS';
            $this->writeChecksums($directory, $checksumsPath);
            $remotePath = $this->uploadRemote($directory, $backupId);
            $pruned = $this->prune($root);
        } catch (\Throwable $exception) {
            File::deleteDirectory($directory);

            throw $exception;
        }

        return [
            'backup_id' => $backupId,
            'directory' => $directory,
            'database' => $databasePath,
            'database_driver' => $driver,
            'assets' => $assetsPath,
            'checksums' => $checksumsPath,
            'remote_path' => $remotePath,
            'pruned' => $pruned,
        ];
    }

    /**
     * @return array{backup_id: string, files: int, asset_entries: int, database_integrity: string, restore_drill: bool}
     */
    public function verify(string $backupDirectory, bool $restoreDrill = false): array
    {
        $directory = $this->resolveDestination($backupDirectory);

        if (! is_dir($directory)) {
            throw new RuntimeException('Direktori backup tidak ditemukan.');
        }

        $verifiedFiles = $this->verifyManifest($directory);
        $integrity = $this->verifyDatabase($verifiedFiles['database']);
        $assetEntries = $this->verifyArchive($verifiedFiles['assets']);

        if ($restoreDrill) {
            $this->runRestoreDrill($verifiedFiles['database'], $verifiedFiles['assets']);
        }

        return [
            'backup_id' => basename(rtrim($directory, '\\/')),
            'files' => count($verifiedFiles),
            'asset_entries' => $assetEntries,
            'database_integrity' => $integrity,
            'restore_drill' => $restoreDrill,
        ];
    }

    public function latest(?string $destination = null): string
    {
        $root = $this->resolveDestination(
            $destination ?? (string) config('operations.backup.destination', storage_path('backups')),
        );
        $candidates = [];

        foreach (File::directories($root) as $directory) {
            if (preg_match('/^\d{8}_\d{6}Z(?:_[a-z0-9]+)?$/', basename($directory)) === 1) {
                $candidates[] = $directory;
            }
        }

        if ($candidates === []) {
            throw new RuntimeException('Backup yang dapat diverifikasi tidak ditemukan.');
        }

        usort($candidates, static function (string $left, string $right): int {
            return (filemtime($right) ?: 0) <=> (filemtime($left) ?: 0);
        });

        return $candidates[0];
    }

    private function snapshotDatabase(Connection $connection, string $target): void
    {
        if ($connection->getDriverName() !== 'sqlite') {
            $this->snapshotMysql($connection, $target);

            return;
        }

        $pdo = $connection->getPdo();
        $quotedTarget = $pdo->quote($target);

        if ($quotedTarget === false || $pdo->exec("VACUUM INTO {$quotedTarget}") === false) {
            throw new RuntimeException('Snapshot database SQLite gagal dibuat.');
        }

        if (! is_file($target) || (filesize($target) ?: 0) === 0) {
            throw new RuntimeException('Snapshot database SQLite tidak valid.');
        }
    }

    private function snapshotMysql(Connection $connection, string $target): void
    {
        $credentialsFile = (string) config('operations.backup.mysql_credentials_file');
        $config = $connection->getConfig();
        $database = trim((string) ($config['database'] ?? ''));

        if ($database === '' || ! is_file($credentialsFile)) {
            throw new RuntimeException('Konfigurasi credential mysqldump tidak ditemukan.');
        }

        $process = new Process([
            (string) config('operations.backup.mysql_dump_binary', 'mysqldump'),
            '--defaults-extra-file='.$credentialsFile,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--hex-blob',
            '--host='.(string) ($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? '3306'),
            $database,
        ], base_path());
        $process->setTimeout((float) config('operations.backup.mysql_dump_timeout', 900));
        $stream = gzopen($target, 'wb9');

        if ($stream === false) {
            throw new RuntimeException('File backup MySQL tidak dapat dibuat.');
        }

        try {
            $exitCode = $process->run(function (string $type, string $buffer) use ($stream): void {
                if ($type === Process::OUT && gzwrite($stream, $buffer) === false) {
                    throw new RuntimeException('Output mysqldump tidak dapat ditulis.');
                }
            });
        } finally {
            gzclose($stream);
        }

        if ($exitCode !== 0) {
            throw new RuntimeException('mysqldump gagal membuat snapshot database.');
        }

        if (! is_file($target) || (filesize($target) ?: 0) === 0) {
            throw new RuntimeException('Snapshot database MySQL tidak valid.');
        }
    }

    /**
     * @return array{database: string, assets: string}
     */
    private function verifyManifest(string $directory): array
    {
        $manifestPath = $directory.DIRECTORY_SEPARATOR.'SHA256SUMS';

        if (! is_file($manifestPath)) {
            throw new RuntimeException('Manifest checksum backup tidak ditemukan.');
        }

        $lines = file($manifestPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new RuntimeException('Manifest checksum backup tidak dapat dibaca.');
        }

        $verified = [];

        foreach ($lines as $line) {
            [$expectedHash, $filename] = array_pad(explode('  ', $line, 2), 2, null);

            if (! is_string($expectedHash)
                || ! is_string($filename)
                || preg_match('/\A[a-f0-9]{64}\z/D', $expectedHash) !== 1
                || $filename === ''
                || str_contains($filename, '/')
                || str_contains($filename, '\\')
                || $filename === 'SHA256SUMS') {
                throw new RuntimeException('Format manifest checksum backup tidak valid.');
            }

            $path = $directory.DIRECTORY_SEPARATOR.$filename;
            $actualHash = is_file($path) ? hash_file('sha256', $path) : false;

            if ($actualHash === false || ! hash_equals($expectedHash, $actualHash)) {
                throw new RuntimeException("Checksum backup tidak cocok untuk {$filename}.");
            }

            $verified[$filename] = $path;
        }

        $databaseFilename = isset($verified['database.sqlite'])
            ? 'database.sqlite'
            : (isset($verified['database.sql.gz']) ? 'database.sql.gz' : null);

        if ($databaseFilename === null) {
            throw new RuntimeException('File database backup tidak tercantum di manifest.');
        }

        if (! isset($verified['storage-app-public.zip'])) {
            throw new RuntimeException('File backup storage-app-public.zip tidak tercantum di manifest.');
        }

        return [
            'database' => $verified[$databaseFilename],
            'assets' => $verified['storage-app-public.zip'],
        ];
    }

    private function verifyDatabase(string $databasePath): string
    {
        return str_ends_with($databasePath, '.sql.gz')
            ? $this->verifyGzip($databasePath)
            : $this->verifySqlite($databasePath);
    }

    private function verifyGzip(string $databasePath): string
    {
        $stream = gzopen($databasePath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Archive database MySQL tidak dapat dibuka.');
        }

        try {
            while (true) {
                $chunk = gzread($stream, 1024 * 1024);

                if ($chunk === false) {
                    throw new RuntimeException('Archive database MySQL tidak dapat dibaca.');
                }

                if ($chunk === '') {
                    break;
                }
            }

        } finally {
            gzclose($stream);
        }

        return 'gzip-ok';
    }

    private function verifySqlite(string $databasePath): string
    {
        $database = new PDO('sqlite:'.$databasePath);
        $query = $database->query('PRAGMA integrity_check');
        $integrity = $query === false ? false : $query->fetchColumn();
        $database = null;

        if ($integrity !== 'ok') {
            throw new RuntimeException('Integrity check database SQLite gagal.');
        }

        return $integrity;
    }

    private function verifyArchive(string $archivePath, ?string $extractTo = null): int
    {
        $archive = new ZipArchive;
        $opened = $archive->open($archivePath);

        if ($opened !== true) {
            throw new RuntimeException('Archive asset tenant tidak valid.');
        }

        try {
            $entries = $archive->numFiles;

            for ($index = 0; $index < $entries; $index++) {
                $name = $archive->getNameIndex($index);

                if ($name === false || ! $this->isSafeArchivePath($name)) {
                    throw new RuntimeException('Archive asset memiliki path yang tidak aman.');
                }
            }

            if ($extractTo !== null) {
                $this->ensureDirectory($extractTo);

                if (! $archive->extractTo($extractTo)) {
                    throw new RuntimeException('Restore archive asset tenant gagal.');
                }
            }

            if (! $archive->close()) {
                throw new RuntimeException('Archive asset tenant gagal diverifikasi.');
            }
        } catch (\Throwable $exception) {
            $archive->close();

            throw $exception;
        }

        return $entries;
    }

    private function runRestoreDrill(string $databasePath, string $archivePath): void
    {
        $staging = sys_get_temp_dir().DIRECTORY_SEPARATOR.'meja-restore-'.Str::lower(Str::random(12));
        $this->ensureDirectory($staging);

        try {
            $restoredDatabase = $staging.DIRECTORY_SEPARATOR.basename($databasePath);

            if (! File::copy($databasePath, $restoredDatabase)) {
                throw new RuntimeException('Restore drill database gagal disalin.');
            }

            $this->verifyDatabase($restoredDatabase);
            $this->verifyArchive($archivePath, $staging.DIRECTORY_SEPARATOR.'storage-app-public');
        } finally {
            File::deleteDirectory($staging);
        }
    }

    private function isSafeArchivePath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        $segments = explode('/', $normalized);

        return $normalized !== ''
            && ! str_starts_with($normalized, '/')
            && preg_match('/\A[A-Za-z]:\//', $normalized) !== 1
            && ! str_contains($normalized, "\0")
            && ! in_array('..', $segments, true);
    }

    private function archiveAssets(string $target): void
    {
        $archive = new ZipArchive;
        $opened = $archive->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new RuntimeException('Archive asset tenant gagal dibuat.');
        }

        try {
            $disk = Storage::disk('public');

            foreach ($disk->allFiles() as $relativePath) {
                $contents = $disk->get($relativePath);

                if (! $archive->addFromString($relativePath, $contents)) {
                    throw new RuntimeException('File asset gagal ditambahkan ke archive.');
                }
            }

            if (! $archive->close()) {
                throw new RuntimeException('Archive asset tenant gagal disimpan.');
            }
        } catch (\Throwable $exception) {
            $archive->close();
            File::delete($target);

            throw $exception;
        }
    }

    private function writeChecksums(string $directory, string $target): void
    {
        $lines = [];

        foreach (File::files($directory) as $file) {
            $hash = hash_file('sha256', $file->getPathname());

            if ($hash === false) {
                throw new RuntimeException('Checksum backup gagal dibuat.');
            }

            $lines[] = $hash.'  '.$file->getFilename();
        }

        sort($lines, SORT_STRING);

        if (File::put($target, implode(PHP_EOL, $lines).PHP_EOL) === false) {
            throw new RuntimeException('Manifest checksum backup gagal disimpan.');
        }
    }

    private function uploadRemote(string $directory, string $backupId): ?string
    {
        if (! (bool) config('operations.backup.remote_enabled', false)) {
            return null;
        }

        $disk = Storage::disk((string) config('operations.backup.remote_disk', 's3-backup'));
        $prefix = trim((string) config('operations.backup.remote_prefix', 'meja'), '/');
        $remoteDirectory = trim($prefix.'/'.$backupId, '/');

        if (str_contains($remoteDirectory, '..')) {
            throw new RuntimeException('Prefix remote backup tidak aman.');
        }

        foreach (File::files($directory) as $file) {
            $stream = fopen($file->getPathname(), 'rb');

            if ($stream === false) {
                throw new RuntimeException('File backup tidak dapat dibaca untuk upload.');
            }

            $remoteFile = $remoteDirectory.'/'.$file->getFilename();

            try {
                if (! $disk->put($remoteFile, $stream) || ! $disk->exists($remoteFile)) {
                    throw new RuntimeException('Upload backup off-host gagal diverifikasi.');
                }
            } finally {
                fclose($stream);
            }
        }

        return $remoteDirectory;
    }

    private function prune(string $root): int
    {
        $retentionDays = max(1, (int) config('operations.backup.retention_days', 30));
        $cutoff = now()->subDays($retentionDays)->getTimestamp();
        $deleted = 0;

        foreach (File::directories($root) as $directory) {
            $name = basename($directory);
            $modifiedAt = filemtime($directory);

            if (! preg_match('/^\d{8}_\d{6}Z(?:_[a-z0-9]+)?$/', $name)
                || $modifiedAt === false
                || $modifiedAt >= $cutoff) {
                continue;
            }

            File::deleteDirectory($directory);
            $deleted++;
        }

        return $deleted;
    }

    private function resolveDestination(string $destination): string
    {
        $destination = trim($destination);

        if ($destination === '') {
            throw new RuntimeException('Lokasi destination backup belum dikonfigurasi.');
        }

        return $this->isAbsolutePath($destination) ? $destination : base_path($destination);
    }

    private function ensureDirectory(string $directory): void
    {
        if ((! is_dir($directory) && ! mkdir($directory, 0700, true)) && ! is_dir($directory)) {
            throw new RuntimeException('Direktori backup tidak dapat dibuat.');
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
