<?php

use App\Modules\KnowledgeBase\Http\Controllers\KnowledgeBaseArticleController;
use App\Modules\KnowledgeBase\Http\Controllers\KnowledgeBaseAttachmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('knowledge-base')->name('knowledge-base.')->group(function () {
    Route::get('/', [KnowledgeBaseArticleController::class, 'index'])->name('index');
    Route::get('/export', [KnowledgeBaseArticleController::class, 'export'])->name('export');
    Route::get('/attachments/{attachment}', [KnowledgeBaseAttachmentController::class, 'show'])->name('attachments.show');
    Route::get('/manage', [KnowledgeBaseArticleController::class, 'manage'])->name('manage');
    Route::get('/manage/export', [KnowledgeBaseArticleController::class, 'exportManage'])->name('manage.export');
    Route::get('/create', [KnowledgeBaseArticleController::class, 'create'])->name('create');
    Route::post('/', [KnowledgeBaseArticleController::class, 'store'])->name('store');
    Route::get('/{article}', [KnowledgeBaseArticleController::class, 'show'])->name('show');
    Route::get('/{article}/edit', [KnowledgeBaseArticleController::class, 'edit'])->name('edit');
    Route::put('/{article}', [KnowledgeBaseArticleController::class, 'update'])->name('update');
    Route::delete('/{article}', [KnowledgeBaseArticleController::class, 'destroy'])->name('destroy');
});
