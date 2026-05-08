<div class="space-y-8">
    <div class="grid gap-6 md:grid-cols-2">
        <label class="block">
            <span class="mb-2 block text-sm font-medium text-slate-700">Nome</span>
            <input type="text" name="name" value="{{ old('name', $userModel->name) }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3" required>
            @error('name') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="mb-2 block text-sm font-medium text-slate-700">Email</span>
            <input type="email" name="email" value="{{ old('email', $userModel->email) }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3" required>
            @error('email') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
        </label>

        @if ($userModel->exists)
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Senha (deixe em branco para manter)</span>
                <input type="password" name="password" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                @error('password') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Confirmar senha</span>
                <input type="password" name="password_confirmation" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
            </label>
        @else
            <div class="md:col-span-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                O usuario sera criado com troca obrigatoria no primeiro acesso e recebera um link para definir a senha.
            </div>
        @endif

        @if (auth()->user()->isGlobalAdmin())
            <label class="block md:col-span-2">
                <span class="mb-2 block text-sm font-medium text-slate-700">Perfil global</span>
                <select name="global_role" class="w-full rounded-2xl border border-slate-300 px-4 py-3" required>
                    @foreach ($globalRoles as $value => $label)
                        <option value="{{ $value }}" @selected(old('global_role', $userModel->global_role?->value ?? \App\Enums\GlobalUserRole::COLLABORATOR->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('global_role') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>
        @else
            <input type="hidden" name="global_role" value="{{ \App\Enums\GlobalUserRole::COLLABORATOR->value }}">
            <div class="md:col-span-2 rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                Novos usuarios criados por gestores entram como colaboradores e recebem acesso pelos vinculos abaixo.
            </div>
        @endif
    </div>

    <section class="space-y-4 rounded-3xl border border-slate-200 bg-slate-50 p-5">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Acessos por setor</h3>
            <p class="mt-1 text-sm text-slate-500">Cada colaborador pode ter um unico nivel por setor. Deixe em branco para nao conceder acesso naquele setor.</p>
        </div>

        @error('sector_accesses') <span class="block text-sm text-rose-600">{{ $message }}</span> @enderror

        @if ($sectors->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-6 text-sm text-slate-500">
                Nenhum setor disponivel para vinculacao neste contexto.
            </div>
        @else
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Setor</th>
                            <th class="px-4 py-3 font-medium">Empresa</th>
                            <th class="px-4 py-3 font-medium">Nivel de acesso</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sectors as $sectorOption)
                            @php($currentAccess = $userModel->sectorAccesses->firstWhere('sector_id', $sectorOption->id))
                            <tr>
                                <td class="px-4 py-4 font-medium text-slate-900">{{ $sectorOption->name }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $sectorOption->company?->name ?? 'Sem empresa' }}</td>
                                <td class="px-4 py-4">
                                    <select name="sector_accesses[{{ $sectorOption->id }}]" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                                        <option value="">Sem acesso</option>
                                        @foreach ($accessLevels as $value => $label)
                                            <option
                                                value="{{ $value }}"
                                                @selected(old("sector_accesses.{$sectorOption->id}", $currentAccess?->access_level?->value) === $value)
                                            >
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error("sector_accesses.{$sectorOption->id}") <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="grid gap-4 md:grid-cols-2">
        @if ($userModel->exists)
            <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <input type="checkbox" name="must_change_password" value="1" @checked(old('must_change_password', $userModel->must_change_password ?? false)) class="size-4 rounded border-slate-300">
                <span class="text-sm text-slate-700">Obrigar troca de senha no proximo acesso</span>
            </label>
        @else
            <input type="hidden" name="must_change_password" value="1">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                Troca obrigatoria ativada para o primeiro acesso.
            </div>
        @endif

        <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $userModel->is_active ?? true)) class="size-4 rounded border-slate-300">
            <span class="text-sm text-slate-700">Usuario ativo</span>
        </label>
    </div>
</div>
