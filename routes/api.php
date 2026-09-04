<?php

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/clients', [ClientController::class, 'index']);
Route::post('/clients', [ClientController::class, 'store']);
Route::get('/clients/{client}', [ClientController::class, 'show']);

Route::get('/clients/{client}/transactions', [TransactionController::class, 'index']);
Route::post('/clients/{client}/transactions', [TransactionController::class, 'store']);
