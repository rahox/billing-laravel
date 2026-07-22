<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBillingData;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBillingData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = $this->createUserWithRole('super-admin', ['email' => 'owner@example.test', 'password' => 'password']);

        $response = $this->withHeader('Referer', config('app.url'))->postJson('/api/login', [
            'email' => 'owner@example.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'owner@example.test')
            ->assertJsonPath('user.roles.0', 'super-admin');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $this->createUserWithRole('super-admin', ['email' => 'owner@example.test', 'password' => 'password']);

        $response = $this->withHeader('Referer', config('app.url'))->postJson('/api/login', [
            'email' => 'owner@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_unauthenticated_request_to_protected_endpoint_is_rejected(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_own_profile(): void
    {
        $user = $this->createUserWithRole('sales', ['commission_type' => 'percentage', 'commission_value' => 5]);

        $response = $this->actingAs($user)->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.commission_type', 'percentage');
    }

    public function test_logout_invalidates_session(): void
    {
        $user = $this->createUserWithRole('super-admin');

        $this->actingAs($user)->withHeader('Referer', config('app.url'))->postJson('/api/logout')->assertOk();
    }
}
