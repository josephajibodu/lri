<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemException;
use Symfony\Component\Finder\SplFileInfo;

#[Signature('assets:upload {paths* : Files or folders to upload} {--disk=r2 : r2 for public site files, r2_private for private uploads} {--to= : The folder inside the bucket to put them in}')]
#[Description('Upload files to a Cloudflare R2 bucket and check each one arrived whole. Nothing on this computer is deleted.')]
class UploadAssets extends Command
{
    /**
     * Public files keep their names when replaced, so browsers only hold on to them for a week.
     */
    private const PublicCacheControl = 'public, max-age=604800';

    /**
     * Files that download when their link is opened, with the name to save them under.
     * Browsers ignore the link's own download attribute for a file on another domain.
     *
     * @var array<string, string>
     */
    private const DownloadNames = [
        'images/disco-run/flyer-full.jpg' => 'lri-silent-disco-run-flyer.jpg',
    ];

    public function handle(): int
    {
        $disk = (string) $this->option('disk');

        if (! in_array($disk, ['r2', 'r2_private'], true)) {
            $this->error('The disk must be r2 or r2_private.');

            return self::FAILURE;
        }

        if (blank(config("filesystems.disks.{$disk}.bucket"))) {
            $this->error("The {$disk} bucket is not set. Fill in the R2 settings in .env first.");

            return self::FAILURE;
        }

        $files = $this->filesToUpload();

        if ($files === null) {
            return self::FAILURE;
        }

        $failed = 0;

        foreach ($files as $key => $localPath) {
            try {
                $arrivedWhole = $this->upload($disk, $key, $localPath);
                $problem = 'the size in the bucket does not match';
            } catch (FilesystemException $exception) {
                $arrivedWhole = false;
                $problem = $exception->getMessage();
            }

            if ($arrivedWhole) {
                $this->line("Uploaded {$key}");
            } else {
                $this->error("Failed {$key}: {$problem}");
                $failed++;
            }
        }

        $uploaded = count($files) - $failed;
        $this->info("Uploaded {$uploaded} of ".count($files).' '.Str::plural('file', count($files))." to {$disk}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Every file to send, keyed by its path in the bucket. Null when a path given doesn't exist.
     *
     * @return array<string, string>|null
     */
    private function filesToUpload(): ?array
    {
        $folder = trim((string) $this->option('to'), '/');
        $prefix = $folder === '' ? '' : $folder.'/';
        $files = [];

        foreach ($this->argument('paths') as $path) {
            if (is_file($path)) {
                $files[$prefix.basename($path)] = $path;

                continue;
            }

            if (! is_dir($path)) {
                $this->error("{$path} is not a file or folder.");

                return null;
            }

            foreach (File::allFiles($path) as $file) {
                /** @var SplFileInfo $file */
                $files[$prefix.str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname())] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Send one file, then compare its size in the bucket with the size here.
     */
    private function upload(string $disk, string $key, string $localPath): bool
    {
        $stream = fopen($localPath, 'r');
        $storage = Storage::disk($disk);

        try {
            $storage->put($key, $stream, $this->uploadOptions($disk, $key));
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $storage->exists($key) && $storage->size($key) === filesize($localPath);
    }

    /**
     * @return array<string, string>
     */
    private function uploadOptions(string $disk, string $key): array
    {
        if ($disk !== 'r2') {
            return [];
        }

        $options = ['CacheControl' => self::PublicCacheControl];

        if (isset(self::DownloadNames[$key])) {
            $options['ContentDisposition'] = 'attachment; filename="'.self::DownloadNames[$key].'"';
        }

        return $options;
    }
}
