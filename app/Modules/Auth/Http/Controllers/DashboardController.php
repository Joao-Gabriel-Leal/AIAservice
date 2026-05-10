<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Auth\Exports\DashboardExport;
use App\Modules\Auth\Support\DashboardDataBuilder;
use App\Modules\Auth\Support\DashboardDateRange;
use App\Support\Exports\SpreadsheetExporter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DashboardController extends Controller
{
    private const DEFAULT_PERIOD_DAYS = 7;

    private const MAX_CUSTOM_RANGE_DAYS = 365;

    private const PERIOD_OPTIONS = [7, 30, 90];

    public function __construct(
        private readonly DashboardDataBuilder $dashboardDataBuilder,
        private readonly SpreadsheetExporter $spreadsheetExporter,
    ) {}

    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = auth()->user();
        $dateRange = $this->resolveDateRange($request);
        $data = $this->dashboardDataBuilder->build($user, $dateRange, $request->integer('sector_id') ?: null, false);

        return view('modules.dashboard.index', $data);
    }

    public function export(Request $request): BinaryFileResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $dateRange = $this->resolveDateRange($request);
        $export = new DashboardExport(
            $this->dashboardDataBuilder->build($user, $dateRange, $request->integer('sector_id') ?: null),
        );

        return $this->spreadsheetExporter->download($export->fileName(), $export->sheets());
    }

    private function resolveDateRange(Request $request): DashboardDateRange
    {
        $dateFrom = $this->parseDate($request->string('date_from')->toString());
        $dateTo = $this->parseDate($request->string('date_to')->toString());

        if ($dateFrom || $dateTo) {
            $from = $dateFrom ?? $dateTo->subDays(self::DEFAULT_PERIOD_DAYS - 1);
            $to = $dateTo ?? CarbonImmutable::now();

            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }

            if (((int) $from->diffInDays($to) + 1) > self::MAX_CUSTOM_RANGE_DAYS) {
                $from = $to->subDays(self::MAX_CUSTOM_RANGE_DAYS - 1);
            }

            return new DashboardDateRange($from->startOfDay(), $to->endOfDay());
        }

        return DashboardDateRange::forPreset(
            $this->resolvePeriod($request->string('period')->toString()),
        );
    }

    private function parseDate(string $date): ?CarbonImmutable
    {
        $date = trim($date);

        if ($date === '') {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            return null;
        }

        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            return null;
        }

        return $parsed->startOfDay();
    }

    private function resolvePeriod(string $period): int
    {
        return in_array((int) $period, self::PERIOD_OPTIONS, true) ? (int) $period : self::DEFAULT_PERIOD_DAYS;
    }
}
