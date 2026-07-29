<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequesterSecurityPayloadTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private Division $division;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('tickets.attachment_disk', 'local'));

        $this->seed(RoleSeeder::class);
        $this->division = Division::create(['code' => 'IT', 'name' => 'Information Technology', 'is_active' => true]);

        $role = Role::query()->where('key', 'requester')->firstOrFail();

        $this->requester = User::factory()->create([
            'role_id' => $role->id,
            'division_id' => $this->division->id,
            'is_active' => true,
        ]);
    }

    public function test_payload_with_forbidden_requester_id_is_rejected_422(): void
    {
        $file = UploadedFile::fake()->create('proof.png', 200, 'image/png');

        $response = $this->actingAs($this->requester)
            ->postJson('/api/v1/requester/tickets', [
                'title' => 'Security Payload Test',
                'description' => 'Attempting to inject requester_id.',
                'affected_url' => 'https://example.com/page',
                'attachments' => [$file],
                'requester_id' => 999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['requester_id']);
    }

    public function test_payload_with_forbidden_status_is_rejected_422(): void
    {
        $file = UploadedFile::fake()->create('proof.png', 200, 'image/png');

        $response = $this->actingAs($this->requester)
            ->postJson('/api/v1/requester/tickets', [
                'title' => 'Security Payload Test',
                'description' => 'Attempting to set status directly.',
                'affected_url' => 'https://example.com/page',
                'attachments' => [$file],
                'status' => 'closed',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_payload_with_forbidden_technical_fields_is_rejected_422(): void
    {
        $file = UploadedFile::fake()->create('proof.png', 200, 'image/png');

        $forbiddenPayloads = [
            'pic_user_id' => 5,
            'assigned_to' => 5,
            'workflow_id' => 1,
            'approver_id' => 2,
            'requested_priority_id' => 1,
            'final_priority_id' => 1,
            'ticket_category_id' => 1,
            'application_id' => 1,
            'application_module_id' => 1,
        ];

        foreach ($forbiddenPayloads as $field => $value) {
            $response = $this->actingAs($this->requester)
                ->postJson('/api/v1/requester/tickets', [
                    'title' => 'Security Injection Test',
                    'description' => "Attempting to inject {$field}.",
                    'affected_url' => 'https://example.com/page',
                    'attachments' => [$file],
                    $field => $value,
                ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors([$field]);
        }
    }
}
