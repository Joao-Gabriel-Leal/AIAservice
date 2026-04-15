<?php

use App\Modules\Assets\Http\Controllers\AssetController;
use Illuminate\Support\Facades\Route;

Route::get('a/{asset}', [AssetController::class, 'publicShow'])->name('assets.public.show');

Route::middleware('auth')->group(function () {
    Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('assets/export', [AssetController::class, 'export'])->name('assets.export');
    Route::get('assets/create', [AssetController::class, 'create'])->name('assets.create');
    Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
    Route::get('assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::get('assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
    Route::put('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
    Route::get('assets/{asset}/movement', [AssetController::class, 'createMovement'])->name('assets.movement.create');
    Route::post('assets/{asset}/movement', [AssetController::class, 'storeMovement'])->name('assets.movement.store');
});
