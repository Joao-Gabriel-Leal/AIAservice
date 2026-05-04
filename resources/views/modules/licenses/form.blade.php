@php($selectedSectorId = (string) old('sector_id', $license->sector_id))
@php($selectedStatus = old('status', $license->status?->value ?? \App\Enums\LicenseStatus::ACTIVE->value))
@php($selectedBillingCycle = old('billing_cycle', $license->billing_cycle?->value))
@php($selectedCurrency = old('cost_currency', $license->cost_currency ?? 'BRL'))

<div class="space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex flex-col gap-1.5 border-b border-slate-200 pb-3">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Catalogo da licenca</p>
            <h3 class="text-lg font-semibold text-slate-900">Fornecedor, produto e setor responsavel</h3>
            <p class="text-sm text-slate-500">Organize a licenca como um contrato ou plano do setor para controlar quantidade, renovacao e uso real.</p>
        </div>

        <div class="grid gap-3 pt-4 md:grid-cols-2">
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Setor responsavel</span>
                <select name="sector_id" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                    <option value="">Selecione</option>
                    @foreach ($sectors as $sectorOption)
                        <option value="{{ $sectorOption->id }}" @selected($selectedSectorId === (string) $sectorOption->id)>{{ $sectorOption->name }}{{ $sectorOption->company ? ' - '.$sectorOption->company->name : '' }}</option>
                    @endforeach
                </select>
                @error('sector_id') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Status da licenca</span>
                <select name="status" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption->value }}" @selected($selectedStatus === $statusOption->value)>{{ $statusOption->label() }}</option>
                    @endforeach
                </select>
                @error('status') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Fornecedor</span>
                <input type="text" name="vendor_name" value="{{ old('vendor_name', $license->vendor_name) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                <span class="mt-1 block text-xs text-slate-500">Ex.: Microsoft, SAP, Adobe.</span>
                @error('vendor_name') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Produto</span>
                <input type="text" name="product_name" value="{{ old('product_name', $license->product_name) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                <span class="mt-1 block text-xs text-slate-500">Ex.: Microsoft 365, SAP Business One.</span>
                @error('product_name') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Plano ou tipo</span>
                <input type="text" name="plan_name" value="{{ old('plan_name', $license->plan_name) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                <span class="mt-1 block text-xs text-slate-500">Ex.: Business Standard, Profissional, Limited Logistics.</span>
                @error('plan_name') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Referencia da licenca</span>
                <input type="text" name="license_reference" value="{{ old('license_reference', $license->license_reference) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                <span class="mt-1 block text-xs text-slate-500">Contrato, tenant, chave parcial ou codigo interno.</span>
                @error('license_reference') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-4">
        <div class="flex flex-col gap-1.5 border-b border-slate-200 pb-3">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Capacidade e custo</p>
            <h3 class="text-lg font-semibold text-slate-900">Quantidade, ciclo financeiro e fornecedor da compra</h3>
            <p class="text-sm text-slate-500">Aqui fica a parte operacional que o setor vai usar para controlar disponibilidade e renovacao.</p>
        </div>

        <div class="grid gap-3 pt-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Quantidade total de licencas</span>
                <input type="number" min="0" name="seats_total" value="{{ old('seats_total', $license->seats_total ?? 0) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                @error('seats_total') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Ciclo de cobranca</span>
                <select name="billing_cycle" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                    <option value="">Nao informar</option>
                    @foreach ($billingCycles as $billingCycleOption)
                        <option value="{{ $billingCycleOption->value }}" @selected($selectedBillingCycle === $billingCycleOption->value)>{{ $billingCycleOption->label() }}</option>
                    @endforeach
                </select>
                @error('billing_cycle') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Custo</span>
                <input type="number" min="0" step="0.01" name="cost_amount" value="{{ old('cost_amount', $license->cost_amount) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                @error('cost_amount') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Moeda</span>
                <input type="text" name="cost_currency" value="{{ $selectedCurrency }}" maxlength="3" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                @error('cost_currency') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block md:col-span-2">
                <span class="mb-2 block text-sm font-medium text-slate-700">Fornecedor da compra</span>
                <input type="text" name="supplier_name" value="{{ old('supplier_name', $license->supplier_name) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                <span class="mt-1 block text-xs text-slate-500">Revenda, parceiro ou fornecedor responsavel pela venda/renovacao.</span>
                @error('supplier_name') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-4">
        <div class="flex flex-col gap-1.5 border-b border-slate-200 pb-3">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Datas e observacoes</p>
            <h3 class="text-lg font-semibold text-slate-900">Compra, renovacao, expiracao e notas</h3>
            <p class="text-sm text-slate-500">Esses campos ajudam a antecipar vencimentos e manter o contexto da licenca no setor.</p>
        </div>

        <div class="grid gap-3 pt-4 md:grid-cols-3">
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Data da compra</span>
                <input type="date" name="purchased_at" value="{{ old('purchased_at', optional($license->purchased_at)->format('Y-m-d')) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                @error('purchased_at') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Proxima renovacao</span>
                <input type="date" name="renewal_date" value="{{ old('renewal_date', optional($license->renewal_date)->format('Y-m-d')) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                @error('renewal_date') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Data de expiracao</span>
                <input type="date" name="expires_at" value="{{ old('expires_at', optional($license->expires_at)->format('Y-m-d')) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                @error('expires_at') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block md:col-span-3">
                <span class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <input type="checkbox" name="auto_renew" value="1" class="rounded border-slate-300 text-slate-900" @checked(old('auto_renew', $license->auto_renew))>
                    <span class="text-sm font-medium text-slate-700">Renovacao automatica</span>
                </span>
                @error('auto_renew') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block md:col-span-3">
                <span class="mb-2 block text-sm font-medium text-slate-700">Observacoes</span>
                <textarea name="notes" rows="4" class="min-h-[120px] w-full rounded-xl border border-slate-300 px-4 py-3">{{ old('notes', $license->notes) }}</textarea>
                <span class="mt-1 block text-xs text-slate-500">Use para registrar termos de uso, observacoes da renovacao, regras do fornecedor ou contexto do setor.</span>
                @error('notes') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>
        </div>
    </section>
</div>
