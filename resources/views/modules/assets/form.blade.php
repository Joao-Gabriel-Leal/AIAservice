@php($includeAllocation = $includeAllocation ?? false)
@php($selectedSectorId = (string) old('current_sector_id', $asset->current_sector_id))
@php($selectedRoomId = (string) old('current_room_id', $asset->current_room_id))
@php($selectedUserId = (string) old('current_user_id', $asset->current_user_id))
@php($selectedStatus = old('status', $asset->status?->value ?? \App\Enums\AssetStatus::DISPONIVEL->value))
@php($selectedSector = $sectors->firstWhere('id', (int) $selectedSectorId))

<div class="space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex flex-col gap-1.5 border-b border-slate-200 pb-3">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Dados do item</p>
            <h3 class="text-lg font-semibold text-slate-900">Identificacao e referencia tecnica</h3>
            <p class="text-sm text-slate-500">Preencha o minimo necessario para localizar e distinguir o patrimonio com rapidez.</p>
        </div>

        <div class="grid gap-3 pt-4 md:grid-cols-2">
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Nome do patrimonio</span>
                <input type="text" name="name" value="{{ old('name', $asset->name) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                @error('name') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Numero de serie</span>
                <input type="text" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                <span class="mt-1 block text-xs text-slate-500">Se informado, o sistema nao permite repetir o mesmo serial.</span>
                @error('serial_number') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Marca</span>
                <input type="text" name="brand" value="{{ old('brand', $asset->brand) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                @error('brand') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Modelo</span>
                <input type="text" name="model" value="{{ old('model', $asset->model) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                @error('model') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block md:col-span-2">
                <span class="mb-2 block text-sm font-medium text-slate-700">Descricao</span>
                <textarea name="description" rows="3" class="min-h-[96px] w-full rounded-xl border border-slate-300 px-4 py-3">{{ old('description', $asset->description) }}</textarea>
                @error('description') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>
        </div>
    </section>

    @if ($includeAllocation)
        <section
            class="rounded-3xl border border-slate-200 bg-white p-4"
            data-asset-allocation-form
            data-initial-room-id="{{ $selectedRoomId }}"
            data-initial-user-id="{{ $selectedUserId }}"
        >
            <div class="flex flex-col gap-1.5 border-b border-slate-200 pb-3">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Lotacao inicial</p>
                <h3 class="text-lg font-semibold text-slate-900">Onde o item entra no sistema</h3>
                <p class="text-sm text-slate-500">Escolha setor, sala obrigatoria, status inicial e opcionalmente o colaborador responsavel.</p>
            </div>

            <div class="mt-4 rounded-2xl border border-sky-100 bg-sky-50/90 px-4 py-3 text-sm text-sky-900">
                <p class="font-medium">Fluxo do cadastro: empresa -> setor -> sala -> patrimonio.</p>
                <p class="mt-1 text-sky-800">Sala e obrigatoria e precisa estar cadastrada dentro do setor escolhido.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('rooms.create') }}" class="inline-flex rounded-xl border border-sky-200 bg-white px-3 py-2 text-sm font-medium text-sky-800 transition hover:border-sky-300 hover:bg-sky-100">Cadastrar sala</a>
                    <a href="{{ route('rooms.index') }}" class="inline-flex rounded-xl border border-sky-200 px-3 py-2 text-sm font-medium text-sky-800 transition hover:border-sky-300 hover:bg-sky-100">Gerenciar salas</a>
                </div>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-700">Setor atual</span>
                    <select name="current_sector_id" class="w-full rounded-xl border border-slate-300 px-4 py-3" required data-sector-select>
                        <option value="">Selecione</option>
                        @foreach ($sectors as $sectorOption)
                            <option value="{{ $sectorOption->id }}" @selected($selectedSectorId === (string) $sectorOption->id) data-sector-name="{{ $sectorOption->name }}">
                                {{ $sectorOption->name }}{{ $sectorOption->company ? ' - '.$sectorOption->company->name : '' }}
                            </option>
                        @endforeach
                    </select>
                    @if ($selectedSector)
                        <div class="mt-2">
                            <x-sector-badge :sector="$selectedSector" mode="chip">{{ $selectedSector->company?->name }}</x-sector-badge>
                        </div>
                    @endif
                    @error('current_sector_id') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-700">Sala atual</span>
                    <select name="current_room_id" class="w-full rounded-xl border border-slate-300 px-4 py-3" required data-room-select>
                        <option value="">Selecione</option>
                        @foreach ($rooms as $roomOption)
                            <option
                                value="{{ $roomOption->id }}"
                                @selected($selectedRoomId === (string) $roomOption->id)
                                data-sector-id="{{ $roomOption->sector_id }}"
                            >
                                {{ $roomOption->name }} - {{ $roomOption->sector?->name ?? 'Sem setor' }}
                            </option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs font-medium text-slate-600">Sala e obrigatoria e precisa estar cadastrada dentro do setor escolhido.</span>
                    <span class="mt-1 block text-xs text-slate-500" data-room-hint>Selecione primeiro o setor para liberar apenas as salas validas.</span>
                    @error('current_room_id') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-700">Status inicial</span>
                    <select name="status" class="w-full rounded-xl border border-slate-300 px-4 py-3" required data-status-select>
                        @foreach ($statuses as $statusOption)
                            <option value="{{ $statusOption->value }}" @selected($selectedStatus === $statusOption->value)>{{ $statusOption->label() }}</option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs text-slate-500" data-status-hint>Status sugerido: disponivel para estoque do setor e em uso quando houver responsavel direto.</span>
                    @error('status') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-700">Responsavel atual</span>
                    <select name="current_user_id" class="w-full rounded-xl border border-slate-300 px-4 py-3" data-user-select>
                        <option value="">Sem colaborador vinculado</option>
                        @foreach ($collaborators as $collaboratorOption)
                            <option
                                value="{{ $collaboratorOption->id }}"
                                @selected($selectedUserId === (string) $collaboratorOption->id)
                                data-sector-ids="{{ $collaboratorOption->sectorAccesses->pluck('sector_id')->implode(',') }}"
                            >
                                {{ $collaboratorOption->name }}
                            </option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs text-slate-500" data-user-hint>O sistema mostra apenas colaboradores com acesso ao setor escolhido.</span>
                    @error('current_user_id') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Setor selecionado</p>
                        <p class="mt-1 font-medium text-slate-900" data-summary-sector>Selecione um setor</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Salas validas</p>
                        <p class="mt-1 font-medium text-slate-900" data-summary-rooms>0 sala(s) disponivel(is)</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Colaboradores validos</p>
                        <p class="mt-1 font-medium text-slate-900" data-summary-users>0 colaborador(es) disponivel(is)</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Sugestao operacional</p>
                        <p class="mt-1 text-slate-700" data-summary-status>Sem responsavel: normalmente disponivel. Com responsavel: normalmente em uso.</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 hidden rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-room-empty-alert>
                <p class="font-medium">Nenhuma sala disponivel para este setor.</p>
                <p class="mt-1 text-amber-800">Para cadastrar o patrimonio, crie uma sala antes de continuar.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('rooms.create') }}" class="inline-flex rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm font-medium text-amber-900 transition hover:border-amber-300 hover:bg-amber-100">Cadastrar sala</a>
                    <a href="{{ route('rooms.index') }}" class="inline-flex rounded-xl border border-amber-200 px-3 py-2 text-sm font-medium text-amber-900 transition hover:border-amber-300 hover:bg-amber-100">Gerenciar salas</a>
                </div>
            </div>
        </section>
    @else
        <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-800">
            Setor, sala, colaborador e status atual mudam apenas pela tela de movimentacao para manter a rastreabilidade.
        </div>
    @endif
</div>

@if ($includeAllocation)
    @once
        @push('scripts')
            <script>
                document.querySelectorAll('[data-asset-allocation-form]').forEach(function (container) {
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
                    const summaryStatus = container.querySelector('[data-summary-status]');
                    const roomOptions = Array.from(roomSelect.options).slice(1);
                    const userOptions = Array.from(userSelect.options).slice(1);

                    function selectedText(select) {
                        const option = select.options[select.selectedIndex];
                        return option ? option.text.trim() : '';
                    }

                    function syncForm() {
                        const selectedSectorId = sectorSelect.value;
                        let availableRooms = 0;
                        let availableUsers = 0;

                        roomOptions.forEach(function (option) {
                            const visible = selectedSectorId !== '' && option.dataset.sectorId === selectedSectorId;
                            option.hidden = !visible;
                            option.disabled = !visible;
                            if (visible) availableRooms += 1;
                        });

                        if (roomSelect.value && (roomSelect.selectedOptions[0]?.hidden || roomSelect.selectedOptions[0]?.disabled)) {
                            roomSelect.value = '';
                        }

                        userOptions.forEach(function (option) {
                            const sectorIds = (option.dataset.sectorIds || '').split(',').filter(Boolean);
                            const visible = selectedSectorId !== '' && sectorIds.includes(selectedSectorId);
                            option.hidden = !visible;
                            option.disabled = !visible;
                            if (visible) availableUsers += 1;
                        });

                        if (userSelect.value && (userSelect.selectedOptions[0]?.hidden || userSelect.selectedOptions[0]?.disabled)) {
                            userSelect.value = '';
                        }

                        if (userSelect.value !== '' && statusSelect.value === 'disponivel') {
                            statusHint.textContent = 'Com responsavel selecionado, o mais comum e usar o status em uso.';
                            summaryStatus.textContent = 'Ha responsavel definido. O status operacional mais comum aqui e em uso.';
                        } else if (userSelect.value === '' && statusSelect.value === 'em_uso') {
                            statusHint.textContent = 'Sem responsavel direto, normalmente o item fica como disponivel no setor.';
                            summaryStatus.textContent = 'Sem responsavel definido. O status operacional mais comum aqui e disponivel.';
                        } else {
                            statusHint.textContent = 'Status coerente com a alocacao atual do item.';
                            summaryStatus.textContent = 'A combinacao escolhida esta coerente com a lotacao atual do patrimonio.';
                        }

                        const shouldShowRoomAlert = selectedSectorId !== '' && availableRooms === 0;

                        summarySector.textContent = selectedSectorId === '' ? 'Selecione um setor' : selectedText(sectorSelect);
                        summaryRooms.textContent = availableRooms + ' sala(s) disponivel(is)';
                        summaryUsers.textContent = availableUsers + ' colaborador(es) disponivel(is)';
                        roomHint.textContent = selectedSectorId === ''
                            ? 'Selecione primeiro o setor para liberar apenas as salas validas.'
                            : shouldShowRoomAlert
                                ? 'Nao ha salas disponiveis para este setor.'
                                : 'A lista foi filtrada para o setor selecionado.';
                        userHint.textContent = selectedSectorId === '' ? 'Selecione primeiro o setor para ver os colaboradores validos.' : 'A lista mostra apenas colaboradores com acesso ao setor escolhido.';
                        roomEmptyAlert.classList.toggle('hidden', !shouldShowRoomAlert);
                    }

                    sectorSelect.addEventListener('change', syncForm);
                    roomSelect.addEventListener('change', syncForm);
                    userSelect.addEventListener('change', syncForm);
                    statusSelect.addEventListener('change', syncForm);
                    syncForm();
                });
            </script>
        @endpush
    @endonce
@endif
