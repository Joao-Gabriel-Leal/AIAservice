<div class="grid gap-6 md:grid-cols-2">
    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">Empresa</span>
        <select name="company_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3" required>
            <option value="">Selecione</option>
            @foreach ($companies as $companyOption)
                <option value="{{ $companyOption->id }}" @selected(old('company_id', $sector->company_id) == $companyOption->id)>{{ $companyOption->name }}</option>
            @endforeach
        </select>
        @error('company_id') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">Nome do setor</span>
        <input type="text" name="name" value="{{ old('name', $sector->name) }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3" required>
        @error('name') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">Cor do setor</span>
        <div class="flex items-center gap-3 rounded-2xl border border-slate-300 bg-white px-4 py-3">
            <input type="color" value="{{ old('color', $sector->color ?: '#3D567B') }}" oninput="this.nextElementSibling.value = this.value.toUpperCase()" class="h-10 w-14 rounded-lg border border-slate-200 bg-transparent p-0">
            <input type="text" name="color" value="{{ old('color', $sector->color ?: '#3D567B') }}" class="w-full rounded-xl border border-slate-200 px-3 py-2 uppercase" placeholder="#3D567B">
        </div>
        @error('color') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="block md:col-span-2">
        <span class="mb-2 block text-sm font-medium text-slate-700">Descrição</span>
        <textarea name="description" rows="4" class="w-full rounded-2xl border border-slate-300 px-4 py-3">{{ old('description', $sector->description) }}</textarea>
        @error('description') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $sector->is_active ?? true)) class="size-4 rounded border-slate-300">
        <span class="text-sm text-slate-700">Setor ativo</span>
    </label>
</div>
