<x-layouts.portal title="Licencas" subtitle="Veja por setor quais licencas existem, quantas estao em uso e onde vale abrir para gerir as atribuicoes." header-variant="none">
    <div class="space-y-6">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Gestao simples"
            title="Licencas por setor"
            description="Cada linha representa um tipo de licenca. Abra a linha para ver com quem cada licenca esta atribuida e transferir quando precisar."
        >
            <x-slot:actions>
                <a href="{{ route('licenses.create') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Nova licenca</a>
                <a href="{{ route('licenses.export', request()->query()) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Exportar Excel</a>
            </x-slot:actions>
        </x-portal.page-intro>

        @php($hasActiveFilters = collect([
            $filters['search'] ?? '',
            $filters['sector_id'] ?? '',
        ])->contains(fn ($value) => filled($value)))

        <x-portal.table-search-bar
            form-id="licenses-filter-form"
            :action="route('licenses.index')"
            :search-value="$filters['search']"
            placeholder="Buscar por fornecedor, produto, email ou referencia"
            :clear-href="route('licenses.index')"
            :has-active-filters="$hasActiveFilters"
        />

        <div class="portal-table-surface">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="portal-table-head text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Licenca</th>
                        <th class="px-6 py-3 font-medium">Tipo</th>
                        <th class="px-6 py-3 font-medium">
                            <x-portal.table-column-filter label="Setor" form-id="licenses-filter-form" name="sector_id" all-label="Setor" min-width="min-w-0" variant="inline">
                                @foreach ($sectors as $sectorOption)
                                    <option value="{{ $sectorOption->id }}" @selected((string) $filters['sector_id'] === (string) $sectorOption->id)>{{ $sectorOption->name }}</option>
                                @endforeach
                            </x-portal.table-column-filter>
                        </th>
                        <th class="px-6 py-3 font-medium">Em uso</th>
                        <th class="px-6 py-3 font-medium">Disponiveis</th>
                        <th class="px-6 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($licenses as $license)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-900">{{ $license->vendor_name }} - {{ $license->product_name }}</p>
                                @if ($license->license_reference)
                                    <p class="mt-1 text-xs text-slate-500">Ref. {{ $license->license_reference }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                {{ $license->plan_name ?: 'Sem tipo definido' }}
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                <x-sector-badge :sector="$license->sector" mode="dot" />
                                <p class="text-xs text-slate-500">{{ $license->sector?->company?->name }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-900">{{ $license->seatsInUse() }}</p>
                                <p class="text-xs text-slate-500">de {{ $license->seats_total }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-medium {{ $license->seatsAvailable() > 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $license->seatsAvailable() }}</p>
                                <p class="text-xs text-slate-500">licenca(s) livres</p>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('licenses.show', $license) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">Abrir</a>
                                    <a href="{{ route('licenses.edit', $license) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">Editar</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <p class="text-sm font-medium text-slate-700">Nenhuma licenca cadastrada ainda.</p>
                                <p class="mt-1 text-sm text-slate-500">Cadastre a primeira linha para comecar a acompanhar com quem cada licenca esta.</p>
                                <a href="{{ route('licenses.create') }}" class="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Cadastrar primeira licenca</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $licenses->links() }}
    </div>
</x-layouts.portal>
