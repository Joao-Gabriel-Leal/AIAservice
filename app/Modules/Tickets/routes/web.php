<?php

use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Http\Controllers\MineTicketExportController;
use App\Modules\Tickets\Http\Controllers\TicketAttachmentController;
use App\Modules\Tickets\Http\Controllers\TicketExportController;
use App\Modules\Tickets\Livewire\BoardDirectoryPage;
use App\Modules\Tickets\Livewire\CentralPage;
use App\Modules\Tickets\Livewire\CreatePage;
use App\Modules\Tickets\Livewire\IndexPage;
use App\Modules\Tickets\Livewire\MinePage;
use App\Modules\Tickets\Livewire\OperationalQueuePage;
use App\Modules\Tickets\Livewire\SettingsPage;
use App\Modules\Tickets\Livewire\ShowPage;
use App\Modules\Tickets\Livewire\TrashPage;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Shared\Support\CurrentCompanyContext;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('tickets')->name('tickets.')->group(function () {
    Route::get('/', BoardDirectoryPage::class)->name('index');
    Route::get('/export', TicketExportController::class)->name('export');
    Route::get('/central', CentralPage::class)->name('central');
    Route::get('/my', MinePage::class)->name('mine');
    Route::get('/my/export', MineTicketExportController::class)->name('mine.export');
    Route::get('/queue', OperationalQueuePage::class)->name('queue');
    Route::get('/trash', TrashPage::class)->name('trash');
    Route::get('/boards/{board}', IndexPage::class)->name('board.show');
    Route::get('/board/{boardOrSector?}', function (?string $boardOrSector = null) {
        if (! $boardOrSector) {
            return redirect()->route('tickets.index');
        }

        $board = $boardOrSector ? TicketBoard::query()->find($boardOrSector) : null;
        $sector = null;

        if (! $board && $boardOrSector) {
            $sector = Sector::query()->find($boardOrSector);
            $boardQuery = $sector?->boards()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name');

            if ($boardQuery && ! auth()->user()->isSuperAdmin()) {
                $boardQuery->whereIn('id', auth()->user()->operationalBoardIds());
            }

            $board = $boardQuery?->first();
        }

        if (! $board) {
            return redirect()->route('tickets.index');
        }

        abort_unless(auth()->user()->canOperateBoard($board), 403);
        abort_unless(app(CurrentCompanyContext::class)->ensureForCompany(auth()->user(), (int) $board->sector?->company_id), 403);

        return redirect()->route('tickets.board.show', [
            'board' => $board,
            'view' => 'stages',
        ]);
    })->name('board');
    Route::get('/create/{catalogItem?}', CreatePage::class)->name('create');
    Route::get('/settings/{board?}', SettingsPage::class)->name('settings');
    Route::get('/attachments/{attachment}/inline', [TicketAttachmentController::class, 'inline'])->name('attachments.inline');
    Route::get('/attachments/{attachment}', [TicketAttachmentController::class, 'show'])->name('attachments.show');
    Route::get('/{ticket}', ShowPage::class)->name('show');
});
