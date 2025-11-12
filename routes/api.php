<?php

use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::prefix('wallets')->group(function () {
    Route::post('/', [WalletController::class, 'store']);
    Route::get('/', [WalletController::class, 'index']);
    Route::get('{id}', [WalletController::class, 'show']);
    Route::get('{id}/balance', [WalletController::class, 'balance']);
    Route::get('{id}/transactions', [WalletController::class, 'transactions']);
    Route::post('{id}/deposit', [WalletController::class, 'deposit']);
    Route::post('{id}/withdraw', [WalletController::class, 'withdraw']);
});

Route::post('/transfers', [TransferController::class, 'transfer']);
