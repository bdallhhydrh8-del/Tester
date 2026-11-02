<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use ZipArchive;

class RunBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Back up the database and the uploaded files';

    public function handle(): int
    {
        $config = config('database.connections.' . config('database.default'));

        if (! in_array($config['driver'] ?? '', ['mysql', 'mariadb'], true)) {
            $this->error('Backups need a MySQL database.');

            return self::FAILURE;
        }

        $dir = rtrim(config('tester.backup_path'), '/\\');

        if (! is_dir($dir) && ! @mkdir($dir, 0750, true)) {
            $this->error("Cannot create the backup folder: {$dir}");

            return self::FAILURE;
        }

        $stamp = now()->format('Y-m-d_His');

        if (! $this->dumpDatabase($config, "{$dir}/db-{$stamp}.sql.gz")) {
            return self::FAILURE;
        }

        if (! $this->zipUploads("{$dir}/uploads-{$stamp}.zip")) {
            return self::FAILURE;
        }

        $this->deleteOldBackups($dir);
        $this->info("Backup finished: {$dir}");

        return self::SUCCESS;
    }

    private function dumpDatabase(array $config, string $file): bool
    {
        $process = new Process([
            config('tester.mysqldump_path'),
            '--host=' . $config['host'],
            '--port=' . $config['port'],
            '--user=' . $config['username'],
            '--single-transaction',
            '--routines',
            '--default-character-set=utf8mb4',
            $config['database'],
        ], null, ['MYSQL_PWD' => (string) ($config['password'] ?? '')]); // keeps the password out of the command line

        $process->setTimeout(3600);

        $gz = gzopen($file, 'wb9');
        $process->run(function ($type, $buffer) use ($gz) {
            if ($type === Process::OUT) {
                gzwrite($gz, $buffer);
            }
        });
        gzclose($gz);

        if (! $process->isSuccessful()) {
            @unlink($file);
            $this->error('Database dump failed: ' . trim($process->getErrorOutput()));

            return false;
        }

        $this->line('Database saved: ' . basename($file));

        return true;
    }

    private function zipUploads(string $zipFile): bool
    {
        $source = rtrim(config('tester.uploads_path'), '/\\');

        if (! is_dir($source)) {
            $this->line('No uploads folder yet, skipping files.');

            return true;
        }

        if (! class_exists(ZipArchive::class)) {
            $this->error('The PHP zip extension is not enabled.');

            return false;
        }

        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('Cannot create the uploads archive.');

            return false;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($source) + 1));
            }
        }

        $zip->close();
        $this->line('Uploads saved: ' . basename($zipFile));

        return true;
    }

    private function deleteOldBackups(string $dir): void
    {
        $limit = now()->subDays(config('tester.backup_keep_days'))->getTimestamp();

        foreach (array_merge(glob("{$dir}/db-*.sql.gz") ?: [], glob("{$dir}/uploads-*.zip") ?: []) as $old) {
            if (filemtime($old) < $limit) {
                @unlink($old);
            }
        }
    }
}