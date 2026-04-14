<?php

namespace App\Modules\Sectors\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Sectors\Http\Requests\SectorRequest;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class SectorController extends Controller
{
    public function __construct(
        private readonly SectorProvisioningService $sectorProvisioningService,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Sector::class);

        $query = Sector::query()->with('company')->latest();

        if (auth()->user()->isSectorAdmin()) {
            $query->whereIn('id', auth()->user()->adminSectorIds());
        }

        $sectors = $query->paginate(12);

        return view('modules.sectors.index', compact('sectors'));
    }

    public function create(): View
    {
        $this->authorize('create', Sector::class);

        return view('modules.sectors.create', [
            'sector' => new Sector(),
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function store(SectorRequest $request): RedirectResponse
    {
        $this->authorize('create', Sector::class);

        $sector = Sector::query()->create([
            ...$request->validated(),
            'slug' => Str::slug($request->string('name')),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->sectorProvisioningService->provision($sector);

        return redirect()->route('sectors.index')->with('status', 'Setor criado com sucesso.');
    }

    public function edit(Sector $sector): View
    {
        $this->authorize('update', $sector);

        return view('modules.sectors.edit', [
            'sector' => $sector,
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function update(SectorRequest $request, Sector $sector): RedirectResponse
    {
        $this->authorize('update', $sector);

        $sector->update([
            ...$request->validated(),
            'slug' => Str::slug($request->string('name')),
            'is_active' => $request->boolean('is_active', false),
        ]);

        $this->sectorProvisioningService->provision($sector);

        return redirect()->route('sectors.index')->with('status', 'Setor atualizado com sucesso.');
    }

    public function destroy(Sector $sector): RedirectResponse
    {
        $this->authorize('delete', $sector);

        $sector->delete();

        return redirect()->route('sectors.index')->with('status', 'Setor removido com sucesso.');
    }
}
