<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequesterAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $requester1;

    private User $requester2;

    private User $supervisor;

    private Ticket $ticket1;

    private TicketAttachment $attachment1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake(config('tickets.attachment_disk', 'local'));

        $division = Division::create(['code' => 'IT', 'name' => 'Information Technology', 'is_active' => true]);

        $reqRole = Role::query()->where('key', 'requester')->firstOrFail();
        $supRole = Role::query()->where('key', 'supervisor_it')->firstOrFail();

        $this->requester1 = User::factory()->create(['role_id' => $reqRole->id, 'division_id' => $division->id]);
        $this->requester2 = User::factory()->create(['role_id' => $reqRole->id, 'division_id' => $division->id]);
        $this->supervisor = User::factory()->create(['role_id' => $supRole->id, 'division_id' => $division->id]);

        $this->ticket1 = Ticket::query()->create([
            'ticket_number' => 'TCK-2026-000001',
            'requester_id' => $this->requester1->id,
            'division_id' => $division->id,
            'current_division_id' => $division->id,
            'title' => 'Tiket Requester 1',
            'description' => 'Deskripsi tiket 1',
            'affected_url' => 'https://example.com/err',
            'status' => 'pending_validation',
        ]);

        $disk = config('tickets.attachment_disk', 'local');
        $filePath = "tickets/{$this->ticket1->id}/test.png";
        Storage::disk($disk)->put($filePath, 'fake content');

        $this->attachment1 = TicketAttachment::create([
            'ticket_id' => $this->ticket1->id,
            'uploaded_by' => $this->requester1->id,
            'original_name' => 'test.png',
            'stored_name' => 'test.png',
            'disk' => $disk,
            'path' => $filePath,
            'mime_type' => 'image/png',
            'size' => 100,
            'category' => 'attachment',
            'visibility' => 'requester',
        ]);
    }

    public function test_requester_can_view_own_ticket(): void
    {
        $response = $this->actingAs($this->requester1)
            ->getJson("/api/v1/tickets/{$this->ticket1->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->ticket1->id);
    }

    public function test_requester_cannot_view_other_requester_ticket(): void
    {
        $response = $this->actingAs($this->requester2)
            ->getJson("/api/v1/tickets/{$this->ticket1->id}");

        $response->assertStatus(403);
    }

    public function test_requester_can_download_own_attachment(): void
    {
        $response = $this->actingAs($this->requester1)
            ->getJson("/api/v1/tickets/{$this->ticket1->id}/attachments/{$this->attachment1->id}/download");

        $response->assertStatus(200);
    }

    public function test_requester_cannot_download_other_requester_attachment(): void
    {
        $response = $this->actingAs($this->requester2)
            ->getJson("/api/v1/tickets/{$this->ticket1->id}/attachments/{$this->attachment1->id}/download");

        $response->assertStatus(403);
    }

    public function test_supervisor_can_view_requester_ticket(): void
    {
        $response = $this->actingAs($this->supervisor)
            ->getJson("/api/v1/tickets/{$this->ticket1->id}");

        $response->assertStatus(200);
    }
}
