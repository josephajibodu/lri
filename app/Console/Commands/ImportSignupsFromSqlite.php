<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('signups:import-sqlite {path? : The SQLite file to copy from, database/database.sqlite by default} {--dry-run : Count what would be copied without saving anything}')]
#[Description('Copy the organiser logins, birthdays and barracks registrations saved in the old SQLite database into the current database')]
class ImportSignupsFromSqlite extends Command
{
    /**
     * The connection name the old SQLite file is opened under.
     */
    public const SourceConnection = 'sqlite_signups';

    /**
     * Each table copied, with the column that identifies the same person in both databases.
     * A row whose identifying value is already in the current database is left alone, so running the import twice is safe.
     *
     * @var array<string, string>
     */
    private const Tables = [
        'users' => 'email',
        'birthdays' => 'email',
        'barracks_registrations' => 'phone',
    ];

    /**
     * Rows keep their own timestamps, photo paths and password hashes; only the ids are new, so they never clash.
     */
    public function handle(): int
    {
        $path = $this->argument('path') ?? database_path('database.sqlite');

        if (! is_file($path)) {
            $this->components->error("There is no SQLite file at {$path}.");

            return self::FAILURE;
        }

        if (DB::connection()->getDriverName() === 'sqlite' && realpath((string) DB::connection()->getDatabaseName()) === realpath($path)) {
            $this->components->error('The app is still using this SQLite file. Point DB_CONNECTION at MySQL first.');

            return self::FAILURE;
        }

        config(['database.connections.'.self::SourceConnection => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        $source = DB::connection(self::SourceConnection);
        $counts = [];

        DB::transaction(function () use ($source, &$counts): void {
            foreach (self::Tables as $table => $identifyingColumn) {
                if (! $source->getSchemaBuilder()->hasTable($table)) {
                    $counts[] = [$table, 0, 0];

                    continue;
                }

                $alreadyHere = DB::table($table)->pluck($identifyingColumn)->flip();
                $copied = 0;
                $skipped = 0;

                foreach ($source->table($table)->orderBy('id')->cursor() as $row) {
                    $row = (array) $row;

                    if ($alreadyHere->has($row[$identifyingColumn])) {
                        $skipped++;

                        continue;
                    }

                    unset($row['id']);

                    if (! $this->option('dry-run')) {
                        DB::table($table)->insert($row);
                    }

                    $alreadyHere->put($row[$identifyingColumn], true);
                    $copied++;
                }

                $counts[] = [$table, $copied, $skipped];
            }
        });

        DB::purge(self::SourceConnection);

        $this->table(['Table', $this->option('dry-run') ? 'Would copy' : 'Copied', 'Already here'], $counts);

        if ($this->option('dry-run')) {
            $this->components->info('Dry run: nothing was saved.');
        }

        return self::SUCCESS;
    }
}
