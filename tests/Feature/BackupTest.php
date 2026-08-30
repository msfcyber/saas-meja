<?php

use App\Services\BackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use ZipArchive;

test('backup service creates a database snapshot, asset archive, and checksum manifest', function () {
    $connectionName = 'backup_testing';
    $databasePath = storage_path('framework/testing/backup-source-'.Str::uuid().'.sqlite');
    $destination = storage_path('framework/testing/backups/'.Str::uuid());
    $assetName = 'backup-test-'.Str::uuid().'.txt';
    $assetPath = storage_path('app/public/'.$assetName);

    config([
        "database.connections.{$connectionName}" => [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'operations.backup.retention_days' => 30,
    ]);
    DB::purge($connectionName);
    File::ensureDirectoryExists(dirname($databasePath));
    File::put($databasePath, '');
    File::ensureDirectoryExists(dirname($assetPath));
    File::put($assetPath, 'backup sentinel');

    try {
        $source = DB::connection($connectionName)->getPdo();
        $source->exec('CREATE TABLE backup_probe (value TEXT NOT NULL)');
        $source->exec("INSERT INTO backup_probe (value) VALUES ('preserved')");

        $result = app(BackupService::class)->create($destination, $connectionName);
        $verification = app(BackupService::class)->verify($result['directory'], restoreDrill: true);
        $latest = app(BackupService::class)->latest($destination);

        expect(File::exists($result['database']))->toBeTrue()
            ->and(File::exists($result['assets']))->toBeTrue()
            ->and(File::exists($result['checksums']))->toBeTrue()
            ->and($result['pruned'])->toBe(0)
            ->and($verification['database_integrity'])->toBe('ok')
            ->and($verification['asset_entries'])->toBeGreaterThan(0)
            ->and($verification['restore_drill'])->toBeTrue()
            ->and($latest)->toBe($result['directory']);

        $snapshot = new PDO('sqlite:'.$result['database']);
        expect($snapshot->query('SELECT value FROM backup_probe')->fetchColumn())->toBe('preserved');
        $snapshot = null;

        $manifest = File::get($result['checksums']);
        expect($manifest)
            ->toContain(hash_file('sha256', $result['database']).'  database.sqlite')
            ->toContain(hash_file('sha256', $result['assets']).'  storage-app-public.zip');

        $archive = new ZipArchive;
        expect($archive->open($result['assets']))->toBeTrue()
            ->and($archive->getFromName($assetName))->toBe('backup sentinel')
            ->and($archive->locateName('.env'))->toBeFalse();
        $archive->close();
    } finally {
        DB::purge($connectionName);
        File::delete($assetPath);
        File::delete($databasePath);
        File::deleteDirectory($destination);
    }
});
