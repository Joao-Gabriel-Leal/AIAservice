<x-layouts.portal title="Patrimonios" subtitle="Cadastro, lotacao atual, filtros operacionais e acesso rapido por QR Code." :show-header="false">
    <div class="space-y-6">
        <x-portal.section-hero
            eyebrow="Patrimonio operacional"
            title="Busca e controle do parque"
            description="Barra rapida para codigo, status, local e responsavel com acesso direto ao cadastro."
        >
            <form method="GET" action="{{ route('assets.index') }}" class="space-y-3" data-asset-filter-form>
                <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Filtros operacionais</p>
                        <p class="mt-1 text-xs text-slate-500">Refine por codigo, status, local ou responsavel sem perder a visao do parque.</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('assets.create') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Novo patrimonio</a>
                        <a href="{{ route('assets.export', request()->query()) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Exportar Excel</a>
                        <button type="submit" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Filtrar</button>
                        <a href="{{ route('assets.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Limpar</a>
                    </div>
                </div>

                <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-[minmax(280px,1.8fr)_repeat(4,minmax(110px,0.56fr))]">
                    <label class="block">
                        <span class="sr-only">Busca</span>
                        <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar por codigo, nome ou serial" class="ui-input h-11 w-full px-3">
                    </label>

                    <label class="block">
                        <span class="sr-only">Status</span>
                        <select name="status" class="ui-native-select h-11 w-full text-sm text-slate-700">
                            <option value="">Status</option>
                            @foreach ($statuses as $statusOption)
                                <option value="{{ $statusOption->value }}" @selected($filters['status'] === $statusOption->value)>{{ $statusOption->label() }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="sr-only">Setor</span>
                        <select name="sector_id" class="ui-native-select h-11 w-full text-sm text-slate-700" data-filter-sector>
                            <option value="">Setor</option>
                            @foreach ($sectors as $sectorOption)
                                <option value="{{ $sectorOption->id }}" @selected((string) $filters['sector_id'] === (string) $sectorOption->id)>{{ $sectorOption->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="sr-only">Sala</span>
                        <select name="room_id" class="ui-native-select h-11 w-full text-sm text-slate-700" data-filter-room>
                            <option value="">Sala</option>
                            @foreach ($rooms as $roomOption)
                                <option value="{{ $roomOption->id }}" @selected((string) $filters['room_id'] === (string) $roomOption->id) data-sector-id="{{ $roomOption->sector_id }}">{{ $roomOption->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="sr-only">Colaborador</span>
                        <select name="user_id" class="ui-native-select h-11 w-full text-sm text-slate-700" data-filter-user>
                            <option value="">Colaborador</option>
                            @foreach ($collaborators as $collaboratorOption)
                                <option value="{{ $collaboratorOption->id }}" @selected((string) $filters['user_id'] === (string) $collaboratorOption->id) data-sector-ids="{{ $collaboratorOption->sectorAccesses->pluck('sector_id')->implode(',') }}">{{ $collaboratorOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </form>
        </x-portal.section-hero>

        <div class="portal-table-surface">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="portal-table-head text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Patrimonio</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Local atual</th>
                        <th class="px-6 py-3 font-medium">Colaborador</th>
                        <th class="px-6 py-3 font-medium">QR</th>
                        <th class="px-6 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($assets as $asset)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">{{ $asset->asset_code }}</p>
                                <p class="mt-1 font-medium text-slate-900">{{ $asset->name }}</p>
                                <p class="text-xs text-slate-500">{{ $asset->serial_number ? 'Serial '.$asset->serial_number : 'Sem serial informado' }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $asset->status?->badgeClasses() }}">
                                    {{ $asset->statusLabel() }}
                                </span>
                                <p class="mt-2 text-xs text-slate-500">{{ $asset->operationalStateLabel() }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                <x-sector-badge :sector="$asset->currentSector" mode="dot" />
                                <p class="text-xs text-slate-500">{{ $asset->currentRoom?->name ?? 'Sem sala' }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ $asset->currentUser?->name ?? 'Nao vinculado' }}</td>
                            <td class="px-6 py-4">
                                <a href="{{ route('assets.show', $asset) }}" class="block w-fit" title="Abrir detalhe do patrimonio">
                                    <div class="rounded-xl border border-slate-200 bg-white p-1.5" data-qr-target="{{ $asset->detailUrl() }}">
                                        {!! $asset->qrCodeSvg(56) !!}
                                    </div>
                                </a>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('assets.show', $asset) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">Detalhes</a>
                                    <a href="{{ route('assets.edit', $asset) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">Editar</a>
                                    <a href="{{ route('assets.movement.create', $asset) }}" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-medium text-white">Movimentar</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <p class="text-sm font-medium text-slate-700">Nenhum patrimonio cadastrado ainda.</p>
                                <p class="mt-1 text-sm text-slate-500">Comece registrando o primeiro item do parque para liberar movimentacoes e QR Code.</p>
                                <a href="{{ route('assets.create') }}" class="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Cadastrar primeiro patrimonio</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $assets->links() }}
    </div>
</x-layouts.portal>

@push('scripts')
    <script>
        document.querySelectorAll('[data-asset-filter-form]').forEach(function (form) {
            const sectorSelect = form.querySelector('[data-filter-sector]');
            const roomSelect = form.querySelector('[data-filter-room]');
            const userSelect = form.querySelector('[data-filter-user]');

            if (!sectorSelect || !roomSelect || !userSelect) {
                return;
            }

            const roomOptions = Array.from(roomSelect.options).slice(1);
            const userOptions = Array.from(userSelect.options).slice(1);

            function syncFilterOptions() {
                const sectorId = sectorSelect.value;

                roomOptions.forEach(function (option) {
                    const visible = sectorId === '' || option.dataset.sectorId === sectorId;
                    option.hidden = !visible;
                    option.disabled = !visible;
                });

                if (roomSelect.value && (roomSelect.selectedOptions[0]?.hidden || roomSelect.selectedOptions[0]?.disabled)) {
                    roomSelect.value = '';
                }

                userOptions.forEach(function (option) {
                    const sectorIds = (option.dataset.sectorIds || '').split(',').filter(Boolean);
                    const visible = sectorId === '' || sectorIds.includes(sectorId);
                    option.hidden = !visible;
                    option.disabled = !visible;
                });

                if (userSelect.value && (userSelect.selectedOptions[0]?.hidden || userSelect.selectedOptions[0]?.disabled)) {
                    userSelect.value = '';
                }
            }

            sectorSelect.addEventListener('change', syncFilterOptions);
            syncFilterOptions();
        });
    </script>
@endpush
