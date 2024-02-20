<?php

use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::post('/clientes/{id}/transacoes', [TransactionController::class, 'store']);
Route::get('/clientes/{id}/extrato', [TransactionController::class, 'index']);
