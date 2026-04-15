<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen overflow-x-hidden bg-[#1b29ae] antialiased">
        <div class="relative isolate min-h-svh overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_16%_24%,#5f7df2_0%,#3f56de_22%,#2639c7_54%,#171f86_100%)]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_60%,rgba(84,228,235,0.17),transparent_30%),linear-gradient(180deg,rgba(255,255,255,0.04)_0%,rgba(10,18,112,0.28)_100%)]"></div>
            <div class="absolute inset-x-0 bottom-0 h-32 bg-[linear-gradient(180deg,rgba(18,28,130,0)_0%,rgba(10,16,88,0.34)_100%)]"></div>

            <div class="absolute -left-10 bottom-28 h-80 w-80 rounded-full border border-white/16 bg-white/10"></div>
            <div class="absolute -left-8 bottom-0 h-56 w-56 rounded-full bg-[#59dfe8]/42"></div>
            <div class="absolute left-2 bottom-0 h-80 w-80 rounded-full bg-[radial-gradient(circle_at_center,rgba(115,231,241,0.36),rgba(255,255,255,0.08)_70%)]"></div>
            <div class="absolute left-28 bottom-[-7rem] h-[17rem] w-[17rem] rounded-full border border-white/16 bg-white/10"></div>
            <div class="absolute left-48 bottom-[-8rem] h-[15rem] w-[15rem] rounded-full border border-white/15 bg-[#a8f4fb]/20"></div>
            <div class="absolute left-72 bottom-[-10rem] h-[16rem] w-[16rem] rounded-full border border-white/14 bg-white/10"></div>
            <div class="absolute left-[29rem] bottom-[-11rem] h-[12rem] w-[12rem] rounded-full border border-white/14 bg-white/10"></div>

            <div class="relative mx-auto flex min-h-svh max-w-[1520px] items-center px-6 py-8 lg:px-10 xl:px-14">
                <div class="grid w-full items-center gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(630px,760px)] xl:gap-14">
                    <section class="hidden lg:flex lg:min-h-[720px] lg:flex-col lg:justify-center">
                        <x-brand-lockup class="w-full max-w-[33rem] drop-shadow-[0_32px_68px_rgba(13,22,120,0.26)]" />
                    </section>

                    <section class="relative">
                        <div class="absolute inset-0 rounded-[2.6rem] bg-white/18 blur-[55px]"></div>
                        <div class="relative overflow-hidden rounded-[2.35rem] border border-white/46 bg-[linear-gradient(102deg,rgba(255,255,255,0.985)_0%,rgba(248,250,255,0.97)_58%,rgba(228,238,255,0.94)_100%)] shadow-[0_44px_90px_-42px_rgba(8,14,88,0.78)]">
                            <div class="absolute inset-y-0 right-0 w-[34%] bg-[linear-gradient(180deg,rgba(213,225,250,0.3)_0%,rgba(222,233,255,0.05)_100%)]"></div>
                            <div class="absolute top-0 right-0 h-40 w-40 bg-[radial-gradient(circle,rgba(157,189,255,0.34),rgba(255,255,255,0))]"></div>

                            <div class="relative min-h-[760px] px-8 pb-8 pt-10 md:px-12 md:pb-10 md:pt-12 lg:px-14">
                                <div class="mb-8 flex justify-center lg:hidden">
                                    <x-brand-lockup class="w-full max-w-[17rem]" />
                                </div>

                                <div class="max-w-[470px] pb-48 md:pb-44">
                                    {{ $slot }}
                                </div>

                                <div class="pointer-events-none absolute bottom-0 right-0 hidden w-[25rem] md:block">
                                    <x-login-support-illustration class="h-auto w-full" />
                                </div>
                            </div>
                        </div>
                    </section>
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
