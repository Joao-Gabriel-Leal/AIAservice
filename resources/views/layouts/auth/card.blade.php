<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#0b1020] text-white antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-[radial-gradient(circle_at_top,#183b78_0%,#0d1530_38%,#080b14_100%)] p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6">
                <div class="flex flex-col gap-6">
                    <div class="rounded-[2rem] border border-white/10 bg-white/5 text-white shadow-[0_35px_80px_-45px_rgba(0,0,0,0.85)] backdrop-blur-xl">
                        <div class="px-10 py-8">{{ $slot }}</div>
                    </div>
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
