<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Division;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ApplicationSystemSeeder;
use Database\Seeders\OfficeSeeder;
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

    public function test_requester_can_create_error_bug_ticket_with_new_required_fields(): void
    {
        $file = UploadedFile::fake()->create('error_screenshot.png', 500, 'image/png');

        $response = $this->actingAs($this->requester)
            ->postJson('/api/v1/requester/tickets', [
                ...$this->payload('error_bug', 'high'),
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
            'request_category' => 'error_bug',
            'application_id' => $this->application->id,
            'urgency' => 'high',
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
                ...$this->payload('request', 'medium'),
                'title' => 'Gagal cetak polis PDF',
                'description' => 'Sistem tidak merespons saat mengunduh berkas polis.',
                'attachments' => [$file],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.reference', null);

        $this->assertDatabaseHas('tickets', [
            'title' => 'Gagal cetak polis PDF',
            'reference' => null,
        ]);
    }

    public function test_office_based_requester_without_division_can_create_ticket(): void
    {
        $this->seed(OfficeSeeder::class);
        $office = Office::query()->pusat()->firstOrFail();
        $this->requester->update([
            'division_id' => null,
            'branch_id' => null,
            'office_id' => $office->id,
        ]);

        $response = $this->actingAs($this->requester)->postJson('/api/v1/requester/tickets', [
            ...$this->payload('other', 'low'),
            'title' => 'Gagal memproses submission',
            'description' => 'Sistem gagal memproses submission dari akun kantor pusat.',
            'affected_url' => 'https://dev.hris.example.test/submission',
            'attachments' => [UploadedFile::fake()->create('error.png', 500, 'image/png')],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Gagal memproses submission')
            ->assertJsonPath('data.office.name', 'Kantor Pusat')
            ->assertJsonPath('data.division', null);

        $ticketId = $response->json('data.id');
        $this->actingAs($this->requester)->getJson("/api/v1/tickets/{$ticketId}")
            ->assertOk()
            ->assertJsonPath('data.office.name', 'Kantor Pusat')
            ->assertJsonPath('data.division', null)
            ->assertJsonPath('data.current_division', null)
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.release_owner', null);

        $this->assertDatabaseHas('tickets', [
            'requester_id' => $this->requester->id,
            'office_id' => $office->id,
            'division_id' => null,
            'current_division_id' => null,
        ]);
    }

    public function test_requester_without_office_and_division_is_rejected(): void
    {
        $this->requester->update(['division_id' => null, 'office_id' => null]);

        $this->actingAs($this->requester)->postJson('/api/v1/requester/tickets', [
            ...$this->payload(),
            'title' => 'Organization missing',
            'description' => 'Requester tidak memiliki organisasi yang valid.',
            'affected_url' => 'https://example.test/error',
            'attachments' => [UploadedFile::fake()->create('error.png', 500, 'image/png')],
        ])->assertUnprocessable()->assertJsonValidationErrors('organization');
    }

    public function test_creation_fails_without_required_fields(): void
    {
        $response = $this->actingAs($this->requester)
            ->postJson('/api/v1/requester/tickets', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['request_category', 'application_id', 'title', 'description', 'attachments', 'urgency']);
    }

    public function test_creation_rejects_visually_empty_rich_text_description(): void
    {
        foreach (['<p><br></p>', '<p>&nbsp;</p>', '<p>​</p>', '<script>alert(1)</script>'] as $description) {
            $this->actingAs($this->requester)->postJson('/api/v1/requester/tickets', [
                ...$this->payload(),
                'description' => $description,
            ])->assertUnprocessable()->assertJsonValidationErrors('description');
        }
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
                    ...$this->payload('error_bug'),
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
                    ...$this->payload(),
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
                ...$this->payload(),
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
                ...$this->payload(),
                'title' => 'Test Inactive User',
                'description' => 'Description test',
                'affected_url' => 'https://example.com/error',
                'attachments' => [$file],
            ]);

        $response->assertStatus(401);
    }

    public function test_all_request_categories_and_urgencies_are_accepted(): void
    {
        foreach ([['request', 'low'], ['error_bug', 'medium'], ['other', 'high']] as [$category, $urgency]) {
            $payload = $this->payload($category, $urgency);
            if ($category === 'error_bug') {
                $payload['affected_url'] = 'https://example.test/error';
            }

            $this->actingAs($this->requester)->postJson('/api/v1/requester/tickets', $payload)
                ->assertCreated()
                ->assertJsonPath('data.request_category.value', $category)
                ->assertJsonPath('data.urgency', $urgency);
        }
    }

    public function test_error_bug_requires_url_but_request_and_other_do_not(): void
    {
        $this->actingAs($this->requester)->postJson('/api/v1/requester/tickets', $this->payload('error_bug'))
            ->assertUnprocessable()->assertJsonValidationErrors('affected_url');
        $this->actingAs($this->requester)->postJson('/api/v1/requester/tickets', $this->payload('request'))->assertCreated();
        $this->actingAs($this->requester)->postJson('/api/v1/requester/tickets', $this->payload('other'))->assertCreated();
    }

    public function test_invalid_category_urgency_and_application_are_rejected(): void
    {
        $this->actingAs($this->requester)->postJson('/api/v1/requester/tickets', [...$this->payload(), 'request_category' => 'incident'])
            ->assertUnprocessable()->assertJsonValidationErrors('request_category');
        $this->actingAs($this->requester)->postJson('/api/v1/requester/tickets', [...$this->payload(), 'urgency' => 'critical'])
            ->assertUnprocessable()->assertJsonValidationErrors('urgency');
        $this->actingAs($this->requester)->postJson('/api/v1/requester/tickets', [...$this->payload(), 'application_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('application_id');

        $this->application->update(['is_active' => false]);
        $this->actingAs($this->requester)->postJson('/api/v1/requester/tickets', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('application_id');
    }

    private function payload(string $category = 'request', string $urgency = 'medium'): array
    {
        return [
            'request_category' => $category,
            'application_id' => $this->application->id,
            'title' => 'Pengajuan Requester',
            'description' => 'Deskripsi pengajuan Requester yang valid.',
            'attachments' => [UploadedFile::fake()->create('proof.png', 100, 'image/png')],
            'urgency' => $urgency,
        ];
    }
}
