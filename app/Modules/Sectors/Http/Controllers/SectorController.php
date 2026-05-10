<?php

namespace App\Modules\Sectors\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Sectors\Exports\SectorsExport;
use App\Modules\Sectors\Http\Requests\SectorRequest;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Sectors\Support\SectorIndexQuery;
use App\Modules\SectorTemplates\Models\SectorTemplate;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Support\Exports\SpreadsheetExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SectorController extends Controller
{
    public function __construct(
        private readonly SectorProvisioningService $sectorProvisioningService,
        private readonly SectorIndexQuery $sectorIndexQuery,
        private readonly SpreadsheetExporter $spreadsheetExporter,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Sector::class);

        $filters = $this->sectorIndexQuery->filters($request);
        $sectors = $this->sectorIndexQuery->build(auth()->user(), $filters)->paginate(12)->withQueryString();

        return view('modules.sectors.index', [
            'sectors' => $sectors,
            'filters' => $filters,
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Sector::class);

        $filters = $this->sectorIndexQuery->filters($request);
        $export = new SectorsExport($this->sectorIndexQuery->build(auth()->user(), $filters)->get());

        return $this->spreadsheetExporter->download($export->fileName(), $export->sheets());
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Sector::class);

        $companyId = $request->integer('company_id') ?: null;
        $companyId = $companyId && Company::query()->whereKey($companyId)->exists() ? $companyId : null;

        return view('modules.sectors.create', [
            'sector' => new Sector(['company_id' => $companyId]),
            'companies' => Company::query()->orderBy('name')->get(),
            'templates' => SectorTemplate::query()->where('is_active', true)->orderBy('name')->get(),
            'returnToCompanyId' => $this->returnToCompanyId($request, $companyId),
        ]);
    }

    public function store(SectorRequest $request): RedirectResponse
    {
        $this->authorize('create', Sector::class);

        $payload = $request->validated();
        $returnToCompanyId = $payload['return_to_company_id'] ?? null;
        unset($payload['return_to_company_id']);

        $sector = Sector::query()->create([
            ...$payload,
            'slug' => Str::slug($request->string('name')),
            'color' => $request->string('color')->toString() ?: '#3D567B',
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->sectorProvisioningService->provision($sector, $sector->template);

        return $this->redirectAfterMutation($returnToCompanyId, 'Setor criado com sucesso.');
    }

    public function edit(Request $request, Sector $sector): View
    {
        $this->authorize('update', $sector);

        return view('modules.sectors.edit', [
            'sector' => $sector,
            'companies' => Company::query()->orderBy('name')->get(),
            'templates' => SectorTemplate::query()->where('is_active', true)->orderBy('name')->get(),
            'returnToCompanyId' => $this->returnToCompanyId($request),
        ]);
    }

    public function update(SectorRequest $request, Sector $sector): RedirectResponse
    {
        $this->authorize('update', $sector);

        $payload = $request->validated();
        $returnToCompanyId = $payload['return_to_company_id'] ?? null;
        unset($payload['return_to_company_id']);

        $sector->update([
            ...$payload,
            'slug' => Str::slug($request->string('name')),
            'color' => $request->string('color')->toString() ?: '#3D567B',
            'is_active' => $request->boolean('is_active', false),
        ]);

        $this->sectorProvisioningService->provision($sector);

        return $this->redirectAfterMutation($returnToCompanyId, 'Setor atualizado com sucesso.');
    }

    public function destroy(Request $request, Sector $sector): RedirectResponse
    {
        $this->authorize('delete', $sector);

        $sector->delete();

        return $this->redirectAfterMutation($this->returnToCompanyId($request), 'Setor removido com sucesso.');
    }

    private function returnToCompanyId(Request $request, ?int $fallbackCompanyId = null): ?int
    {
        $companyId = $request->integer('return_to_company_id') ?: $fallbackCompanyId;

        return $companyId && Company::query()->whereKey($companyId)->exists() ? $companyId : null;
    }

    private function redirectAfterMutation(?int $companyId, string $status): RedirectResponse
    {
        if ($companyId) {
            return redirect()->route('companies.show', $companyId)->with('status', $status);
        }

        return redirect()->route('sectors.index')->with('status', $status);
    }
}
