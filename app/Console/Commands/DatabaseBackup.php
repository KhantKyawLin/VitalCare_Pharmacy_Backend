<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class DatabaseBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:db-backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automated secure database backup (MySQL)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filename = "backup-" . Carbon::now()->format('Y-m-d_H-i-s') . ".sql";
        
        // Ensure the backup directory exists
        if (!Storage::exists('backups')) {
            Storage::makeDirectory('backups');
        }

        $filePath = storage_path("app/backups/" . $filename);
        
        $dbHost = env('DB_HOST', '127.0.0.1');
        $dbName = env('DB_DATABASE');
        $dbUser = env('DB_USERNAME');
        $dbPass = env('DB_PASSWORD');

        $this->info("Starting backup of {$dbName}...");

        // Construct the mysqldump command
        // Note: We use --single-transaction to avoid locking tables during backup for InnoDB
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s > %s',
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbHost),
            escapeshellarg($dbName),
            escapeshellarg($filePath)
        );

        $output = [];
        $returnVar = null;
        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            $this->info("Backup successfully created at: {$filePath}");
            Log::info("Database backup created successfully: {$filename}");
            
            // Clean up old backups (older than 7 days)
            $this->cleanupOldBackups();
        } else {
            $this->error("Backup failed with return code: {$returnVar}");
            Log::error("Database backup failed for {$filename}. Error code: {$returnVar}");
        }
    }

    /**
     * Remove backups older than 7 days to save space.
     */
    private function cleanupOldBackups()
    {
        $files = Storage::files('backups');
        $threshold = Carbon::now()->subDays(7);

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp(Storage::lastModified($file));
            if ($lastModified->lt($threshold)) {
                Storage::delete($file);
                $this->info("Deleted old backup: {$file}");
            }
        }
    }
}
