<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    public function test_health_endpoint_reports_a_connected_database_in_the_standard_envelope(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertHeader('X-Request-ID')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['status', 'database'],
                'meta' => ['timestamp', 'request_id'],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Tic Hub API is healthy',
                'data' => [
                    'status' => 'ok',
                    'database' => 'connected',
                ],
            ]);

        $this->assertSame(
            $response->headers->get('X-Request-ID'),
            $response->json('meta.request_id'),
        );
    }

    public function test_valid_client_request_id_is_preserved(): void
    {
        $requestId = (string) Str::uuid();

        $this->withHeader('X-Request-ID', $requestId)
            ->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertJsonPath('meta.request_id', $requestId);
    }

    public function test_missing_request_id_generates_a_uuid(): void
    {
        $response = $this->getJson('/api/v1/health')->assertOk();
        $requestId = $response->json('meta.request_id');

        $this->assertIsString($requestId);
        $this->assertTrue(Str::isUuid($requestId));
        $this->assertSame($requestId, $response->headers->get('X-Request-ID'));
    }

    public function test_invalid_client_request_id_is_replaced(): void
    {
        $response = $this->withHeader('X-Request-ID', str_repeat('x', 100))
            ->getJson('/api/v1/health')
            ->assertOk();

        $this->assertTrue(Str::isUuid($response->json('meta.request_id')));
        $this->assertNotSame(str_repeat('x', 100), $response->json('meta.request_id'));
    }

    public function test_unknown_api_route_returns_a_standard_json_404(): void
    {
        $this->getJson('/api/v1/route-that-does-not-exist')
            ->assertNotFound()
            ->assertHeader('X-Request-ID')
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found',
                'error' => ['code' => 'NOT_FOUND'],
            ])
            ->assertJsonStructure(['meta' => ['request_id']]);
    }

    public function test_unsupported_api_method_returns_standard_json(): void
    {
        $this->postJson('/api/v1/health')
            ->assertStatus(405)
            ->assertHeader('X-Request-ID')
            ->assertJson([
                'success' => false,
                'message' => 'Method not allowed',
                'error' => ['code' => 'METHOD_NOT_ALLOWED'],
            ])
            ->assertJsonStructure(['meta' => ['request_id']]);
    }

    public function test_validation_exception_uses_the_standard_envelope(): void
    {
        Route::post('/api/v1/_test/validation', function () {
            validator([], ['name' => ['required']])->validate();
        });

        $this->postJson('/api/v1/_test/validation')
            ->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ])
            ->assertJsonStructure([
                'errors' => ['name'],
                'meta' => ['request_id'],
            ]);
    }

    public function test_internal_error_is_safe_even_when_debug_is_enabled(): void
    {
        Route::get('/api/v1/_test/error', function () {
            throw new RuntimeException('secret-database-password from C:\\internal\\path');
        });

        $response = $this->getJson('/api/v1/_test/error')
            ->assertInternalServerError()
            ->assertJson([
                'success' => false,
                'message' => 'An unexpected error occurred',
                'error' => ['code' => 'SERVER_ERROR'],
            ]);

        $body = $response->getContent();
        $this->assertStringNotContainsString('secret-database-password', $body);
        $this->assertStringNotContainsString('C:\\internal\\path', $body);
        $this->assertStringNotContainsString('trace', strtolower($body));
    }

    public function test_cors_allows_the_configured_frontend_origin_with_credentials(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/health')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_health_endpoint_reports_database_failure_without_sensitive_details(): void
    {
        config([
            'database.default' => 'unavailable_health_database',
            'database.connections.unavailable_health_database' => [
                'driver' => 'sqlite',
                'database' => base_path('missing-directory/health.sqlite'),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('unavailable_health_database');

        $response = $this->getJson('/api/v1/health')
            ->assertStatus(503)
            ->assertHeader('X-Request-ID')
            ->assertJson([
                'success' => false,
                'message' => 'Tic Hub API database is unavailable',
                'error' => ['code' => 'DATABASE_UNAVAILABLE'],
            ])
            ->assertJsonStructure(['meta' => ['timestamp', 'request_id']]);

        $body = strtolower($response->getContent());
        $this->assertStringNotContainsString('password', $body);
        $this->assertStringNotContainsString('select ', $body);
        $this->assertStringNotContainsString('trace', $body);
        $this->assertStringNotContainsString('missing-directory', $body);
    }
}
