<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $asset->asset_code])
    </head>
    <body class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(14,165,233,0.16),_transparent_38%),linear-gradient(180deg,_#f8fafc_0%,_#e2e8f0_100%)] text-slate-900">
        <div class="mx-auto flex min-h-screen w-full max-w-5xl items-center px-4 py-8 sm:px-6 lg:px-8">
            <main class="w-full overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-2xl shadow-slate-900/10">
                <section class="border-b border-slate-200 bg-slate-950 px-6 py-8 text-white sm:px-8">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-300">Patrimonio identificado</p>
                    <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-sm text-slate-300">Dados principais do patrimonio com acesso autenticado</p>
                            <h1 class="mt-2 text-3xl font-semibold">{{ $asset->name }}</h1>
                            <p class="mt-3 text-sm text-slate-300">Codigo {{ $asset->asset_code }}</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full px-3 py-1 text-xs font-medium {{ $asset->status?->badgeClasses() }}">{{ $asset->statusLabel() }}</span>
                            <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-slate-100">{{ $asset->operationalStateLabel() }}</span>
                        </div>
                    </div>
                </section>

                <section class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[minmax(0,1.3fr)_minmax(260px,0.7fr)]">
                    <div class="space-y-6">
                        @if ($asset->description)
                            <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Descricao</p>
                                <p class="mt-3 text-sm leading-6 text-slate-700">{{ $asset->description }}</p>
                            </article>
                        @endif

                        <div class="grid gap-4 md:grid-cols-2">
                            <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                                <p class="text-sm text-slate-500">Setor atual</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">{{ $asset->currentSector?->name ?? 'Sem setor' }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $asset->currentSector?->company?->name ?? 'Empresa nao informada' }}</p>
                            </article>

                            <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                                <p class="text-sm text-slate-500">Sala atual</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">{{ $asset->currentRoom?->name ?? 'Sem sala' }}</p>
                                <p class="mt-1 text-sm text-slate-500">Localizacao vinculada no sistema</p>
                            </article>

                            <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                                <p class="text-sm text-slate-500">Colaborador atual</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">{{ $asset->currentUser?->name ?? 'Sem colaborador vinculado' }}</p>
                                <p class="mt-1 text-sm text-slate-500">Responsavel atual pelo item</p>
                            </article>

                            <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                                <p class="text-sm text-slate-500">Ultima atualizacao</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">{{ $asset->updated_at?->format('d/m/Y H:i') ?? 'Nao disponivel' }}</p>
                                <p class="mt-1 text-sm text-slate-500">Horario do registro mais recente</p>
                            </article>
                        </div>
                    </div>

                    <aside class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Ficha tecnica</p>

                        <dl class="mt-4 space-y-4 text-sm">
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <dt class="text-slate-500">Numero de serie</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ $asset->serial_number ?: 'Nao informado' }}</dd>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <dt class="text-slate-500">Marca</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ $asset->brand ?: 'Nao informada' }}</dd>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <dt class="text-slate-500">Modelo</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ $asset->model ?: 'Nao informado' }}</dd>
                            </div>

                            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-4 text-xs leading-5 text-slate-500">
                                O QR direciona para esta tela protegida por login. Para historico completo, movimentacoes e edicao, acesse o sistema interno.
                            </div>
                        </dl>
                    </aside>
                </section>
            </main>
        </div>
    </body>
</html>
