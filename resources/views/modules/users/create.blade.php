<x-layouts.portal title="Novo usuário">
    <form method="POST" action="{{ route('users.store') }}" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @include('modules.users.form')

        <div class="flex justify-end gap-3">
            <a href="{{ route('users.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancelar</a>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Salvar usuário</button>
        </div>
    </form>
</x-layouts.portal>
