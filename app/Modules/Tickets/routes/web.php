<?php

use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Http\Controllers\TicketAttachmentController;
use App\Modules\Tickets\Http\Controllers\TicketExportController;
use App\Modules\Tickets\Livewire\CentralPage;
use App\Modules\Tickets\Livewire\CreatePage;
use App\Modules\Tickets\Livewire\IndexPage;
use App\Modules\Tickets\Livewire\MinePage;
use App\Modules\Tickets\Livewire\SettingsPage;
use App\Modules\Tickets\Livewire\ShowPage;
use App\Modules\Tickets\Models\TicketBoard;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('tickets')->name('tickets.')->group(function () {
    Route::get('/', IndexPage::class)->name('index');
    Route::get('/export', TicketExportController::class)->name('export');
    Route::get('/central', CentralPage::class)->name('central');
    Route::get('/my', MinePage::class)->name('mine');
    Route::get('/board/{boardOrSector?}', function (?string $boardOrSector = null) {
        $board = $boardOrSector ? TicketBoard::query()->find($boardOrSector) : null;
        $sector = null;

        if (! $board && $boardOrSector) {
            $sector = Sector::query()->find($boardOrSector);
            $board = $sector?->boards()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->first();
        }

        return redirect()->route('tickets.index', array_filter([
            'view' => 'stages',
            'sector' => $board?->sector_id ?? $sector?->id,
            'board' => $board?->id,
        ]));
    })->name('board');
    Route::get('/create/{catalogItem?}', CreatePage::class)->name('create');
    Route::get('/settings/{board?}', SettingsPage::class)->name('settings');
    Route::get('/attachments/{attachment}/inline', [TicketAttachmentController::class, 'inline'])->name('attachments.inline');
    Route::get('/attachments/{attachment}', [TicketAttachmentController::class, 'show'])->name('attachments.show');
    Route::get('/{ticket}', ShowPage::class)->name('show');
});
