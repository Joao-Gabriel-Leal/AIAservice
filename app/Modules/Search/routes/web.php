<?php

use App\Modules\Search\Livewire\SearchPage;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:dev'])->group(function () {
    Route::get('/search', SearchPage::class)->name('search');
});
