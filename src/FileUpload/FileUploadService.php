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

        // Store metadata including original filename
        $metadata = [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_at' => now()->toIso8601String(),
        ];

        $metadataPath = $path.'/'.$componentId.'/'.$filename.'.meta';
        $metadataJson = json_encode($metadata);
        if ($metadataJson !== false) {
            Storage::disk($disk)->put($metadataPath, $metadataJson);
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

    public function moveToPermanent(string $identifier, string $destinationPath, ?string $disk = null, bool $useOriginalName = false): ?string
    {
        $tempPath = $this->getTemporaryPath($identifier);

        if (! $tempPath) {
            return null;
        }

        // If useOriginalName is true, replace the filename in destinationPath with original name
        if ($useOriginalName) {
            $originalName = $this->getTemporaryFileOriginalName($identifier);
            if ($originalName) {
                $directory = dirname($destinationPath);
                $destinationPath = $directory !== '.' ? $directory.'/'.$originalName : $originalName;
            }
        }

        $storageDisk = $disk ?? config('diffyne.file_upload.disk', 'local');
        $tempDisk = config('diffyne.file_upload.disk', 'local');

        if ($storageDisk === $tempDisk) {
            if (Storage::disk($tempDisk)->move($tempPath, $destinationPath)) {
                // Delete metadata file after successful move
                $metadataPath = $tempPath.'.meta';
                Storage::disk($tempDisk)->delete($metadataPath);

                return $destinationPath;
            }
        }

        $content = Storage::disk($tempDisk)->get($tempPath);
        if ($content && Storage::disk($storageDisk)->put($destinationPath, $content)) {
            Storage::disk($tempDisk)->delete($tempPath);
            // Delete metadata file after successful copy
            $metadataPath = $tempPath.'.meta';
            Storage::disk($tempDisk)->delete($metadataPath);

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
        $deleted = Storage::disk($disk)->delete($path);

        // Also delete metadata file if it exists
        $metadataPath = $path.'.meta';
        Storage::disk($disk)->delete($metadataPath);

        return $deleted;
    }

    /**
     * Get metadata for a temporary file.
     *
     * @return array<string, mixed>|null
     */
    public function getTemporaryFileMetadata(string $identifier): ?array
    {
        $path = $this->getTemporaryPath($identifier);

        if (! $path) {
            return null;
        }

        $disk = config('diffyne.file_upload.disk', 'local');
        $metadataPath = $path.'.meta';

        if (! Storage::disk($disk)->exists($metadataPath)) {
            return null;
        }

        $metadataJson = Storage::disk($disk)->get($metadataPath);
        if (! $metadataJson) {
            return null;
        }

        $metadata = json_decode($metadataJson, true);

        return is_array($metadata) ? $metadata : null;
    }

    /**
     * Get the original filename for a temporary file.
     */
    public function getTemporaryFileOriginalName(string $identifier): ?string
    {
        $metadata = $this->getTemporaryFileMetadata($identifier);

        return $metadata['original_name'] ?? null;
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
                    $metadataPath = $file.'.meta';
                    Storage::disk($disk)->delete($metadataPath);
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
