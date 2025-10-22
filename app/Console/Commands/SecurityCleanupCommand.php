<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SecurityCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:cleanup {--dry-run : Show what would be cleaned up without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired sessions, old audit logs, and other security-related data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('Running in dry-run mode. No data will be deleted.');
        }

        $this->info('Starting security cleanup...');

        // Clean up expired user sessions
        $this->cleanupExpiredSessions($dryRun);

        // Clean up old audit logs
        $this->cleanupOldAuditLogs($dryRun);

        // Clean up old password history
        $this->cleanupOldPasswordHistory($dryRun);

        // Clean up expired password reset tokens
        $this->cleanupExpiredPasswordResetTokens($dryRun);

        // Clean up expired personal access tokens
        $this->cleanupExpiredPersonalAccessTokens($dryRun);

        $this->info('Security cleanup completed!');
    }

    /**
     * Clean up expired user sessions.
     */
    protected function cleanupExpiredSessions(bool $dryRun): void
    {
        $this->info('Cleaning up expired user sessions...');

        $query = \App\Models\UserSession::where('expires_at', '<', now())
            ->orWhere('is_active', false);

        $count = $query->count();

        if ($count > 0) {
            if (!$dryRun) {
                $deleted = $query->delete();
                $this->info("Deleted {$deleted} expired user sessions.");
            } else {
                $this->info("Would delete {$count} expired user sessions.");
            }
        } else {
            $this->info('No expired user sessions to clean up.');
        }
    }

    /**
     * Clean up old audit logs.
     */
    protected function cleanupOldAuditLogs(bool $dryRun): void
    {
        $this->info('Cleaning up old audit logs...');

        $retentionDays = config('security.audit.retention_days', 365);
        $cutoffDate = now()->subDays($retentionDays);

        $query = \App\Models\AuditLog::where('created_at', '<', $cutoffDate);
        $count = $query->count();

        if ($count > 0) {
            if (!$dryRun) {
                $deleted = $query->delete();
                $this->info("Deleted {$deleted} audit logs older than {$retentionDays} days.");
            } else {
                $this->info("Would delete {$count} audit logs older than {$retentionDays} days.");
            }
        } else {
            $this->info('No old audit logs to clean up.');
        }
    }

    /**
     * Clean up old password history.
     */
    protected function cleanupOldPasswordHistory(bool $dryRun): void
    {
        $this->info('Cleaning up old password history...');

        $historyCount = config('security.password.history_count', 5);
        
        // Get users with more than the configured history count
        $users = \App\Models\User::withCount('passwordHistory')
            ->having('password_history_count', '>', $historyCount)
            ->get();

        $totalDeleted = 0;

        foreach ($users as $user) {
            $query = $user->passwordHistory()
                ->orderBy('created_at', 'desc')
                ->skip($historyCount);

            $count = $query->count();

            if ($count > 0) {
                if (!$dryRun) {
                    $deleted = $query->delete();
                    $totalDeleted += $deleted;
                } else {
                    $totalDeleted += $count;
                }
            }
        }

        if ($totalDeleted > 0) {
            if (!$dryRun) {
                $this->info("Deleted {$totalDeleted} old password history entries.");
            } else {
                $this->info("Would delete {$totalDeleted} old password history entries.");
            }
        } else {
            $this->info('No old password history to clean up.');
        }
    }

    /**
     * Clean up expired password reset tokens.
     */
    protected function cleanupExpiredPasswordResetTokens(bool $dryRun): void
    {
        $this->info('Cleaning up expired password reset tokens...');

        $expireMinutes = config('auth.passwords.users.expire', 60);
        $cutoffDate = now()->subMinutes($expireMinutes);

        $query = \DB::table('password_reset_tokens')
            ->where('created_at', '<', $cutoffDate);

        $count = $query->count();

        if ($count > 0) {
            if (!$dryRun) {
                $deleted = $query->delete();
                $this->info("Deleted {$deleted} expired password reset tokens.");
            } else {
                $this->info("Would delete {$count} expired password reset tokens.");
            }
        } else {
            $this->info('No expired password reset tokens to clean up.');
        }
    }

    /**
     * Clean up expired personal access tokens.
     */
    protected function cleanupExpiredPersonalAccessTokens(bool $dryRun): void
    {
        $this->info('Cleaning up expired personal access tokens...');

        $query = \Laravel\Sanctum\PersonalAccessToken::where('expires_at', '<', now());
        $count = $query->count();

        if ($count > 0) {
            if (!$dryRun) {
                $deleted = $query->delete();
                $this->info("Deleted {$deleted} expired personal access tokens.");
            } else {
                $this->info("Would delete {$count} expired personal access tokens.");
            }
        } else {
            $this->info('No expired personal access tokens to clean up.');
        }
    }
}
