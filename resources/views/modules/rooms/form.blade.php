<div class="grid gap-6 md:grid-cols-2">
    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">Setor</span>
        <select name="sector_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3" required>
            <option value="">Selecione</option>
            @foreach ($sectors as $sectorOption)
                <option value="{{ $sectorOption->id }}" @selected(old('sector_id', $room->sector_id) == $sectorOption->id)>{{ $sectorOption->name }}</option>
            @endforeach
        </select>
        @error('sector_id') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">Nome da sala</span>
        <input type="text" name="name" value="{{ old('name', $room->name) }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3" required>
        @error('name') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="block md:col-span-2">
        <span class="mb-2 block text-sm font-medium text-slate-700">Descrição</span>
        <textarea name="description" rows="4" class="w-full rounded-2xl border border-slate-300 px-4 py-3">{{ old('description', $room->description) }}</textarea>
        @error('description') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $room->is_active ?? true)) class="size-4 rounded border-slate-300">
        <span class="text-sm text-slate-700">Sala ativa</span>
    </label>
</div>
