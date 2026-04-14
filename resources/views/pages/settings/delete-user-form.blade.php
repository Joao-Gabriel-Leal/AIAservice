<?php

use Livewire\Component;

new class extends Component {}; ?>

<div class="ui-panel rounded-3xl border border-rose-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Excluir conta</h3>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">
                Use esta acao apenas se realmente quiser remover o acesso e os recursos vinculados a esta conta.
            </p>
        </div>

        <flux:modal.trigger name="confirm-user-deletion">
            <button
                type="button"
                class="rounded-2xl border border-rose-200 bg-white px-4 py-3 text-sm font-medium text-rose-700 transition hover:border-rose-300 hover:bg-rose-50"
                data-test="delete-user-button"
            >
                Excluir conta
            </button>
        </flux:modal.trigger>
    </div>

    <p class="mt-4 rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        A exclusao e permanente e remove os dados associados ao seu usuario neste ambiente.
    </p>

    <livewire:pages::settings.delete-user-modal />
</div>
