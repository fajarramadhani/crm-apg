<?php

namespace Tests\Feature;

use App\Console\Commands\SeedStage9BrowserFixturesCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedStage9BrowserFixturesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_refuses_non_mysql_and_non_disposable_databases(): void
    {
        $this->assertNotNull(SeedStage9BrowserFixturesCommand::safetyRefusal('staging', 'sqlite', ':memory:', SeedStage9BrowserFixturesCommand::CONFIRMATION));
        $this->assertNotNull(SeedStage9BrowserFixturesCommand::safetyRefusal('staging', 'mysql', 'crm_production', SeedStage9BrowserFixturesCommand::CONFIRMATION));
        $this->assertNotNull(SeedStage9BrowserFixturesCommand::safetyRefusal('production', 'mysql', 'crm_stage9_test', SeedStage9BrowserFixturesCommand::CONFIRMATION));
        $this->assertNotNull(SeedStage9BrowserFixturesCommand::safetyRefusal('staging', 'mysql', 'crm_stage9_test', ''));
        $this->assertNull(SeedStage9BrowserFixturesCommand::safetyRefusal('staging', 'mysql', 'crm_stage9_browser', SeedStage9BrowserFixturesCommand::CONFIRMATION));
    }

    public function test_fixture_identities_are_complete_and_reserved_for_testing(): void
    {
        $fixtures = SeedStage9BrowserFixturesCommand::fixtureUsers();

        $this->assertSame(
            ['requester', 'supervisor_it', 'pic_it_support', 'pic_it_develop', 'admin', 'it_lead', 'pic', 'qa'],
            array_values(array_column($fixtures, 'role')),
        );
        foreach ($fixtures as $fixture) {
            $this->assertStringEndsWith('@stage9.invalid', $fixture['email']);
            $this->assertStringStartsWith('Stage 9 ', $fixture['name']);
        }
    }

    public function test_command_refuses_the_automated_sqlite_test_database_before_seeding(): void
    {
        $this->artisan('stage9:seed-browser-fixtures', [
            '--confirm' => SeedStage9BrowserFixturesCommand::CONFIRMATION,
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
