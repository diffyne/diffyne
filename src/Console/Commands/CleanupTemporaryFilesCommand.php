<?php

namespace Diffyne\Console\Commands;

use Diffyne\FileUpload\FileUploadService;
use Illuminate\Console\Command;

class CleanupTemporaryFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'diffyne:cleanup-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup old temporary files uploaded through Diffyne';

    /**
     * Execute the console command.
     */
    public function handle(FileUploadService $service): int
    {
        $this->info('Cleaning up old temporary files...');

        $deletedCount = $service->cleanupOldFiles();

        if ($deletedCount > 0) {
            $this->info("Deleted {$deletedCount} temporary file(s).");
        } else {
            $this->info('No old temporary files to clean up.');
        }

        return Command::SUCCESS;
    }
}

