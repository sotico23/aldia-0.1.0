<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CleanupTempDataCommand extends Command
{
    protected $signature = 'cleanup:temp-data
                    {--sessions-days=2 : Delete sessions older than N days}
                    {--cache-prefix= : Redis cache prefix to flush}
                    {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Clean up temporary data, expired sessions, stale cache, and old logs';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $totalCleaned = 0;

        $this->info('=== AlDia - Temp Data Cleanup ===');
        if ($dryRun) {
            $this->warn('DRY RUN mode - no data will be deleted');
        }
        $this->newLine();

        // 1. Clean expired sessions from database
        $totalCleaned += $this->cleanSessions($dryRun);

        // 2. Clean failed jobs table
        $totalCleaned += $this->cleanFailedJobs($dryRun);

        // 3. Clean old cache entries (Redis)
        $totalCleaned += $this->cleanStaleCache($dryRun);

        // 4. Clean old application logs
        $totalCleaned += $this->cleanOldLogs($dryRun);

        // 5. Clean temporary files
        $totalCleaned += $this->cleanTempFiles($dryRun);

        $this->newLine();
        $this->info("Cleanup complete. Total items cleaned: {$totalCleaned}");

        return Command::SUCCESS;
    }

    protected function cleanSessions(bool $dryRun): int
    {
        $days = (int) $this->option('sessions-days');
        $cutoff = now()->subDays($days);
        $count = DB::table('sessions')->where('last_activity', '<', $cutoff->timestamp)->count();

        if ($count > 0) {
            if ($dryRun) {
                $this->info("[sessions] Would delete {$count} expired session(s) (older than {$days} days)");
            } else {
                DB::table('sessions')->where('last_activity', '<', $cutoff->timestamp)->delete();
                $this->info("[sessions] Deleted {$count} expired session(s)");
            }
        } else {
            $this->info('[sessions] No expired sessions to clean');
        }

        return $count;
    }

    protected function cleanFailedJobs(bool $dryRun): int
    {
        $count = DB::table('failed_jobs')->count();

        if ($count > 0) {
            if ($dryRun) {
                $this->info("[failed_jobs] Would delete {$count} failed job(s)");
            } else {
                DB::table('failed_jobs')->delete();
                $this->info("[failed_jobs] Deleted {$count} failed job(s)");
            }
        } else {
            $this->info('[failed_jobs] No failed jobs to clean');
        }

        return $count;
    }

    protected function cleanStaleCache(bool $dryRun): int
    {
        // Only clean if Redis is the cache driver
        if (config('cache.default') !== 'redis') {
            $this->info('[cache] Skipping - cache driver is '.config('cache.default').' (not redis)');

            return 0;
        }

        $prefix = $this->option('cache-prefix') || config('database.redis.options.prefix', '');
        if (! $prefix) {
            $this->warn('[cache] No Redis prefix specified, skipping cache cleanup');

            return 0;
        }

        try {
            $redis = app('redis')->connection('cache');
            $keys = $redis->keys("{$prefix}*");
            $count = count($keys);

            if ($count > 0) {
                if ($dryRun) {
                    $this->info("[cache] Would flush {$count} Redis key(s) with prefix: {$prefix}");
                } else {
                    $redis->del($keys);
                    $this->info("[cache] Flushed {$count} Redis key(s)");
                }
            } else {
                $this->info('[cache] No Redis keys to flush');
            }

            return $count;
        } catch (\Throwable $e) {
            $this->error("[cache] Redis cleanup failed: {$e->getMessage()}");

            return 0;
        }
    }

    protected function cleanOldLogs(bool $dryRun): int
    {
        $logDir = storage_path('logs');
        $logFiles = File::files($logDir);
        $cutoff = now()->subDays(14);
        $deleted = 0;

        foreach ($logFiles as $file) {
            if ($file->getExtension() !== 'log') {
                continue;
            }
            if ($file->getMTime() < $cutoff->timestamp) {
                if ($dryRun) {
                    $this->info("[logs] Would delete: {$file->getFilename()}");
                } else {
                    File::delete($file->getRealPath());
                    $this->info("[logs] Deleted: {$file->getFilename()}");
                }
                $deleted++;
            }
        }

        if ($deleted === 0) {
            $this->info('[logs] No old log files to clean');
        }

        return $deleted;
    }

    protected function cleanTempFiles(bool $dryRun): int
    {
        $tempDirs = [
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];

        $deleted = 0;

        foreach ($tempDirs as $dir) {
            if (! File::isDirectory($dir)) {
                continue;
            }

            $files = File::files($dir);
            foreach ($files as $file) {
                // Skip very recent files (last 1 hour)
                if ($file->getMTime() > now()->subHour()->timestamp) {
                    continue;
                }

                if ($dryRun) {
                    $this->info("[temp] Would delete: {$file->getRealPath()}");
                } else {
                    File::delete($file->getRealPath());
                }
                $deleted++;
            }
        }

        if ($deleted === 0) {
            $this->info('[temp] No stale temp files to clean');
        } else {
            $this->info("[temp] Cleaned {$deleted} temp file(s)");
        }

        return $deleted;
    }
}
