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

    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">Senha {{ $userModel->exists ? '(deixe em branco para manter)' : '' }}</span>
        <input type="password" name="password" class="w-full rounded-2xl border border-slate-300 px-4 py-3" {{ $userModel->exists ? '' : 'required' }}>
        @error('password') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">Confirmar senha</span>
        <input type="password" name="password_confirmation" class="w-full rounded-2xl border border-slate-300 px-4 py-3" {{ $userModel->exists ? '' : 'required' }}>
    </label>

    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">Perfil</span>
        <select name="role" class="w-full rounded-2xl border border-slate-300 px-4 py-3" required>
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $userModel->role?->value) == $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('role') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">Setor</span>
        <select name="sector_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3" @disabled(auth()->user()->isSectorAdmin())>
            <option value="">Selecione</option>
            @foreach ($sectors as $sectorOption)
                <option value="{{ $sectorOption->id }}" @selected(old('sector_id', $userModel->sector_id ?: auth()->user()->sector_id) == $sectorOption->id)>{{ $sectorOption->name }}</option>
            @endforeach
        </select>
        @if (auth()->user()->isSectorAdmin())
            <input type="hidden" name="sector_id" value="{{ auth()->user()->sector_id }}">
        @endif
        @error('sector_id') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">Sala</span>
        <select name="room_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
            <option value="">Selecione</option>
            @foreach ($rooms as $roomOption)
                <option value="{{ $roomOption->id }}" @selected(old('room_id', $userModel->room_id) == $roomOption->id)>{{ $roomOption->name }} ({{ $roomOption->sector?->name }})</option>
            @endforeach
        </select>
        @error('room_id') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
        <input type="checkbox" name="must_change_password" value="1" @checked(old('must_change_password', $userModel->must_change_password ?? ! $userModel->exists)) class="size-4 rounded border-slate-300">
        <span class="text-sm text-slate-700">Obrigar troca de senha no primeiro acesso</span>
    </label>

    <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $userModel->is_active ?? true)) class="size-4 rounded border-slate-300">
        <span class="text-sm text-slate-700">Usuário ativo</span>
    </label>
</div>
