<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::get('/users', [AuthController::class, 'getAllUsers']);
Route::get('/users/{id}', [AuthController::class, 'getUserDetail']);
Route::post('/profile', [AuthController::class, 'getProfileByEmail']);
Route::put('/profile/update', [AuthController::class, 'updateProfile']);
Route::post('/password/request-otp', [AuthController::class, 'sendOtpForResetPassword']);
Route::post('/password/verify-otp', [AuthController::class, 'verifyOtpForResetPassword']);
Route::post('/password/update', [AuthController::class, 'updatePassword']);







