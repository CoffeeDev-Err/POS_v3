<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_superadmins_can_access_user_management_endpoints(): void
    {
        $targetId = $this->createUser('target', 'cashier', 'target-token');

        foreach (['admin', 'cashier'] as $role) {
            $token = "{$role}-token";
            $this->createUser($role, $role, $token);

            $requests = [
                fn () => $this->withToken($token)->getJson('/api/users'),
                fn () => $this->withToken($token)->postJson('/api/users', []),
                fn () => $this->withToken($token)->patchJson("/api/users/{$targetId}", []),
                fn () => $this->withToken($token)->patchJson("/api/users/{$targetId}/status", ['active' => false]),
                fn () => $this->withToken($token)->deleteJson("/api/users/{$targetId}"),
            ];

            foreach ($requests as $request) {
                $request()
                    ->assertForbidden()
                    ->assertJsonPath('message', 'You are not authorized to perform this action.');
            }
        }

        $this->assertDatabaseHas('users', ['id' => $targetId]);
    }

    public function test_superadmin_can_access_user_management_endpoints(): void
    {
        $token = 'superadmin-token';
        $this->createUser('owner', 'superadmin', $token);

        $this->withToken($token)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(1);
    }

    private function createUser(string $username, string $role, string $token): int
    {
        return DB::table('users')->insertGetId([
            'name' => ucfirst($username),
            'username' => $username,
            'username_normalized' => $username,
            'email' => "{$username}@example.test",
            'role' => $role,
            'active' => true,
            'password' => Hash::make('test-password'),
            'api_token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
