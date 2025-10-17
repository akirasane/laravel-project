<?php

namespace App\Console\Commands;

use App\Services\LogIntegrityService;
use Illuminate\Console\Command;

class VerifyLogIntegrity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:verify-integrity {--file=* : Specific log files to verify} {--report : Generate detailed report}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify the integrity of log files';

    /**
     * Execute the console command.
     */
    public function handle(LogIntegrityService $integrityService): int
    {
        $files = $this->option('file');
        $generateReport = $this->option('report');
        
        if ($generateReport) {
            $this->info('Generating comprehensive integrity report...');
            $report = $integrityService->generateIntegrityReport();
            
            $this->displayReport($report);
            
            return $report['invalid_files'] > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        if (empty($files)) {
            // Verify all log files
            $this->info('Verifying integrity of all log files...');
            $results = $integrityService->verifyAllLogs();
        } else {
            // Verify specified files
            $results = [];
            foreach ($files as $file) {
                $filePath = storage_path('logs/' . $file);
                $results[$file] = $integrityService->verifyLogIntegrity($filePath);
            }
        }

        $hasFailures = false;

        foreach ($results as $file => $result) {
            if (isset($result['error'])) {
                $this->warn("⚠️  {$file}: {$result['error']}");
            } elseif ($result['valid']) {
                $this->info("✅ {$file}: Integrity verified");
                if ($result['current_size'] > $result['original_size']) {
                    $newBytes = $result['current_size'] - $result['original_size'];
                    $this->line("   📈 File has grown by {$newBytes} bytes since last check");
                }
            } else {
                $this->error("❌ {$file}: Integrity violation detected!");
                $this->line("   Original hash: {$result['original_hash']}");
                $this->line("   Current hash:  {$result['current_hash']}");
                $hasFailures = true;
            }
        }

        if ($hasFailures) {
            $this->error('⚠️  Log integrity violations detected! Check security logs for details.');
            return Command::FAILURE;
        }

        $this->info('✅ All log files passed integrity verification.');
        return Command::SUCCESS;
    }

    /**
     * Display the integrity report.
     */
    private function displayReport(array $report): void
    {
        $this->info("📊 Log Integrity Report - {$report['timestamp']}");
        $this->line('');
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Files', $report['total_files']],
                ['Valid Files', $report['valid_files']],
                ['Invalid Files', $report['invalid_files']],
                ['Files Without Records', $report['files_without_records']],
            ]
        );

        if ($report['invalid_files'] > 0) {
            $this->line('');
            $this->error('❌ Files with integrity violations:');
            
            foreach ($report['details'] as $file => $details) {
                if ($details['status'] === 'invalid') {
                    $this->line("   • {$file}");
                    $this->line("     Original: {$details['original_hash']}");
                    $this->line("     Current:  {$details['current_hash']}");
                }
            }
        }

        if ($report['files_without_records'] > 0) {
            $this->line('');
            $this->warn('⚠️  Files without integrity records:');
            
            foreach ($report['details'] as $file => $details) {
                if ($details['status'] === 'no_record') {
                    $this->line("   • {$file}: {$details['error']}");
                }
            }
        }

        $this->line('');
        $this->info('📁 Report saved to: storage/logs/integrity/reports/');
    }
}