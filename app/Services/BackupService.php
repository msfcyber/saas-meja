<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

final class BackupService
{
    /**
     * @return array{backup_id: string, directory: string, database: string, assets: string, checksums: string, pruned: int}
     */
    public function create(?string $destination = null, ?string $connectionName = null): array
    {
        $connection = DB::connection($connectionName);

        if ($connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Automated application backup saat ini hanya mendukung SQLite.');
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
            $databasePath = $directory.DIRECTORY_SEPARATOR.'database.sqlite';
            $this->snapshotDatabase($connection, $databasePath);

            $assetsPath = $directory.DIRECTORY_SEPARATOR.'storage-app-public.zip';
            $this->archiveAssets($assetsPath);

            $checksumsPath = $directory.DIRECTORY_SEPARATOR.'SHA256SUMS';
            $this->writeChecksums($directory, $checksumsPath);
            $pruned = $this->prune($root);
        } catch (\Throwable $exception) {
            File::deleteDirectory($directory);

            throw $exception;
        }

        return [
            'backup_id' => $backupId,
            'directory' => $directory,
            'database' => $databasePath,
            'assets' => $assetsPath,
            'checksums' => $checksumsPath,
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
        $integrity = $this->verifySqlite($verifiedFiles['database']);
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
        $pdo = $connection->getPdo();
        $quotedTarget = $pdo->quote($target);

        if ($quotedTarget === false || $pdo->exec("VACUUM INTO {$quotedTarget}") === false) {
            throw new RuntimeException('Snapshot database SQLite gagal dibuat.');
        }

        if (! is_file($target) || (filesize($target) ?: 0) === 0) {
            throw new RuntimeException('Snapshot database SQLite tidak valid.');
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

        foreach (['database.sqlite', 'storage-app-public.zip'] as $requiredFile) {
            if (! isset($verified[$requiredFile])) {
                throw new RuntimeException("File backup {$requiredFile} tidak tercantum di manifest.");
            }
        }

        return [
            'database' => $verified['database.sqlite'],
            'assets' => $verified['storage-app-public.zip'],
        ];
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
            $restoredDatabase = $staging.DIRECTORY_SEPARATOR.'database.sqlite';

            if (! File::copy($databasePath, $restoredDatabase)) {
                throw new RuntimeException('Restore drill database gagal disalin.');
            }

            $this->verifySqlite($restoredDatabase);
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
            $source = storage_path('app/public');

            if (is_dir($source)) {
                $sourcePath = rtrim(realpath($source) ?: $source, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                );

                /** @var SplFileInfo $file */
                foreach ($files as $file) {
                    if (! $file->isFile()) {
                        continue;
                    }

                    $relativePath = substr($file->getPathname(), strlen($sourcePath));

                    if ($relativePath === '') {
                        continue;
                    }

                    if (! $archive->addFile($file->getPathname(), str_replace('\\', '/', $relativePath))) {
                        throw new RuntimeException('File asset gagal ditambahkan ke archive.');
                    }
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
