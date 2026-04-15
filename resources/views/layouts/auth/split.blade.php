@props([
    'title' => null,
    'panelEyebrow' => 'Acesso ao portal',
    'panelTitle' => null,
    'panelDescription' => null,
    'heroEyebrow' => 'Operacao conectada',
    'heroTitle' => 'Chamados, prioridades e historico no mesmo ambiente',
    'heroDescription' => 'Entre para acompanhar filas, registrar andamento e manter o atendimento alinhado com visibilidade e seguranca.',
    'heroBadges' => ['Acesso seguro', 'SLA visivel', 'Recuperacao guiada'],
    'heroHighlights' => [],
])

@php
    $resolvedHeroHighlights = count($heroHighlights) ? $heroHighlights : [
        [
            'eyebrow' => 'Central unificada',
            'title' => 'Chamados, fila e contexto no mesmo fluxo',
            'description' => 'Acompanhe demandas em andamento sem perder responsaveis, status e historico recente.',
        ],
        [
            'eyebrow' => 'Prioridade clara',
            'title' => 'SLA, prazo e urgencia sempre visiveis',
            'description' => 'A leitura do painel destaca o que precisa de atencao imediata e ajuda a reduzir retrabalho.',
        ],
        [
            'eyebrow' => 'Confianca operacional',
            'title' => 'Acesso protegido com recuperacao orientada',
            'description' => 'Entre com seguranca e recupere credenciais sem interromper a rotina do atendimento.',
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#e8eef6] text-slate-900 antialiased">
        <div class="auth-shell">
            <div class="auth-shell-grid">
                <section class="auth-hero">
                    <div class="auth-hero-orb auth-hero-orb-primary"></div>
                    <div class="auth-hero-orb auth-hero-orb-secondary"></div>

                    <a href="{{ route('home') }}" class="auth-hero-brand" wire:navigate>
                        <x-brand-lockup class="w-full max-w-[12rem] sm:max-w-[14rem]" />
                    </a>

                    <div class="auth-hero-copy">
                        <div class="auth-hero-badges">
                            @foreach ($heroBadges as $badge)
                                <span class="auth-hero-badge">{{ $badge }}</span>
                            @endforeach
                        </div>

                        <p class="auth-hero-eyebrow">{{ $heroEyebrow }}</p>
                        <h1 class="auth-hero-title">{{ $heroTitle }}</h1>
                        <p class="auth-hero-description">{{ $heroDescription }}</p>
                    </div>

                    <div class="auth-hero-highlights">
                        @foreach ($resolvedHeroHighlights as $highlight)
                            <article class="auth-hero-highlight">
                                @if (filled($highlight['eyebrow'] ?? null))
                                    <p class="auth-hero-highlight-kicker">{{ $highlight['eyebrow'] }}</p>
                                @endif

                                <h2 class="auth-hero-highlight-title">{{ $highlight['title'] ?? '' }}</h2>
                                <p class="auth-hero-highlight-copy">{{ $highlight['description'] ?? '' }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="auth-panel-wrap">
                    <div class="auth-panel">
                        <div class="auth-panel-header">
                            <p class="auth-panel-eyebrow">{{ $panelEyebrow }}</p>

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
