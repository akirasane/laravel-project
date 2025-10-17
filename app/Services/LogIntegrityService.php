<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LogIntegrityService
{
    private string $hashAlgorithm = 'sha256';
    private string $integrityPath = 'logs/integrity';

    /**
     * Generate integrity hash for a log file.
     */
    public function generateLogHash(string $logPath): string
    {
        if (!file_exists($logPath)) {
            throw new \InvalidArgumentException("Log file does not exist: {$logPath}");
        }

        $content = file_get_contents($logPath);
        $timestamp = Carbon::now()->toISOString();
        $metadata = [
            'file_path' => $logPath,
            'file_size' => filesize($logPath),
            'timestamp' => $timestamp,
            'algorithm' => $this->hashAlgorithm,
        ];

        // Create hash of content + metadata
        $hashInput = $content . json_encode($metadata);
        $hash = hash($this->hashAlgorithm, $hashInput);

        // Store integrity record
        $this->storeIntegrityRecord($logPath, $hash, $metadata);

        return $hash;
    }

    /**
     * Verify log file integrity.
     */
    public function verifyLogIntegrity(string $logPath): array
    {
        if (!file_exists($logPath)) {
            return [
                'valid' => false,
                'error' => 'Log file does not exist',
            ];
        }

        $integrityRecord = $this->getLatestIntegrityRecord($logPath);
        
        if (!$integrityRecord) {
            return [
                'valid' => false,
                'error' => 'No integrity record found',
            ];
        }

        // Recalculate hash
        $currentContent = file_get_contents($logPath);
        $currentSize = filesize($logPath);
        
        // Check if file has grown (new entries added)
        if ($currentSize > $integrityRecord['metadata']['file_size']) {
            // Extract original content up to the recorded size
            $originalContent = substr($currentContent, 0, $integrityRecord['metadata']['file_size']);
            $hashInput = $originalContent . json_encode($integrityRecord['metadata']);
        } else {
            $hashInput = $currentContent . json_encode($integrityRecord['metadata']);
        }

        $currentHash = hash($this->hashAlgorithm, $hashInput);

        $isValid = hash_equals($integrityRecord['hash'], $currentHash);

        return [
            'valid' => $isValid,
            'original_hash' => $integrityRecord['hash'],
            'current_hash' => $currentHash,
            'original_size' => $integrityRecord['metadata']['file_size'],
            'current_size' => $currentSize,
            'timestamp' => $integrityRecord['metadata']['timestamp'],
        ];
    }

    /**
     * Verify integrity of all log files.
     */
    public function verifyAllLogs(): array
    {
        $logDirectory = storage_path('logs');
        $results = [];

        if (!is_dir($logDirectory)) {
            return ['error' => 'Log directory does not exist'];
        }

        $logFiles = glob($logDirectory . '/*.log');

        foreach ($logFiles as $logFile) {
            $relativePath = str_replace($logDirectory . '/', '', $logFile);
            $results[$relativePath] = $this->verifyLogIntegrity($logFile);
        }

        return $results;
    }

    /**
     * Store integrity record.
     */
    private function storeIntegrityRecord(string $logPath, string $hash, array $metadata): void
    {
        $recordPath = $this->getIntegrityRecordPath($logPath);
        
        $record = [
            'hash' => $hash,
            'metadata' => $metadata,
            'created_at' => Carbon::now()->toISOString(),
        ];

        // Ensure directory exists
        $directory = dirname($recordPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Append to integrity log (keep history)
        file_put_contents($recordPath, json_encode($record) . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Get latest integrity record for a log file.
     */
    private function getLatestIntegrityRecord(string $logPath): ?array
    {
        $recordPath = $this->getIntegrityRecordPath($logPath);
        
        if (!file_exists($recordPath)) {
            return null;
        }

        $lines = file($recordPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (empty($lines)) {
            return null;
        }

        // Get the last line (most recent record)
        $lastLine = end($lines);
        
        return json_decode($lastLine, true);
    }

    /**
     * Get integrity record file path.
     */
    private function getIntegrityRecordPath(string $logPath): string
    {
        $logName = basename($logPath);
        $integrityDir = storage_path($this->integrityPath);
        
        return $integrityDir . '/' . $logName . '.integrity';
    }

    /**
     * Generate daily integrity report.
     */
    public function generateIntegrityReport(): array
    {
        $verificationResults = $this->verifyAllLogs();
        $report = [
            'timestamp' => Carbon::now()->toISOString(),
            'total_files' => count($verificationResults),
            'valid_files' => 0,
            'invalid_files' => 0,
            'files_without_records' => 0,
            'details' => [],
        ];

        foreach ($verificationResults as $file => $result) {
            if (isset($result['error'])) {
                $report['files_without_records']++;
                $report['details'][$file] = [
                    'status' => 'no_record',
                    'error' => $result['error'],
                ];
            } elseif ($result['valid']) {
                $report['valid_files']++;
                $report['details'][$file] = [
                    'status' => 'valid',
                    'size_change' => $result['current_size'] - $result['original_size'],
                ];
            } else {
                $report['invalid_files']++;
                $report['details'][$file] = [
                    'status' => 'invalid',
                    'original_hash' => $result['original_hash'],
                    'current_hash' => $result['current_hash'],
                ];

                // Log integrity violation
                Log::channel('security')->critical('Log integrity violation detected', [
                    'file' => $file,
                    'original_hash' => $result['original_hash'],
                    'current_hash' => $result['current_hash'],
                ]);
            }
        }

        // Store the report
        $reportPath = storage_path($this->integrityPath . '/reports/integrity-report-' . Carbon::now()->format('Y-m-d') . '.json');
        $reportDir = dirname($reportPath);
        
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));

        return $report;
    }

    /**
     * Clean up old integrity records.
     */
    public function cleanupOldRecords(int $retentionDays = 365): void
    {
        $cutoffDate = Carbon::now()->subDays($retentionDays);
        $integrityDir = storage_path($this->integrityPath);
        
        if (!is_dir($integrityDir)) {
            return;
        }

        $files = glob($integrityDir . '/*.integrity');
        
        foreach ($files as $file) {
            $this->cleanupIntegrityFile($file, $cutoffDate);
        }

        // Clean up old reports
        $reportsDir = $integrityDir . '/reports';
        if (is_dir($reportsDir)) {
            $reportFiles = glob($reportsDir . '/*.json');
            foreach ($reportFiles as $reportFile) {
                if (filemtime($reportFile) < $cutoffDate->timestamp) {
                    unlink($reportFile);
                }
            }
        }
    }

    /**
     * Clean up old records from an integrity file.
     */
    private function cleanupIntegrityFile(string $filePath, Carbon $cutoffDate): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $keptLines = [];

        foreach ($lines as $line) {
            $record = json_decode($line, true);
            if ($record && isset($record['created_at'])) {
                $recordDate = Carbon::parse($record['created_at']);
                if ($recordDate->isAfter($cutoffDate)) {
                    $keptLines[] = $line;
                }
            }
        }

        // Rewrite file with kept lines
        if (count($keptLines) !== count($lines)) {
            file_put_contents($filePath, implode("\n", $keptLines) . "\n");
        }
    }
}