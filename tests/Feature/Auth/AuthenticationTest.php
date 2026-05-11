<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response
            ->assertOk()
            ->assertDontSeeText('Abrir chamado');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_with_pending_password_change_are_redirected_to_the_isolated_force_change_form(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('password.force-change'));

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertRedirect(route('password.force-change'));

        $this->actingAs($user)
            ->get(route('password.force-change'))
            ->assertOk()
            ->assertSee('Troque sua senha');
    }

    public function test_pending_password_change_accepts_only_strong_new_password(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->post(route('password.force-change.update'), [
                'password' => 'fraca',
                'password_confirmation' => 'fraca',
            ])
            ->assertSessionHasErrors('password');

        $this->actingAs($user)
            ->post(route('password.force-change.update'), [
                'password' => 'Senha@123',
                'password_confirmation' => 'Senha@123',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_force_change_requires_current_password_after_first_mandatory_reset(): void
    {
        $user = User::factory()->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->post(route('password.force-change.update'), [
                'password' => 'Senha@123',
                'password_confirmation' => 'Senha@123',
            ])
            ->assertSessionHasErrors('current_password');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrorsIn('email');

        $this->assertGuest();
    }

    public function test_inactive_users_cannot_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrorsIn('email');

        $this->assertGuest();
    }

    public function test_inactive_authenticated_users_are_logged_out_on_the_next_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $user->forceFill(['is_active' => false])->save();

        $this->get(route('dashboard'))
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_expired_guest_logout_request_redirects_to_login(): void
    {
        $request = Request::create('/logout', 'POST');

        $response = TestResponse::fromBaseResponse(
            app(ExceptionHandler::class)->render($request, new TokenMismatchException('CSRF token mismatch.'))
        );

        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_logout_request_with_invalid_token_keeps_default_token_mismatch_response(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/logout', 'POST');

        $request->setUserResolver(fn () => $user);

        $response = TestResponse::fromBaseResponse(
            app(ExceptionHandler::class)->render($request, new TokenMismatchException('CSRF token mismatch.'))
        );

        $response->assertStatus(419);
    }
}
