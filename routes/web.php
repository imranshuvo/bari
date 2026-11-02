<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/language/{locale}', LocaleController::class)->name('locale');

Route::get('/invitations/{token}', [InvitationController::class, 'accept'])
    ->name('invitations.accept');
