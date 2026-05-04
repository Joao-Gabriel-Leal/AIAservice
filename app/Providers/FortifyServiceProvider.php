<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login', [
            'loginHints' => $this->resolveLoginHints(),
        ]));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Resolve local login hints for faster manual validation on auth screens.
     *
     * @return array<int, array{label: string, email: string, password: string}>
     */
    private function resolveLoginHints(): array
    {
        if (! $this->app->environment('local') && ! config('app.debug')) {
            return [];
        }

        $candidates = [
            [
                'label' => 'Super admin local',
                'email' => (string) env('SUPER_ADMIN_EMAIL', 'admin@aiaservice.local'),
                'password' => (string) env('SUPER_ADMIN_PASSWORD', 'password'),
            ],
            [
                'label' => 'Demo Anadem',
                'email' => 'admin@anadem.com.br',
                'password' => 'Anadem@2026!',
            ],
        ];

        try {
            $availableEmails = User::query()
                ->whereIn('email', collect($candidates)->pluck('email')->unique())
                ->pluck('email')
                ->all();
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => in_array($candidate['email'], $availableEmails, true),
        ));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
