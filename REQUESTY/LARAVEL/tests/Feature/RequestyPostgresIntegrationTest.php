<?php

namespace Tests\Feature;

use App\Services\PgEncryptionContext;
use Tests\TestCase;

class RequestyPostgresIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $rlDir = storage_path('app/ratelimit');
        if (is_dir($rlDir)) {
            foreach (glob($rlDir . '/*.json') ?: [] as $f) {
                @unlink($f);
            }
        }
    }

    public function test_postgresql_connection_and_encryption_key_active(): void
    {
        $pdo = PgEncryptionContext::getPdo();
        $row = $pdo->query("SELECT current_setting('restaurante.encryption_key', true) AS key, current_schema() AS sch")->fetch();

        $this->assertNotEmpty($row['key'] ?? '');
        $this->assertSame('restaurante', $row['sch'] ?? '');
    }

    public function test_api_login_jwt_rbac_and_pagination_work_against_postgresql(): void
    {
        $loginAdmin = $this->postJson('/api/login', [
            'correo' => 'admin@restaurante.local',
            'contrasena' => 'AdminRest2026*',
        ]);
        $loginAdmin->assertStatus(200)->assertJsonStructure(['success', 'token', 'usuario']);
        $adminToken = (string) $loginAdmin->json('token');

        $loginMesero = $this->postJson('/api/login', [
            'correo' => 'mesero@restaurante.local',
            'contrasena' => 'MeseroRest2026*',
        ]);
        $loginMesero->assertStatus(200);
        $meseroToken = (string) $loginMesero->json('token');

        $this->withHeader('Authorization', "Bearer {$meseroToken}")
            ->getJson('/api/usuarios')
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson('/api/pedidos?limit=2&offset=0')
            ->assertStatus(200)
            ->assertJsonPath('pagination.limit', 2)
            ->assertJsonPath('pagination.offset', 0);

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson('/api/usuarios', [
                'nombre' => 'WeakPass',
                'apellido' => 'Test',
                'documento' => '888777666',
                'correo' => 'weak@test.local',
                'contrasena' => 'abcdef',
                'rol' => 'CLIENTE',
            ])
            ->assertStatus(422);
    }

    public function test_web_views_security_headers_and_404_routing(): void
    {
        $this->get('/login')
            ->assertStatus(200)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');

        $this->get('/mesas')
            ->assertRedirect('/login');

        $this->get('/ruta-no-existente-laravel')->assertStatus(404);
    }
}
