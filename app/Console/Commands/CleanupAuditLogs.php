<?php

namespace App\Console\Commands;

use App\Services\AuditTrailService;
use App\Services\LogIntegrityService;
use Illuminate\Console\Command;

class CleanupAuditLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:cleanup {--days=365 : Number of days to retain logs} {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old audit logs and integrity records';

    /**
     * Execute the console command.
     */
    public function handle(AuditTrailService $auditService, LogIntegrityService $integrityService): int
    {
        $retentionDays = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("Cleaning up logs older than {$retentionDays} days...");
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No files will be deleted');
        }

        // Clean up audit logs
        if (!$dryRun) {
            $auditService->cleanupOldLogs();
            $this->info('✅ Audit logs cleanup completed');
        } else {
            $this->info('📋 Would clean up audit logs older than ' . $retentionDays . ' days');
        }

        // Clean up integrity records
        if (!$dryRun) {
            $integrityService->cleanupOldRecords($retentionDays);
            $this->info('✅ Integrity records cleanup completed');
        } else {
            $this->info('📋 Would clean up integrity records older than ' . $retentionDays . ' days');
        }

        // Show log directory sizes
        $this->displayLogDirectoryInfo();

        return Command::SUCCESS;
    }

    /**
     * Display information about log directories.
     */
    private function displayLogDirectoryInfo(): void
    {
        $this->line('');
        $this->info('📁 Log Directory Information:');

        $directories = [
            'Main Logs' => storage_path('logs'),
            'Integrity Records' => storage_path('logs/integrity'),
        ];

        $data = [];
        
        foreach ($directories as $name => $path) {
            if (is_dir($path)) {
                $size = $this->getDirectorySize($path);
                $fileCount = $this->getFileCount($path);
                
                $data[] = [
                    $name,
                    $this->formatBytes($size),
                    $fileCount . ' files',
                ];
            } else {
                $data[] = [$name, 'Directory not found', '0 files'];
            }
        }

        $this->table(['Directory', 'Size', 'Files'], $data);
    }

    /**
     * Get the total size of a directory.
     */
    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Get the number of files in a directory.
     */
    private function getFileCount(string $directory): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Format bytes into human-readable format.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}