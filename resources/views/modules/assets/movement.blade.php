<x-layouts.portal title="Movimentar patrimonio" subtitle="Atualize setor, sala, colaborador e status sem perder o historico do item.">
    <div class="grid gap-5 xl:grid-cols-[320px_minmax(0,1fr)]">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap gap-2 border-b border-slate-200 pb-3 text-sm">
                <a href="{{ route('assets.index') }}" class="text-slate-500 hover:text-slate-900">Patrimonios</a>
                <span class="text-slate-300">/</span>
                <a href="{{ route('assets.show', $asset) }}" class="text-slate-500 hover:text-slate-900">{{ $asset->asset_code }}</a>
                <span class="text-slate-300">/</span>
                <span class="font-medium text-slate-900">Movimentacao</span>
            </div>

            <p class="text-sm font-medium text-slate-500">Patrimonio atual</p>
            <h2 class="mt-2 text-xl font-semibold text-slate-900">{{ $asset->name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $asset->asset_code }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $asset->status?->badgeClasses() }}">{{ $asset->statusLabel() }}</span>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ $asset->operationalStateLabel() }}</span>
            </div>

            <dl class="mt-5 space-y-3 text-sm">
                <div>
                    <dt class="font-medium text-slate-700">Setor</dt>
                    <dd class="mt-1 text-slate-600"><x-sector-badge :sector="$asset->currentSector" mode="dot" /></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-700">Sala</dt>
                    <dd class="mt-1 text-slate-600">{{ $asset->currentRoom?->name ?? 'Sem sala' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-700">Colaborador</dt>
                    <dd class="mt-1 text-slate-600">{{ $asset->currentUser?->name ?? 'Nao vinculado' }}</dd>
                </div>
            </dl>
        </section>

        <form method="POST" action="{{ route('assets.movement.store', $asset) }}" class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm" data-asset-movement-form data-current-status="{{ $asset->status?->value }}" data-current-sector-id="{{ (string) $asset->current_sector_id }}" data-current-room-id="{{ (string) $asset->current_room_id }}" data-current-user-id="{{ (string) $asset->current_user_id }}">
            @csrf

            <div class="flex flex-col gap-1.5 border-b border-slate-200 pb-3">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Nova movimentacao</p>
                <h3 class="text-lg font-semibold text-slate-900">Destino, responsavel e estado do item</h3>
                <p class="text-sm text-slate-500">Use esta tela para mover o patrimonio sem alterar o historico manualmente.</p>
            </div>

            <div class="rounded-2xl border border-sky-100 bg-sky-50/90 px-4 py-3 text-sm text-sky-900">
                <p class="font-medium">Fluxo da movimentacao: setor -> sala -> colaborador -> status.</p>
                <p class="mt-1 text-sky-800">Sala e obrigatoria e precisa estar cadastrada dentro do setor escolhido.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('rooms.create') }}" class="inline-flex rounded-xl border border-sky-200 bg-white px-3 py-2 text-sm font-medium text-sky-800 transition hover:border-sky-300 hover:bg-sky-100">Cadastrar sala</a>
                    <a href="{{ route('rooms.index') }}" class="inline-flex rounded-xl border border-sky-200 px-3 py-2 text-sm font-medium text-sky-800 transition hover:border-sky-300 hover:bg-sky-100">Gerenciar salas</a>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-700">Status de destino</span>
                    <select name="status" class="w-full rounded-xl border border-slate-300 px-4 py-3" required data-status-select>
                        @foreach ($statuses as $statusOption)
                            <option value="{{ $statusOption->value }}" @selected(old('status', $asset->status?->value) === $statusOption->value)>{{ $statusOption->label() }}</option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs text-slate-500" data-status-hint>Trocas de status puras viram historico de mudanca de status.</span>
                    @error('status') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-700">Setor de destino</span>
                    <select name="current_sector_id" class="w-full rounded-xl border border-slate-300 px-4 py-3" required data-sector-select>
                        @foreach ($sectors as $sectorOption)
                            <option value="{{ $sectorOption->id }}" @selected((string) old('current_sector_id', $asset->current_sector_id) === (string) $sectorOption->id) data-sector-name="{{ $sectorOption->name }}" data-sector-color="{{ $sectorOption->displayColor() }}">
                                {{ $sectorOption->name }}{{ $sectorOption->company ? ' - '.$sectorOption->company->name : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('current_sector_id') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-700">Sala de destino</span>
                    <select name="current_room_id" class="w-full rounded-xl border border-slate-300 px-4 py-3" required data-room-select>
                        <option value="">Selecione</option>
                        @foreach ($rooms as $roomOption)
                            <option value="{{ $roomOption->id }}" @selected((string) old('current_room_id', $asset->current_room_id) === (string) $roomOption->id) data-sector-id="{{ $roomOption->sector_id }}">
                                {{ $roomOption->name }} - {{ $roomOption->sector?->name ?? 'Sem setor' }}
                            </option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs font-medium text-slate-600">Sala e obrigatoria e precisa estar cadastrada dentro do setor escolhido.</span>
                    <span class="mt-1 block text-xs text-slate-500" data-room-hint>Selecione o setor para ver apenas as salas validas.</span>
                    @error('current_room_id') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-700">Colaborador de destino</span>
                    <select name="current_user_id" class="w-full rounded-xl border border-slate-300 px-4 py-3" data-user-select>
                        <option value="">Sem colaborador vinculado</option>
                        @foreach ($collaborators as $collaboratorOption)
                            <option value="{{ $collaboratorOption->id }}" @selected((string) old('current_user_id', $asset->current_user_id) === (string) $collaboratorOption->id) data-sector-ids="{{ $collaboratorOption->sectorAccesses->pluck('sector_id')->implode(',') }}">
                                {{ $collaboratorOption->name }}
                            </option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs text-slate-500" data-user-hint>So colaboradores com acesso ao setor de destino aparecem como validos.</span>
                    @error('current_user_id') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="block md:col-span-2">
                    <span class="mb-2 block text-sm font-medium text-slate-700">Motivo</span>
                    <input type="text" name="reason" value="{{ old('reason') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3" placeholder="Ex.: troca de sala, entrega ao colaborador, manutencao">
                    @error('reason') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="block md:col-span-2">
                    <span class="mb-2 block text-sm font-medium text-slate-700">Observacoes</span>
                    <textarea name="notes" rows="3" class="min-h-[96px] w-full rounded-xl border border-slate-300 px-4 py-3">{{ old('notes') }}</textarea>
                    @error('notes') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Tipo esperado</p>
                        <p class="mt-1 font-medium text-slate-900" data-movement-type>Transferencia local</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Setor</p>
                        <div class="mt-1 font-medium text-slate-900" data-summary-sector>
                            <x-sector-badge :sector="$asset->currentSector" mode="dot" />
                        </div>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Salas validas</p>
                        <p class="mt-1 font-medium text-slate-900" data-summary-rooms>0 sala(s) disponivel(is)</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Colaboradores validos</p>
                        <p class="mt-1 font-medium text-slate-900" data-summary-users>0 colaborador(es) disponivel(is)</p>
                    </div>
                </div>
            </div>

            <div class="hidden rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-room-empty-alert>
                <p class="font-medium">Nenhuma sala disponivel para este setor.</p>
                <p class="mt-1 text-amber-800">Crie uma sala antes de continuar com a movimentacao.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('rooms.create') }}" class="inline-flex rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm font-medium text-amber-900 transition hover:border-amber-300 hover:bg-amber-100">Cadastrar sala</a>
                    <a href="{{ route('rooms.index') }}" class="inline-flex rounded-xl border border-amber-200 px-3 py-2 text-sm font-medium text-amber-900 transition hover:border-amber-300 hover:bg-amber-100">Gerenciar salas</a>
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-4">
                <a href="{{ route('assets.show', $asset) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancelar</a>
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Registrar movimentacao</button>
            </div>
        </form>
    </div>
</x-layouts.portal>

@push('scripts')
    <script>
        document.querySelectorAll('[data-asset-movement-form]').forEach(function (container) {
            const sectorSelect = container.querySelector('[data-sector-select]');
            const roomSelect = container.querySelector('[data-room-select]');
            const userSelect = container.querySelector('[data-user-select]');
            const statusSelect = container.querySelector('[data-status-select]');
            const roomHint = container.querySelector('[data-room-hint]');
            const userHint = container.querySelector('[data-user-hint]');
            const statusHint = container.querySelector('[data-status-hint]');
            const roomEmptyAlert = container.querySelector('[data-room-empty-alert]');
            const summarySector = container.querySelector('[data-summary-sector]');
            const summaryRooms = container.querySelector('[data-summary-rooms]');
            const summaryUsers = container.querySelector('[data-summary-users]');
            const movementType = container.querySelector('[data-movement-type]');
            const currentStatus = container.dataset.currentStatus || '';
            const currentSectorId = container.dataset.currentSectorId || '';
            const currentRoomId = container.dataset.currentRoomId || '';
            const currentUserId = container.dataset.currentUserId || '';
            const roomOptions = Array.from(roomSelect.options).slice(1);
            const userOptions = Array.from(userSelect.options).slice(1);

            function selectedText(select) {
                const option = select.options[select.selectedIndex];
                return option ? option.text.trim() : '';
            }

            function selectedSectorColor(select) {
                const option = select.options[select.selectedIndex];
                return option?.dataset?.sectorColor || '#64748B';
            }

            function escapeHtml(value) {
                return String(value)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function renderSectorSummary(name, color) {
                return `
                    <span class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <span class="size-2.5 shrink-0 rounded-full" style="background-color: ${color}"></span>
                        <span class="font-medium text-slate-900">${escapeHtml(name)}</span>
                    </span>
                `;
            }

            function syncMovement() {
                const sectorId = sectorSelect.value;
                let availableRooms = 0;
                let availableUsers = 0;

                roomOptions.forEach(function (option) {
                    const visible = option.dataset.sectorId === sectorId;
                    option.hidden = !visible;
                    option.disabled = !visible;
                    if (visible) availableRooms += 1;
                });

                if (roomSelect.value && (roomSelect.selectedOptions[0]?.hidden || roomSelect.selectedOptions[0]?.disabled)) {
                    roomSelect.value = '';
                }

                userOptions.forEach(function (option) {
                    const sectorIds = (option.dataset.sectorIds || '').split(',').filter(Boolean);
                    const visible = sectorIds.includes(sectorId);
                    option.hidden = !visible;
                    option.disabled = !visible;
                    if (visible) availableUsers += 1;
                });

                if (userSelect.value && (userSelect.selectedOptions[0]?.hidden || userSelect.selectedOptions[0]?.disabled)) {
                    userSelect.value = '';
                }

                const sameStatus = statusSelect.value === currentStatus;
                const sameUser = userSelect.value === currentUserId;
                const sameRoom = roomSelect.value === currentRoomId;
                const sameSector = sectorSelect.value === currentSectorId;

                if (!sameStatus && sameUser && sameRoom && sameSector) {
                    movementType.textContent = 'Mudanca de status';
                    statusHint.textContent = 'Somente o status mudou. O historico sera registrado como mudanca de status.';
                } else if (!sameSector || !sameRoom) {
                    movementType.textContent = 'Transferencia local';
                    statusHint.textContent = 'Como houve troca de setor ou sala, o historico sera registrado como transferencia local.';
                } else if (!sameUser) {
                    movementType.textContent = userSelect.value === '' ? 'Devolucao de colaborador' : 'Atribuicao a colaborador';
                    statusHint.textContent = 'A alteracao principal aqui e de responsavel.';
                } else {
                    movementType.textContent = 'Transferencia local';
                    statusHint.textContent = 'Defina uma alteracao real para registrar a movimentacao.';
                }

                const shouldShowRoomAlert = availableRooms === 0;

                summarySector.innerHTML = renderSectorSummary(selectedText(sectorSelect) || 'Sem setor', selectedSectorColor(sectorSelect));
                summaryRooms.textContent = availableRooms + ' sala(s) disponivel(is)';
                summaryUsers.textContent = availableUsers + ' colaborador(es) disponivel(is)';
                roomHint.textContent = shouldShowRoomAlert
                    ? 'Nao ha salas disponiveis para este setor.'
                    : 'A lista foi filtrada para o setor selecionado.';
                userHint.textContent = 'A lista mostra apenas colaboradores com acesso ao setor de destino.';
                roomEmptyAlert.classList.toggle('hidden', !shouldShowRoomAlert);
            }

            sectorSelect.addEventListener('change', syncMovement);
            roomSelect.addEventListener('change', syncMovement);
            userSelect.addEventListener('change', syncMovement);
            statusSelect.addEventListener('change', syncMovement);
            syncMovement();
        });
    </script>
@endpush
