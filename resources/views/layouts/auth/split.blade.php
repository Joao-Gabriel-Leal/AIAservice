@props([
    'title' => null,
    'panelEyebrow' => 'Acesso ao portal',
    'panelTitle' => null,
    'panelDescription' => null,
    'heroEyebrow' => 'AIA Service',
    'heroTitle' => 'Atendimento em ordem, desde o primeiro acesso',
    'heroDescription' => 'Ambiente seguro para acompanhar chamados e rotinas internas.',
    'heroBadges' => ['Acesso seguro', 'SLA visivel', 'Recuperacao guiada'],
    'heroHighlights' => [],
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#e8eef6] text-slate-900 antialiased">
        <div class="auth-shell">
            <div class="auth-shell-grid">
                <section class="auth-hero" aria-label="{{ config('app.name', 'AIA Service') }}">
                    <a href="{{ route('home') }}" class="auth-hero-brand" wire:navigate>
                        <x-brand-lockup class="auth-brand-lockup" />
                    </a>

                    <div class="auth-hero-visual" aria-hidden="true">
                        <x-login-support-illustration class="auth-hero-illustration" />
                    </div>

                    <div class="auth-hero-caption">
                        <p>{{ $heroDescription }}</p>
                    </div>
                </section>

                <section class="auth-panel-wrap">
                    <div class="auth-panel">
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
