<?php

namespace App\Http\Controllers;

use App\Modules\Shared\Support\CurrentCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyContextController extends Controller
{
    public function store(Request $request, CurrentCompanyContext $context): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
        ]);

        abort_unless($context->switch($request->user(), (int) $validated['company_id']), 403);

        return redirect()->back(303)->with('status', 'Empresa atual alterada.');
    }
}
