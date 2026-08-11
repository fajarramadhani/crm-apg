<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Division;
use App\Models\Role;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\ApplicationSystemSeeder;
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

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('tickets.attachment_disk', 'local'));

        $this->seed(RoleSeeder::class);
        $this->seed(ApplicationSystemSeeder::class);
        $this->application = Application::query()->where('code', 'HRIS')->firstOrFail();
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
                ...$this->payload(),
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
                ...$this->payload(),
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
            'application_module_id' => 1,
            'primary_pic_id' => 5,
            'secondary_pic_ids' => [5],
            'internal_note' => 'private',
        ];

        foreach ($forbiddenPayloads as $field => $value) {
            $response = $this->actingAs($this->requester)
                ->postJson('/api/v1/requester/tickets', [
                    ...$this->payload(),
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

    public function test_requester_cannot_bypass_contract_through_generic_ticket_endpoint(): void
    {
        $category = TicketCategory::query()->create(['code' => 'INCIDENT', 'name' => 'Incident', 'type' => 'incident', 'is_active' => true]);
        $this->actingAs($this->requester)->postJson('/api/v1/tickets', [
            'ticket_category_id' => $category->id,
            'application_id' => $this->application->id,
            'title' => 'Bypass attempt',
            'description' => 'Requester attempts generic endpoint.',
        ])->assertForbidden();
    }

    public function test_title_rejects_html_while_description_is_sanitized(): void
    {
        $this->actingAs($this->requester)->postJson('/api/v1/requester/tickets', [
            ...$this->payload(),
            'title' => '<script>alert(1)</script>',
            'description' => '<p><strong>Safe formatting</strong></p>',
        ])->assertUnprocessable()->assertJsonValidationErrors(['title']);

        $response = $this->actingAs($this->requester)->postJson('/api/v1/requester/tickets', [
            ...$this->payload(),
            'title' => 'Rich description security test',
            'description' => '<script>alert(1)</script><iframe src="https://example.com"></iframe><object data="x"></object><embed src="x"><img src="x" onerror="alert(2)"><p style="text-align: center" onclick="alert(3)"><strong>Safe</strong> <em>description</em> <a href="javascript:alert(4)" target="_blank">unsafe link</a> <a href="https://example.com" target="_blank">safe link</a></p><table onclick="alert(5)"><tbody><tr><th>Header</th><td>Value</td></tr></tbody></table>',
        ])->assertCreated();

        $description = $response->json('data.description');
        $this->assertStringContainsString('<strong>Safe</strong>', $description);
        $this->assertStringContainsString('<em>description</em>', $description);
        $this->assertStringContainsString('<table>', $description);
        $this->assertStringContainsString('<th>Header</th>', $description);
        $this->assertStringContainsString('href="https://example.com" target="_blank" rel="noopener noreferrer"', $description);
        $this->assertStringContainsString('style="text-align: center"', $description);
        $this->assertStringNotContainsStringIgnoringCase('<script', $description);
        $this->assertStringNotContainsStringIgnoringCase('<iframe', $description);
        $this->assertStringNotContainsStringIgnoringCase('<object', $description);
        $this->assertStringNotContainsStringIgnoringCase('<embed', $description);
        $this->assertStringNotContainsStringIgnoringCase('<img', $description);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $description);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $description);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $description);
    }

    private function payload(): array
    {
        return [
            'request_category' => 'error_bug',
            'application_id' => $this->application->id,
            'title' => 'Security Payload Test',
            'description' => 'Security payload validation.',
            'affected_url' => 'https://example.com/page',
            'attachments' => [UploadedFile::fake()->create('proof.png', 200, 'image/png')],
            'urgency' => 'medium',
        ];
    }
}
