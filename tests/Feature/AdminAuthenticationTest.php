<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_form_is_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Entrar')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false);
    }

    public function test_user_can_authenticate_and_is_redirected_to_admin(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_do_not_authenticate_the_user(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'senha-incorreta',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login_when_accessing_admin(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Área administrativa');
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_admin_is_protected_again_after_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertOk();

        $this->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_optional_fortify_routes_are_not_available(): void
    {
        foreach ([
            ['get', '/register'],
            ['post', '/register'],
            ['get', '/forgot-password'],
            ['post', '/forgot-password'],
            ['get', '/reset-password/token'],
            ['post', '/reset-password'],
            ['get', '/email/verify'],
            ['get', '/email/verify/1/hash'],
            ['post', '/email/verification-notification'],
            ['get', '/two-factor-challenge'],
            ['post', '/two-factor-challenge'],
            ['post', '/user/two-factor-authentication'],
        ] as [$method, $uri]) {
            $this->{$method}($uri)->assertNotFound();
        }

        $this->assertSame([], config('fortify.features'));
    }
}
