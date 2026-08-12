<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConcurrencyCheckCommandTest extends TestCase
{
    public function test_it_refuses_non_local_or_testing_environments(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->artisan('crm:concurrency-check')
            ->expectsOutputToContain('restricted to local/testing environments')
            ->assertFailed();
    }

    public function test_it_refuses_non_mysql_drivers(): void
    {
        config(['app.env' => 'testing', 'database.default' => 'sqlite']);

        $this->artisan('crm:concurrency-check')
            ->expectsOutputToContain('requires a MySQL or MariaDB connection')
            ->assertFailed();
    }

    public function test_hidden_worker_has_the_same_driver_guard(): void
    {
        config(['app.env' => 'testing', 'database.default' => 'sqlite']);

        $this->artisan('crm:concurrency-worker', [
            'scenario' => 'primary',
            'barrier' => sys_get_temp_dir(),
            'payload' => base64_encode(json_encode(['worker' => 1], JSON_THROW_ON_ERROR)),
        ])->expectsOutputToContain('requires a MySQL or MariaDB connection')->assertFailed();
    }
}
