<?php

namespace Tests\Feature;

use App\Support\PerformanceCheckSafety;
use Tests\TestCase;

class PerformanceCheckCommandTest extends TestCase
{
    public function test_it_refuses_non_local_or_testing_environments(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->artisan('crm:performance-check', ['--confirm-disposable' => true])
            ->expectsOutputToContain('restricted to local/testing environments')
            ->assertFailed();
    }

    public function test_it_refuses_non_mysql_or_mariadb_drivers(): void
    {
        config(['app.env' => 'testing', 'database.default' => 'sqlite']);

        $this->artisan('crm:performance-check', ['--confirm-disposable' => true])
            ->expectsOutputToContain('requires a MySQL or MariaDB connection')
            ->assertFailed();
    }

    public function test_it_requires_explicit_disposable_acknowledgement(): void
    {
        config([
            'app.env' => 'testing',
            'database.default' => 'benchmark_guard_test',
            'database.connections.benchmark_guard_test.driver' => 'mysql',
        ]);

        $this->artisan('crm:performance-check')
            ->expectsOutputToContain('requires --confirm-disposable')
            ->assertFailed();
    }

    public function test_safety_accepts_testing_mysql_only_with_disposable_acknowledgement(): void
    {
        config([
            'app.env' => 'testing',
            'database.default' => 'benchmark_guard_test',
            'database.connections.benchmark_guard_test.driver' => 'mysql',
        ]);

        $this->assertNull(PerformanceCheckSafety::refusalReason(true));
    }

    public function test_it_refuses_a_non_empty_disposable_boundary(): void
    {
        $reason = PerformanceCheckSafety::disposableBoundaryReason(['users', 'tickets']);

        $this->assertSame(
            'Disposable safety condition failed: core tables must be empty (found data in users, tickets). No data was changed.',
            $reason,
        );
    }

    public function test_it_accepts_an_empty_disposable_boundary(): void
    {
        $this->assertNull(PerformanceCheckSafety::disposableBoundaryReason([]));
    }
}
