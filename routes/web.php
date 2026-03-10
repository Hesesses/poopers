<?php

use App\Http\Controllers\MarketingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketingController::class, 'home']);
Route::get('/privacy', [MarketingController::class, 'privacy']);
Route::get('/terms', [MarketingController::class, 'terms']);
Route::get('/join/{code}', [MarketingController::class, 'join']);

Route::get('/auth/verify', function (\Illuminate\Http\Request $request) {
    $token = $request->query('token');

    return redirect("poopers://auth/verify?token={$token}");
})->name('auth.verify');

Route::get('/.well-known/apple-app-site-association', function () {
    return response()->json([
        'applinks' => [
            'apps' => [],
            'details' => [
                [
                    'appID' => '5KJXC2X3UB.com.hesesport.poopers',
                    'paths' => ['/join/*'],
                ],
            ],
        ],
    ]);
});
