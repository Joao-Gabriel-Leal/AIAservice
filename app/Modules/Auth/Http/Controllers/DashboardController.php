<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Auth\Exports\DashboardExport;
use App\Modules\Auth\Support\DashboardDataBuilder;
use App\Support\Exports\SpreadsheetExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardDataBuilder $dashboardDataBuilder,
        private readonly SpreadsheetExporter $spreadsheetExporter,
    ) {
    }

    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = auth()->user();
        $period = $this->resolvePeriod($request->string('period')->toString());
        $data = $this->dashboardDataBuilder->build($user, $period);

        return view('modules.dashboard.index', $data);
    }

    public function export(Request $request): BinaryFileResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $period = $this->resolvePeriod($request->string('period')->toString());
        $export = new DashboardExport($this->dashboardDataBuilder->build($user, $period));

        return $this->spreadsheetExporter->download($export->fileName(), $export->sheets());
    }

    private function resolvePeriod(string $period): int
    {
        return in_array((int) $period, [7, 30, 90], true) ? (int) $period : 7;
    }
}
