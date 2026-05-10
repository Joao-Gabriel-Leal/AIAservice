<x-layouts.portal title="Editar sala">
    @php($roomReturnToCompanyId = old('return_to_company_id', $returnToCompanyId ?? null))

    <form method="POST" action="{{ route('rooms.update', $room) }}" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('modules.rooms.form')

        <div class="flex justify-end gap-3">
            <a href="{{ $roomReturnToCompanyId ? route('companies.show', $roomReturnToCompanyId) : route('rooms.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancelar</a>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Salvar alterações</button>
        </div>
    </form>
</x-layouts.portal>
