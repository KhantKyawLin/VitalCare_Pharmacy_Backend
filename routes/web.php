<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SocialMetadataController;

Route::get('/', function () {
    return view('welcome');
});

// Social Media Shareable Links (for rich previews)
Route::get('/share/product/{id}', [SocialMetadataController::class, 'product'])->name('share.product');
Route::get('/share/tip/{id}', [SocialMetadataController::class, 'healthTip'])->name('share.tip');
