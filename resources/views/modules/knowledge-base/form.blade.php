@php($visibilityValue = old('visibility', $article->visibility?->value ?? \App\Enums\KnowledgeBaseVisibility::PUBLIC->value))
@php($selectedSector = $sectors->firstWhere('id', (int) old('sector_id', $article->sector_id)))

<div class="grid gap-6">
    <section class="rounded-3xl border border-slate-200 bg-slate-50/70 p-6">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Dados principais</h2>
            <p class="mt-1 text-sm text-slate-500">Defina o setor, o titulo e a visibilidade do artigo.</p>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Setor</span>
                <select name="sector_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3" required>
                    <option value="">Selecione</option>
                    @foreach ($sectors as $sectorOption)
                        <option value="{{ $sectorOption->id }}" @selected(old('sector_id', $article->sector_id) == $sectorOption->id)>
                            {{ $sectorOption->name }}
                            @if ($sectorOption->company)
                                - {{ $sectorOption->company->name }}
                            @endif
                        </option>
                    @endforeach
                </select>
                @if ($selectedSector)
                    <div class="mt-2">
                        <x-sector-badge :sector="$selectedSector" mode="chip">{{ $selectedSector->company?->name }}</x-sector-badge>
                    </div>
                @endif
                @error('sector_id') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Visibilidade</span>
                <select name="visibility" class="w-full rounded-2xl border border-slate-300 px-4 py-3" required>
                    @foreach (\App\Enums\KnowledgeBaseVisibility::cases() as $visibility)
                        <option value="{{ $visibility->value }}" @selected($visibilityValue === $visibility->value)>{{ $visibility->label() }}</option>
                    @endforeach
                </select>
                @error('visibility') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block md:col-span-2">
                <span class="mb-2 block text-sm font-medium text-slate-700">Titulo</span>
                <input type="text" name="title" value="{{ old('title', $article->title) }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3" placeholder="Ex.: Como acessar a VPN da empresa" required>
                @error('title') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 md:self-end">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $article->is_active ?? true)) class="size-4 rounded border-slate-300">
                <span class="text-sm text-slate-700">Artigo ativo</span>
            </label>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-6">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Resumo e detalhes</h2>
            <p class="mt-1 text-sm text-slate-500">Use um resumo curto para busca e um conteudo mais detalhado para orientar o usuario.</p>
        </div>

        <div class="grid gap-6">
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Resumo</span>
                <textarea name="summary" rows="4" class="w-full rounded-2xl border border-slate-300 px-4 py-3" placeholder="Explique rapidamente quando este artigo deve ser usado." required>{{ old('summary', $article->summary) }}</textarea>
                @error('summary') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Conteudo detalhado</span>
                <textarea name="content" rows="12" class="w-full rounded-2xl border border-slate-300 px-4 py-3" placeholder="Descreva passo a passo, requisitos, observacoes e links internos." required>{{ old('content', $article->content) }}</textarea>
                @error('content') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-6">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Anexos</h2>
            <p class="mt-1 text-sm text-slate-500">Adicione arquivos de apoio, prints, PDFs ou manuais relacionados ao artigo.</p>
        </div>

        <div class="space-y-4">
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Novos anexos</span>
                <input type="file" name="attachments[]" multiple class="block w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm">
                <span class="mt-2 block text-xs text-slate-500">Limite de 10 MB por arquivo.</span>
                @error('attachments') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                @error('attachments.*') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            @if ($article->relationLoaded('attachments') && $article->attachments->isNotEmpty())
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-medium text-slate-800">Anexos atuais</p>
                    <div class="mt-3 space-y-3">
                        @foreach ($article->attachments as $attachment)
                            <label class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ $attachment->original_name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $attachment->humanSize() }}
                                        @if ($attachment->uploader)
                                            • enviado por {{ $attachment->uploader->name }}
                                        @endif
                                    </p>
                                </div>
                                <span class="inline-flex items-center gap-2 text-sm text-slate-600">
                                    <input type="checkbox" name="remove_attachments[]" value="{{ $attachment->id }}" class="size-4 rounded border-slate-300">
                                    Remover
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
</div>
