@props([
    'title' => null,
    'panelEyebrow' => 'Acesso ao portal',
    'panelTitle' => null,
    'panelDescription' => null,
    'heroTitle' => 'Gerencie seus chamados com eficiencia',
    'heroDescription' => 'Sistema completo de gestao operacional para sua empresa',
    'heroBadges' => ['Acesso seguro', 'SLA visivel', 'Recuperacao guiada'],
    'heroHighlights' => [],
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="auth-body min-h-screen bg-white text-slate-950 antialiased">
        <div class="auth-shell">
            <div class="auth-shell-grid">
                <section class="auth-panel-wrap">
                    <div class="auth-panel">
                        <a href="{{ route('home') }}" class="auth-login-brand" wire:navigate>
                            <span class="auth-login-brand-mark" aria-hidden="true">
                                <x-auth-building-icon class="h-7 w-7" />
                            </span>

                            <span class="auth-login-brand-copy">
                                <span class="auth-login-brand-title">Sistema</span>
                                <span class="auth-login-brand-subtitle">Operacional</span>
                            </span>
                        </a>

                        <div class="auth-panel-header">
                            @if (filled($panelEyebrow))
                                <p class="auth-panel-eyebrow">{{ $panelEyebrow }}</p>
                            @endif

                            @if (filled($panelTitle))
                                <h2 class="auth-panel-title">{{ $panelTitle }}</h2>
                            @endif

                            @if (filled($panelDescription))
                                <p class="auth-panel-description">{{ $panelDescription }}</p>
                            @endif
                        </div>

                        <div class="auth-panel-body">
                            {{ $slot }}
                        </div>
                    </div>
                </section>

                <section class="auth-hero" aria-label="{{ config('app.name', 'AIA Service') }}">
                    <div class="auth-hero-inner">
                        <div class="auth-hero-visual-card" aria-hidden="true">
                            <div class="auth-hero-icon-ring">
                                <div class="auth-hero-icon-core">
                                    <x-auth-building-icon class="h-20 w-20" />
                                </div>
                            </div>
                        </div>

                        <div class="auth-hero-copy">
                            <h1 class="auth-hero-title">{{ $heroTitle }}</h1>
                            <p class="auth-hero-description">{{ $heroDescription }}</p>
                        </div>
                    </div>

                    <p class="auth-hero-footer">
                        &copy; {{ now()->year }} Sistema Operacional. Todos os direitos reservados.
                    </p>
                </section>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
