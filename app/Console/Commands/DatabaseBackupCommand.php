<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'backup:database
                    {--path= : Custom backup path}
                    {--compress : Compress with gzip}';

    protected $description = 'Create a MySQL database backup';

    protected function getBackupPath(): string
    {
        return $this->option('path')
            || storage_path('backups/database');
    }

    public function handle(): int
    {
        $this->info('=== AlDia - Database Backup ===');
        $this->newLine();

        $backupDir = $this->getBackupPath();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $dbName = config('database.connections.mysql.database', 'aldia');
        $filename = "{$dbName}_{$timestamp}.sql";
        $filepath = "{$backupDir}/{$filename}";

        // Ensure backup directory exists
        File::ensureDirectoryExists($backupDir);

        $this->info("Database: {$dbName}");
        $this->info("Backup file: {$filepath}");
        $this->newLine();

        // Build mysqldump command
        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', 3306);
        $username = config('database.connections.mysql.username', 'root');
        $password = config('database.connections.mysql.password', '');

        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction --routines --triggers --events %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($dbName),
        );

        if ($password) {
            $cmd = str_replace('--user=', '--password='.escapeshellarg($password).' --user=', $cmd);
        }

        $this->info('Running mysqldump...');
        $result = Process::run($cmd);

        if ($result->failed()) {
            $this->error("Backup failed: {$result->errorOutput()}");

            return Command::FAILURE;
        }

        // Write SQL to file
        File::put($filepath, $result->output());

        $this->info("Backup created: {$filename}");

        // Compress if requested
        if ($this->option('compress')) {
            $gzFilepath = "{$filepath}.gz";
            $compressed = Process::run('gzip -c '.escapeshellarg($filepath).' > '.escapeshellarg($gzFilepath));

            if ($compressed->successful()) {
                File::delete($filepath);
                $filepath = $gzFilepath;
                $filename .= '.gz';
                $this->info("Compressed: {$filename}");
            } else {
                $this->warn('Compression failed, keeping uncompressed file');
            }
        }

        // Cleanup old backups (keep 7 days)
        $this->cleanupOldBackups($backupDir);

        $filesize = number_format(File::size($filepath) / 1024, 1);
        $this->newLine();
        $this->info("Done! File size: {$filesize} KB");

        return Command::SUCCESS;
    }

    protected function cleanupOldBackups(string $directory): void
    {
        $files = File::files($directory);
        $cutoff = now()->subDays(7);
        $deleted = 0;

        foreach ($files as $file) {
            if ($file->getMTime() < $cutoff->timestamp) {
                File::delete($file->getRealPath());
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->info("Cleaned up {$deleted} old backup(s) (> 7 days)");
        }
    }
}
