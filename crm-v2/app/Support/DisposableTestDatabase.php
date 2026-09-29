<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DisposableTestDatabase
{
    public static function assertSafe(): void
    {
        $database = config('database.connections.mysql.database');
        if (! app()->environment('testing') || ! app()->runningInConsole() || app()->configurationIsCached()
            || config('database.default') !== 'mysql' || ! is_string($database)
            || ! str_ends_with($database, '_ci')
            || config('database.disposable_test_database') !== $database) {
            throw new RuntimeException('Destructive test cleanup requires testing, uncached config and an explicitly designated disposable MySQL _ci database.');
        }
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== $database
            || $connection->selectOne('SELECT DATABASE() AS selected_database')->selected_database !== $database) {
            throw new RuntimeException('The connected database is not the designated disposable test database.');
        }
    }
}
