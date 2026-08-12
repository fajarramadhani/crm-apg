<?php

namespace App\Support;

final class ConcurrencyCheckSafety
{
    public static function refusalReason(): ?string
    {
        if (! app()->environment(['local', 'testing'])) {
            return 'crm:concurrency-check is restricted to local/testing environments.';
        }

        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return 'crm:concurrency-check requires a MySQL or MariaDB connection.';
        }

        return null;
    }
}
