declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Exception;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup';
    protected $description = 'Create a backup of the database';

    // Constants for backup settings
    private const BACKUP_DIR = 'backups';
    private const MAX_BACKUPS = 30; // Keep last 30 backups
    private const BACKUP_FILENAME_FORMAT = 'backup_Y-m-d_His';

    public function handle(): int
    {
        try {
            $this->info('Starting database backup...');
            Log::info('Starting database backup process');

            // Create backups directory if it doesn't exist
            if (!Storage::exists(self::BACKUP_DIR)) {
                Storage::makeDirectory(self::BACKUP_DIR);
                $this->info('Created backups directory');
            }

            // Generate backup filename
            $filename = Carbon::now()->format(self::BACKUP_FILENAME_FORMAT) . '.sql';
            $backupPath = storage_path('app/' . self::BACKUP_DIR . '/' . $filename);

            // Get database configuration
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');
            $dbHost = config('database.connections.mysql.host');

            // Construct mysqldump command
            $command = sprintf(
                'mysqldump -h %s -u %s -p%s %s > %s',
                escapeshellarg($dbHost),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName),
                escapeshellarg($backupPath)
            );

            // Execute backup command
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                throw new Exception('Database backup failed: ' . implode("\n", $output));
            }

            // Clean up old backups
            $this->cleanOldBackups();

            $this->info('Database backup completed successfully');
            Log::info('Database backup completed successfully', ['filename' => $filename]);

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Database backup failed: ' . $e->getMessage());
            Log::error('Database backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }

    private function cleanOldBackups(): void
    {
        try {
            $files = Storage::files(self::BACKUP_DIR);
            
            if (count($files) > self::MAX_BACKUPS) {
                // Sort files by name (which includes timestamp)
                sort($files);
                
                // Remove oldest files
                $filesToDelete = array_slice($files, 0, count($files) - self::MAX_BACKUPS);
                
                foreach ($filesToDelete as $file) {
                    Storage::delete($file);
                    $this->info("Deleted old backup: $file");
                    Log::info("Deleted old backup file", ['file' => $file]);
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to clean old backups', [
                'error' => $e->getMessage()
            ]);
            // Don't throw the exception as this is a cleanup task
        }
    }
} 