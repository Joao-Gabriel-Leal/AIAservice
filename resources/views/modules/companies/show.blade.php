<x-layouts.portal :title="$company->name" header-variant="none">
    @php
        $activeSectorsCount = $company->sectors->where('is_active', true)->count();
        $roomsCount = $company->sectors->sum(fn ($sector) => $sector->rooms->count());
        $activeRoomsCount = $company->sectors->sum(fn ($sector) => $sector->rooms->where('is_active', true)->count());
        $canCreateSector = auth()->user()->can('create', \App\Modules\Sectors\Models\Sector::class);
    @endphp

    <div class="space-y-6">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Administracao"
            :title="$company->name"
            description="Estrutura da empresa com setores e salas em um unico lugar."
        >
            <x-slot:actions>
                <a href="{{ route('companies.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Voltar</a>
                <a href="{{ route('companies.edit', ['company' => $company, 'return_to_company_id' => $company->id]) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Editar empresa</a>
                @if ($canCreateSector)
                    <a href="{{ route('sectors.create', ['company_id' => $company->id, 'return_to_company_id' => $company->id]) }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Novo setor</a>
                @endif
            </x-slot:actions>
        </x-portal.page-intro>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.6fr)]">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Dados da empresa</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">{{ $company->legal_name ?: $company->name }}</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ $company->document ?: 'Documento nao informado' }}</p>
                    </div>

                    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $company->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                        {{ $company->is_active ? 'Ativa' : 'Inativa' }}
                    </span>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Email</p>
                        <p class="mt-1 text-sm font-medium text-slate-800">{{ $company->email ?: 'Sem email' }}</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Telefone</p>
                        <p class="mt-1 text-sm font-medium text-slate-800">{{ $company->phone ?: 'Sem telefone' }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Resumo operacional</p>
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-2xl font-semibold text-slate-950">{{ $company->sectors->count() }}</p>
                        <p class="text-xs text-slate-500">Setores</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-2xl font-semibold text-slate-950">{{ $activeSectorsCount }}</p>
                        <p class="text-xs text-slate-500">Ativos</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-2xl font-semibold text-slate-950">{{ $roomsCount }}</p>
                        <p class="text-xs text-slate-500">Salas</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-2xl font-semibold text-slate-950">{{ $activeRoomsCount }}</p>
                        <p class="text-xs text-slate-500">Salas ativas</p>
                    </div>
                </div>
            </section>
        </div>

        <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Estrutura</p>
                    <h2 class="mt-1 text-xl font-semibold text-slate-950">Setores e salas</h2>
                </div>

                @if ($canCreateSector)
                    <a href="{{ route('sectors.create', ['company_id' => $company->id, 'return_to_company_id' => $company->id]) }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Novo setor</a>
                @endif
            </div>

            @forelse ($company->sectors as $sector)
                <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4 p-5">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-3">
                                <x-sector-badge :sector="$sector" mode="dot" />
                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $sector->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $sector->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-slate-500">{{ $sector->description ?: 'Sem descricao' }}</p>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <a href="{{ route('rooms.create', ['sector_id' => $sector->id, 'return_to_company_id' => $company->id]) }}" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-medium text-white">Nova sala</a>
                            <a href="{{ route('sectors.edit', ['sector' => $sector, 'return_to_company_id' => $company->id]) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">Editar</a>
                            @can('delete', $sector)
                                <form method="POST" action="{{ route('sectors.destroy', $sector) }}" onsubmit="return confirm('Remover setor?');">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="return_to_company_id" value="{{ $company->id }}">
                                    <button type="submit" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-medium text-rose-600">Excluir</button>
                                </form>
                            @endcan
                        </div>
                    </div>

                    <div class="border-t border-slate-100">
                        @if ($sector->rooms->isNotEmpty())
                            <div class="divide-y divide-slate-100">
                                @foreach ($sector->rooms as $room)
                                    <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-3">
                                                <p class="font-medium text-slate-900">{{ $room->name }}</p>
                                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $room->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                                    {{ $room->is_active ? 'Ativa' : 'Inativa' }}
                                                </span>
                                            </div>
                                            <p class="mt-1 text-sm text-slate-500">{{ $room->description ?: 'Sem descricao' }}</p>
                                        </div>

                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('rooms.edit', ['room' => $room, 'return_to_company_id' => $company->id]) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">Editar</a>
                                            @can('delete', $room)
                                                <form method="POST" action="{{ route('rooms.destroy', $room) }}" onsubmit="return confirm('Remover sala?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="return_to_company_id" value="{{ $company->id }}">
                                                    <button type="submit" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-medium text-rose-600">Excluir</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-5 text-sm text-slate-500">
                                <span>Nenhuma sala cadastrada neste setor.</span>
                                <a href="{{ route('rooms.create', ['sector_id' => $sector->id, 'return_to_company_id' => $company->id]) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">Criar sala</a>
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                    <p class="text-sm font-medium text-slate-700">Nenhum setor cadastrado para esta empresa.</p>
                    @if ($canCreateSector)
                        <a href="{{ route('sectors.create', ['company_id' => $company->id, 'return_to_company_id' => $company->id]) }}" class="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Criar primeiro setor</a>
                    @endif
                </div>
            @endforelse
        </section>
    </div>
</x-layouts.portal>
