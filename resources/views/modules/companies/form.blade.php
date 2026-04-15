<div class="grid gap-6 md:grid-cols-2">
    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">Nome</span>
        <input type="text" name="name" value="{{ old('name', $company->name) }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3" required>
        @error('name') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">Razão social</span>
        <input type="text" name="legal_name" value="{{ old('legal_name', $company->legal_name) }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
        @error('legal_name') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">Documento</span>
        <input type="text" name="document" value="{{ old('document', $company->document) }}" inputmode="numeric" maxlength="18" data-mask="cpf-cnpj" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
        @error('document') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">Email</span>
        <input type="email" name="email" value="{{ old('email', $company->email) }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
        @error('email') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">Telefone</span>
        <input type="text" name="phone" value="{{ old('phone', $company->phone) }}" inputmode="numeric" maxlength="15" data-mask="phone" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
        @error('phone') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
    </label>

    <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $company->is_active ?? true)) class="size-4 rounded border-slate-300">
        <span class="text-sm text-slate-700">Empresa ativa</span>
    </label>
</div>
