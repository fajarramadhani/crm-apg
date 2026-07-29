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

class RequesterTicketCreationTest extends TestCase
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

    public function test_requester_can_create_ticket_with_five_fields(): void
    {
        $file = UploadedFile::fake()->create('error_screenshot.png', 500, 'image/png');

        $response = $this->actingAs($this->requester)
            ->postJson('/api/v1/requester/tickets', [
                'title' => 'Portal asuransi gagal memproses submission',
                'description' => 'Muncul error 500 saat tombol simpan ditekan pada form polis.',
                'affected_url' => 'https://portal.example.com/submission/123',
                'reference' => 'SUB-998822',
                'attachments' => [$file],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Portal asuransi gagal memproses submission')
            ->assertJsonPath('data.affected_url', 'https://portal.example.com/submission/123')
            ->assertJsonPath('data.reference', 'SUB-998822')
            ->assertJsonPath('data.status', 'pending_validation');

        $this->assertDatabaseHas('tickets', [
            'title' => 'Portal asuransi gagal memproses submission',
            'affected_url' => 'https://portal.example.com/submission/123',
            'reference' => 'SUB-998822',
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
        ]);

        $this->assertDatabaseHas('ticket_attachments', [
            'original_name' => 'error_screenshot.png',
        ]);
    }

    public function test_requester_can_create_ticket_without_optional_reference(): void
    {
        $file = UploadedFile::fake()->create('log_document.pdf', 1000, 'application/pdf');

        $response = $this->actingAs($this->requester)
            ->postJson('/api/v1/requester/tickets', [
                'title' => 'Gagal cetak polis PDF',
                'description' => 'Sistem tidak merespons saat mengunduh berkas polis.',
                'affected_url' => 'https://portal.example.com/policy/print',
                'attachments' => [$file],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.reference', null);

        $this->assertDatabaseHas('tickets', [
            'title' => 'Gagal cetak polis PDF',
            'reference' => null,
        ]);
    }

    public function test_creation_fails_without_required_fields(): void
    {
        $response = $this->actingAs($this->requester)
            ->postJson('/api/v1/requester/tickets', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'description', 'affected_url', 'attachments']);
    }

    public function test_creation_fails_with_invalid_or_unsafe_urls(): void
    {
        $file = UploadedFile::fake()->create('doc.png', 100, 'image/png');

        $unsafeUrls = [
            'javascript:alert(1)',
            'file:///etc/passwd',
            'data:text/html,<script>alert(1)</script>',
            'ftp://example.com/file',
            'not-a-valid-url',
        ];

        foreach ($unsafeUrls as $unsafeUrl) {
            $response = $this->actingAs($this->requester)
                ->postJson('/api/v1/requester/tickets', [
                    'title' => 'Test URL Security',
                    'description' => 'Testing unsafe URL protocol.',
                    'affected_url' => $unsafeUrl,
                    'attachments' => [$file],
                ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['affected_url']);
        }
    }

    public function test_creation_fails_with_disallowed_file_types(): void
    {
        $forbiddenFiles = [
            UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload'),
            UploadedFile::fake()->create('script.sh', 100, 'text/x-shellscript'),
            UploadedFile::fake()->create('shell.php', 100, 'text/x-php'),
            UploadedFile::fake()->create('page.html', 100, 'text/html'),
        ];

        foreach ($forbiddenFiles as $forbiddenFile) {
            $response = $this->actingAs($this->requester)
                ->postJson('/api/v1/requester/tickets', [
                    'title' => 'Test File Whitelist',
                    'description' => 'Testing disallowed file extensions.',
                    'affected_url' => 'https://example.com/error',
                    'attachments' => [$forbiddenFile],
                ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['attachments.0']);
        }
    }

    public function test_creation_fails_with_oversized_file(): void
    {
        // 11MB file (exceeds 10MB limit)
        $largeFile = UploadedFile::fake()->create('huge_file.pdf', 11264, 'application/pdf');

        $response = $this->actingAs($this->requester)
            ->postJson('/api/v1/requester/tickets', [
                'title' => 'Test File Size',
                'description' => 'Testing file size limit.',
                'affected_url' => 'https://example.com/error',
                'attachments' => [$largeFile],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['attachments.0']);
    }

    public function test_creation_fails_if_requester_is_inactive(): void
    {
        $this->requester->update(['is_active' => false]);
        $file = UploadedFile::fake()->create('test.png', 100, 'image/png');

        $response = $this->actingAs($this->requester)
            ->postJson('/api/v1/requester/tickets', [
                'title' => 'Test Inactive User',
                'description' => 'Description test',
                'affected_url' => 'https://example.com/error',
                'attachments' => [$file],
            ]);

        $response->assertStatus(401);
    }
}
