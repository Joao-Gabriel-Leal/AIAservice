<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Exports\CompaniesExport;
use App\Modules\Companies\Http\Requests\CompanyRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Support\CompanyIndexQuery;
use App\Modules\Shared\Support\CurrentCompanyContext;
use App\Support\Exports\SpreadsheetExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyIndexQuery $companyIndexQuery,
        private readonly SpreadsheetExporter $spreadsheetExporter,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Company::class);

        $filters = $this->companyIndexQuery->filters($request);
        $companies = $this->companyIndexQuery->build($filters)->paginate(12)->withQueryString();

        return view('modules.companies.index', compact('companies', 'filters'));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Company::class);

        $filters = $this->companyIndexQuery->filters($request);
        $export = new CompaniesExport($this->companyIndexQuery->build($filters)->get());

        return $this->spreadsheetExporter->download($export->fileName(), $export->sheets());
    }

    public function create(): View
    {
        $this->authorize('create', Company::class);

        return view('modules.companies.create', ['company' => new Company]);
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $this->authorize('create', Company::class);

        $company = Company::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('companies.show', $company)->with('status', 'Empresa criada com sucesso.');
    }

    public function show(Company $company): View
    {
        $this->authorize('view', $company);

        app(CurrentCompanyContext::class)->ensureForCompany(auth()->user(), $company->id);

        $company->load([
            'sectors' => fn ($query) => $query
                ->with(['rooms' => fn ($roomsQuery) => $roomsQuery->orderBy('name')])
                ->orderBy('name'),
        ]);

        return view('modules.companies.show', compact('company'));
    }

    public function edit(Request $request, Company $company): View
    {
        $this->authorize('update', $company);

        return view('modules.companies.edit', [
            'company' => $company,
            'returnToCompanyId' => $request->integer('return_to_company_id') ?: null,
        ]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $this->authorize('update', $company);

        $company->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', false),
        ]);

        if ($request->integer('return_to_company_id') === $company->id) {
            return redirect()->route('companies.show', $company)->with('status', 'Empresa atualizada com sucesso.');
        }

        return redirect()->route('companies.index')->with('status', 'Empresa atualizada com sucesso.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('delete', $company);

        $company->delete();

        return redirect()->route('companies.index')->with('status', 'Empresa removida com sucesso.');
    }
}
