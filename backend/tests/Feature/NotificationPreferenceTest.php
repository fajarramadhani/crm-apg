<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    }

    public function test_user_can_view_own_preferences()
    {
        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => NotificationType::TicketSubmitted->value,
            'in_app_enabled' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/notification-preferences');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.notification_type', NotificationType::TicketSubmitted->value);
    }

    public function test_user_cannot_mute_critical_notifications()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/notification-preferences/'.NotificationType::SlaBreached->value, [
            'in_app_enabled' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Critical notifications cannot be disabled or muted.']);
    }

    public function test_user_cannot_mute_more_than_30_days()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/notification-preferences/'.NotificationType::TicketSubmitted->value, [
            'in_app_enabled' => true,
            'muted_until' => now()->addDays(31)->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_user_cannot_update_preference_with_invalid_type()
    {
        $user = User::factory()->create(['role_id' => 1]);

        $response = $this->actingAs($user)->putJson('/api/v1/notification-preferences/invalid_type_123', [
            'in_app_enabled' => false,
        ]);

        $response->assertStatus(422);
    }
}
