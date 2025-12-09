<?php

namespace Diffyne\FileUpload;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    public function storeTemporary(UploadedFile $file, string $componentId): string
    {
        $disk = config('diffyne.file_upload.disk', 'local');
        $path = config('diffyne.file_upload.temporary_path', 'diffyne/temp');

        $extension = $file->getClientOriginalExtension();
        $filename = Str::random(40).($extension ? '.'.$extension : '');

        $storedPath = $file->storeAs(
            $path.'/'.$componentId,
            $filename,
            $disk
        );

        if (! $storedPath) {
            throw new \RuntimeException('Failed to store file');
        }

        return $componentId.':'.$filename;
    }

    public function getTemporaryPath(string $identifier): ?string
    {
        $parts = explode(':', $identifier, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$componentId, $filename] = $parts;
        $disk = config('diffyne.file_upload.disk', 'local');
        $path = config('diffyne.file_upload.temporary_path', 'diffyne/temp');

        $fullPath = $path.'/'.$componentId.'/'.$filename;

        return Storage::disk($disk)->exists($fullPath) ? $fullPath : null;
    }

    public function moveToPermanent(string $identifier, string $destinationPath, ?string $disk = null): ?string
    {
        $tempPath = $this->getTemporaryPath($identifier);

        if (! $tempPath) {
            return null;
        }

        $storageDisk = $disk ?? config('diffyne.file_upload.disk', 'local');
        $tempDisk = config('diffyne.file_upload.disk', 'local');

        if ($storageDisk === $tempDisk) {
            if (Storage::disk($tempDisk)->move($tempPath, $destinationPath)) {
                return $destinationPath;
            }
        }

        $content = Storage::disk($tempDisk)->get($tempPath);
        if ($content && Storage::disk($storageDisk)->put($destinationPath, $content)) {
            Storage::disk($tempDisk)->delete($tempPath);

            return $destinationPath;
        }

        return null;
    }

    public function deleteTemporary(string $identifier): bool
    {
        $path = $this->getTemporaryPath($identifier);

        if (! $path) {
            return false;
        }

        $disk = config('diffyne.file_upload.disk', 'local');

        return Storage::disk($disk)->delete($path);
    }

    /**
     * Cleanup temporary files older than the configured hours.
     *
     * @return int Number of files deleted
     */
    public function cleanupOldFiles(): int
    {
        $disk = config('diffyne.file_upload.disk', 'local');
        $path = config('diffyne.file_upload.temporary_path', 'diffyne/temp');
        $cleanupAfterHours = config('diffyne.file_upload.cleanup_after_hours', 24);

        if (! $cleanupAfterHours || $cleanupAfterHours <= 0) {
            return 0;
        }

        $cutoffTime = now()->subHours($cleanupAfterHours)->timestamp;
        $deletedCount = 0;

        // Get all directories in the temp path
        $directories = Storage::disk($disk)->directories($path);

        foreach ($directories as $directory) {
            // Get all files in this directory
            $files = Storage::disk($disk)->files($directory);

            foreach ($files as $file) {
                $lastModified = Storage::disk($disk)->lastModified($file);

                // Delete if file is older than cutoff time
                if ($lastModified < $cutoffTime) {
                    if (Storage::disk($disk)->delete($file)) {
                        $deletedCount++;
                    }
                }
            }

            // Remove empty directories
            $remainingFiles = Storage::disk($disk)->files($directory);
            if (empty($remainingFiles)) {
                Storage::disk($disk)->deleteDirectory($directory);
            }
        }

        return $deletedCount;
    }
}
