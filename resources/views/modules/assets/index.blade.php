<x-layouts.portal title="Patrimonios" subtitle="Cadastro, lotacao atual, filtros operacionais e acesso rapido por QR Code." header-variant="none">
    <div class="space-y-6">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Patrimonio operacional"
            title="Busca e controle do parque"
            description="Barra rapida para codigo, status, saneamento patrimonial, local e responsavel com acesso direto ao cadastro."
        >
            <x-slot:actions>
                <a href="{{ route('assets.create') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Novo patrimonio</a>
                <a href="{{ route('assets.export', request()->query()) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Exportar Excel</a>
            </x-slot:actions>
        </x-portal.page-intro>

        @php($hasActiveFilters = collect([
            $filters['search'] ?? '',
            $filters['status'] ?? '',
            $filters['allocation_status'] ?? '',
            $filters['sector_id'] ?? '',
            $filters['room_id'] ?? '',
            $filters['user_id'] ?? '',
        ])->contains(fn ($value) => filled($value)))

        @if ($latestImportBatch)
            <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Ultima importacao patrimonial</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">Aba {{ $latestImportBatch->source_sheet }} processada</h2>
                        <p class="mt-2 text-sm text-slate-500">
                            Batch #{{ $latestImportBatch->id }} em {{ $latestImportBatch->finished_at?->format('d/m/Y H:i') ?? 'processamento em aberto' }}.
                            {{ $latestImportBatch->promoted_rows }} item(ns) promovido(s) para o parque e {{ $latestImportBatch->pending_review_rows }} linha(s) ficaram pendentes para saneamento.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <article class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Linhas</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $latestImportBatch->total_rows }}</p>
                        </article>
                        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-700">Ready</p>
                            <p class="mt-1 text-lg font-semibold text-emerald-900">{{ $latestImportBatch->ready_rows }}</p>
                        </article>
                        <article class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-700">Pendentes</p>
                            <p class="mt-1 text-lg font-semibold text-amber-900">{{ $latestImportBatch->pending_review_rows }}</p>
                        </article>
                        <article class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-rose-700">Erros</p>
                            <p class="mt-1 text-lg font-semibold text-rose-900">{{ $latestImportBatch->error_rows }}</p>
                        </article>
                        <article class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-700">Promovidos</p>
                            <p class="mt-1 text-lg font-semibold text-sky-900">{{ $latestImportBatch->promoted_rows }}</p>
                        </article>
                    </div>
                </div>

                @if ($latestImportPendingRows->isNotEmpty())
                    <div class="mt-5 rounded-3xl border border-amber-200 bg-amber-50/70 p-4">
                        <div class="border-b border-amber-200 pb-3">
                            <p class="text-sm font-semibold text-amber-900">Linhas pendentes de saneamento</p>
                            <p class="mt-1 text-sm text-amber-800">Esses registros ficaram na staging porque o legado nao trouxe status confiavel para promover direto ao parque operacional.</p>
                        </div>

                        <div class="mt-4 space-y-3">
                            @foreach ($latestImportPendingRows as $pendingRow)
                                @php($normalizedPayload = $pendingRow->normalized_payload ?? [])
                                <article class="rounded-2xl border border-amber-200 bg-white px-4 py-3">
                                    <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700">
                                                Linha {{ $pendingRow->source_row }}{{ $pendingRow->asset_code ? ' - '.$pendingRow->asset_code : '' }}
                                            </p>
                                            <p class="mt-1 font-medium text-slate-900">{{ $pendingRow->item_name ?? 'Item sem nome identificado' }}</p>
                                            <p class="mt-1 text-sm text-slate-600">{{ $pendingRow->pending_reason ?? 'Pendente de revisao.' }}</p>
                                        </div>
                                        <div class="text-sm text-slate-500">
                                            <p>{{ $normalizedPayload['legacy_position_text'] ?? 'Sem setor legado' }}</p>
                                            <p>{{ $normalizedPayload['legacy_collaborator_name'] ?? 'Sem colaborador legado' }}</p>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        @endif

        <x-portal.table-search-bar
            form-id="assets-filter-form"
            :action="route('assets.index')"
            :search-value="$filters['search']"
            placeholder="Buscar por codigo, nome ou serial"
            :clear-href="route('assets.index')"
            :has-active-filters="$hasActiveFilters"
            data-asset-filter-form
        />

        <div class="portal-table-surface">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="portal-table-head text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Patrimonio</th>
                        <th class="px-6 py-3 font-medium">
                            <div class="grid gap-3">
                                <x-portal.table-column-filter label="Status" form-id="assets-filter-form" name="status" min-width="min-w-44">
                                    @foreach ($statuses as $statusOption)
                                        <option value="{{ $statusOption->value }}" @selected($filters['status'] === $statusOption->value)>{{ $statusOption->label() }}</option>
                                    @endforeach
                                </x-portal.table-column-filter>

                                <x-portal.table-column-filter label="Saneamento" form-id="assets-filter-form" name="allocation_status" min-width="min-w-44">
                                    @foreach ($allocationStatuses as $allocationStatus)
                                        <option value="{{ $allocationStatus->value }}" @selected($filters['allocation_status'] === $allocationStatus->value)>{{ $allocationStatus->label() }}</option>
                                    @endforeach
                                </x-portal.table-column-filter>
                            </div>
                        </th>
                        <th class="px-6 py-3 font-medium">
                            <div class="grid gap-3">
                                <x-portal.table-column-filter label="Setor" form-id="assets-filter-form" name="sector_id" min-width="min-w-52" :auto-submit="false" data-filter-sector>
                                    @foreach ($sectors as $sectorOption)
                                        <option value="{{ $sectorOption->id }}" @selected((string) $filters['sector_id'] === (string) $sectorOption->id)>{{ $sectorOption->name }}</option>
                                    @endforeach
                                </x-portal.table-column-filter>

                                <x-portal.table-column-filter label="Sala" form-id="assets-filter-form" name="room_id" min-width="min-w-52" data-filter-room>
                                    @foreach ($rooms as $roomOption)
                                        <option value="{{ $roomOption->id }}" @selected((string) $filters['room_id'] === (string) $roomOption->id) data-sector-id="{{ $roomOption->sector_id }}">{{ $roomOption->name }}</option>
                                    @endforeach
                                </x-portal.table-column-filter>
                            </div>
                        </th>
                        <th class="px-6 py-3 font-medium">
                            <x-portal.table-column-filter label="Colaborador" form-id="assets-filter-form" name="user_id" min-width="min-w-52" data-filter-user>
                                @foreach ($collaborators as $collaboratorOption)
                                    <option value="{{ $collaboratorOption->id }}" @selected((string) $filters['user_id'] === (string) $collaboratorOption->id) data-sector-ids="{{ $collaboratorOption->sectorAccesses->pluck('sector_id')->implode(',') }}">{{ $collaboratorOption->name }}</option>
                                @endforeach
                            </x-portal.table-column-filter>
                        </th>
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
                                @if ($asset->importedFromLegacy())
                                    <p class="mt-2 text-xs text-slate-500">Origem legado: {{ $asset->legacy_source_sheet }} linha {{ $asset->legacy_source_row }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $asset->status?->badgeClasses() }}">
                                    {{ $asset->statusLabel() }}
                                </span>
                                <span class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $asset->allocation_status?->badgeClasses() }}">
                                    {{ $asset->allocationStatusLabel() }}
                                </span>
                                <p class="mt-2 text-xs text-slate-500">{{ $asset->operationalStateLabel() }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                <x-sector-badge :sector="$asset->currentSector" mode="dot" />
                                <p class="text-xs text-slate-500">{{ $asset->currentRoom?->name ?? 'Sem sala' }}</p>
                                @if ($asset->allocation_status?->value === 'pending_review')
                                    <p class="mt-2 text-xs font-medium text-amber-700">Aguardando saneamento de alocacao.</p>
                                @endif
                            </td>
                            <td class="ui-person-column-cell">
                                <x-person-reference :user="$asset->currentUser" empty-label="Nao vinculado" />
                            </td>
                            <td class="px-6 py-2">
                                <a href="{{ $asset->qrCodeUrl() }}" class="block w-fit" title="Abrir pagina publica do QR code">
                                    <div class="rounded-xl border border-slate-200 bg-white p-1" data-qr-target="{{ $asset->qrCodeUrl() }}">
                                        {!! $asset->qrCodeSvg(68) !!}
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
            const formSelector = `[form="${form.id}"]`;
            const sectorSelect = document.querySelector(`[data-filter-sector]${formSelector}`);
            const roomSelect = document.querySelector(`[data-filter-room]${formSelector}`);
            const userSelect = document.querySelector(`[data-filter-user]${formSelector}`);

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

            sectorSelect.addEventListener('change', function () {
                syncFilterOptions();

                if (form.requestSubmit) {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });

            syncFilterOptions();
        });
    </script>
@endpush
