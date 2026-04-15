<?php

use App\Modules\Search\Livewire\SearchPage;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/search', SearchPage::class)->name('search');
});
