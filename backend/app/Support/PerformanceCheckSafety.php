<?php

namespace App\Support;

final class PerformanceCheckSafety
{
    public static function refusalReason(bool $disposableConfirmed): ?string
    {
        if (! app()->environment(['local', 'testing'])) {
            return 'crm:performance-check is restricted to local/testing environments.';
        }

        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return 'crm:performance-check requires a MySQL or MariaDB connection.';
        }

        if (! $disposableConfirmed) {
            return 'crm:performance-check requires --confirm-disposable to acknowledge destructive disposable-database use.';
        }

        return null;
    }

    /** @param list<string> $nonEmptyTables */
    public static function disposableBoundaryReason(array $nonEmptyTables): ?string
    {
        if ($nonEmptyTables === []) {
            return null;
        }

        return 'Disposable safety condition failed: core tables must be empty (found data in '.implode(', ', $nonEmptyTables).'). No data was changed.';
    }
}
