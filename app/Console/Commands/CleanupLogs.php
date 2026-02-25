<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log as LaravelLog;

class CleanupLogs extends Command
{
    protected $signature = 'logs:cleanup';
    protected $description = 'Clean up logs older than 3 months';

    public function handle(): int
    {
        try {
            $this->info('Starting logs cleanup...');
            LaravelLog::info('Starting logs cleanup process');

            $cutoffDate = now()->subMonths(3);
            
            // Delete logs older than 3 months
            $deletedCount = Log::where('created_at', '<', $cutoffDate)->delete();

            $this->info("Deleted {$deletedCount} old logs");
            LaravelLog::info("Deleted {$deletedCount} logs older than {$cutoffDate}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Log cleanup failed: ' . $e->getMessage());
            LaravelLog::error('Log cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
} 