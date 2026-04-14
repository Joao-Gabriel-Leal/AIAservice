<?php

use App\Modules\Tickets\Http\Controllers\TicketAttachmentController;
use App\Modules\Tickets\Livewire\BoardPage;
use App\Modules\Tickets\Livewire\CreatePage;
use App\Modules\Tickets\Livewire\SettingsPage;
use App\Modules\Tickets\Livewire\ShowPage;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('tickets')->name('tickets.')->group(function () {
    Route::get('/board/{sector?}', BoardPage::class)->name('board');
    Route::get('/create/{catalogItem?}', CreatePage::class)->name('create');
    Route::get('/settings/{sector?}', SettingsPage::class)
        ->middleware('role:super_admin,sector_admin')
        ->name('settings');
    Route::get('/attachments/{attachment}', [TicketAttachmentController::class, 'show'])->name('attachments.show');
    Route::get('/{ticket}', ShowPage::class)->name('show');
});
