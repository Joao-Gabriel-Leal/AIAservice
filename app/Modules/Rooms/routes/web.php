<?php

use App\Modules\Rooms\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('rooms/export', [RoomController::class, 'export'])->name('rooms.export');
    Route::resource('rooms', RoomController::class)->except(['show']);
});
