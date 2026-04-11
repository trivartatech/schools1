<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name'     => 'Test School',
            'slug'     => 'test-school',
            'is_active'=> true,
        ]);

        $this->adminUser = User::create([
            'name'      => 'Test Admin',
            'email'     => 'admin@test.com',
            'username'  => 'testadmin',
            'password'  => Hash::make('password123'),
            'user_type' => UserType::Admin->value,
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    public function test_login_page_renders(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_user_can_login_with_email(): void
    {
        $response = $this->post('/login', [
            'email'    => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_user_can_login_with_username(): void
    {
        $response = $this->post('/login', [
            'email'    => 'testadmin', // username field accepts username too
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $response = $this->post('/login', [
            'email'    => 'admin@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->adminUser->update(['is_active' => false]);

        $response = $this->post('/login', [
            'email'    => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->post('/login', []);

        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_logout(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_unauthenticated_user_is_redirected_from_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    // ── OTP Rate Limiting ─────────────────────────────────────────────────────

    public function test_otp_request_is_rate_limited(): void
    {
        // 5 attempts, 6th should be throttled
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login/otp/request', ['phone' => '9999999999']);
        }

        $response = $this->post('/login/otp/request', ['phone' => '9999999999']);

        $response->assertStatus(429); // Too Many Requests
    }
}
