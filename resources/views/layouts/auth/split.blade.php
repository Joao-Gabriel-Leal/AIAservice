<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#eef1ff] antialiased dark:bg-[#161a6b]">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="relative hidden h-full flex-col overflow-hidden p-10 text-white lg:flex">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,#4de1d2_0%,#2f33d6_45%,#14195f_100%)]"></div>
                <div class="absolute inset-0 opacity-20" style="background-image:linear-gradient(135deg,rgba(255,255,255,.18)_0,rgba(255,255,255,0)_42%);"></div>

                <a href="{{ route('home') }}" class="relative z-20 flex items-center" wire:navigate>
                    <span class="flex h-16 w-40 items-center justify-center overflow-hidden rounded-[1.75rem] border border-white/15 bg-[#2f33d6]/90 p-2 shadow-2xl shadow-black/20">
                        <x-app-logo-icon class="h-full w-full" />
                    </span>
                </a>

                <div class="relative z-20 mt-auto max-w-xl space-y-6">
                    <div class="space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-[0.35em] text-white/70">AIA Tech</p>
                        <flux:heading size="xl" level="1" class="max-w-lg text-white">
                            Tecnologia com a identidade da sua empresa desde o primeiro acesso.
                        </flux:heading>
                        <flux:text class="max-w-md text-base leading-7 text-white/78">
                            Esta primeira personalizacao aplica sua marca nas areas principais do sistema para a gente evoluir visual, cores e telas nas proximas rodadas.
                        </flux:text>
                    </div>

                    <div class="grid max-w-md grid-cols-2 gap-4 text-sm text-white/85">
                        <div class="rounded-3xl border border-white/12 bg-white/10 p-4 backdrop-blur-sm">
                            <p class="font-semibold">Marca visivel</p>
                            <p class="mt-2 text-white/70">Logo aplicada em login, navegacao e cabecalho.</p>
                        </div>
                        <div class="rounded-3xl border border-white/12 bg-white/10 p-4 backdrop-blur-sm">
                            <p class="font-semibold">Base pronta</p>
                            <p class="mt-2 text-white/70">Estrutura preparada para novos ajustes visuais.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-3 font-medium lg:hidden" wire:navigate>
                        <span class="flex h-16 w-36 items-center justify-center overflow-hidden rounded-[1.75rem] border border-[#2f33d6]/10 bg-[#2f33d6] p-2 shadow-lg shadow-[#2f33d6]/15">
                            <x-app-logo-icon class="h-full w-full" />
                        </span>

                        <span class="text-sm font-semibold uppercase tracking-[0.3em] text-[#2f33d6]">AIA Tech</span>
                    </a>
                    {{ $slot }}
                </div>
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
