<?php

namespace App\Console\Commands;

use App\Services\LogIntegrityService;
use Illuminate\Console\Command;

class GenerateLogIntegrityHashes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:generate-integrity-hashes {--file=* : Specific log files to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate integrity hashes for log files';

    /**
     * Execute the console command.
     */
    public function handle(LogIntegrityService $integrityService): int
    {
        $files = $this->option('file');
        
        if (empty($files)) {
            // Process all log files
            $logDirectory = storage_path('logs');
            $files = glob($logDirectory . '/*.log');
        } else {
            // Process specified files
            $files = array_map(function ($file) {
                return storage_path('logs/' . $file);
            }, $files);
        }

        $this->info('Generating integrity hashes for log files...');
        
        foreach ($files as $file) {
            if (!file_exists($file)) {
                $this->error("File not found: {$file}");
                continue;
            }

            try {
                $hash = $integrityService->generateLogHash($file);
                $this->info("Generated hash for " . basename($file) . ": {$hash}");
            } catch (\Exception $e) {
                $this->error("Failed to generate hash for " . basename($file) . ": " . $e->getMessage());
            }
        }

        $this->info('Integrity hash generation completed.');
        
        return Command::SUCCESS;
    }
}