<?php

use App\Enums\UserGenderEnum;
use App\Enums\UserLanguageLevelEnum;
use App\Enums\UserQualificationTypeEnum;
use App\Helpers\ResponseData;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Resume\ResumeController;
use App\Http\Controllers\Api\User\UserController;
use App\Http\Controllers\Api\User\UserResumeController;
use App\Services\Bot\ProcessBotService;
use Illuminate\Support\Facades\Route;

// Return enumerates data
Route::get('/enums', function () {

    return ResponseData::success('Enum\'s retrieved', [
        'user_gender' => UserGenderEnum::toSelectArray(),
        'user_language_level' => UserLanguageLevelEnum::toSelectArray(),
        'user_qualification_type' => UserQualificationTypeEnum::toSelectArray(),
    ]);

});

// Unauthenticated routes
Route::group([
    'prefix' => 'auth',
], function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
});

// Only authenticated users can access the group routes bellow
Route::group([

    'middleware' => 'auth:sanctum',
    'prefix' => 'client',

], function () {

    Route::prefix('/user')->group(function () {

        Route::get('/', [AuthController::class, 'user']);
        Route::put('/update', [UserController::class, 'update']);
        Route::get('/resumes', UserResumeController::class);

    });

    Route::prefix('/resumes')->group(function () {

        Route::get('/files', [ResumeController::class, 'getResumesFiles']);
        Route::post('/new-resume', [ResumeController::class, 'storeNewResume']);
        Route::get('/pendings', [ResumeController::class, 'resumeAnalytics']);
        Route::get('/pendings/{resume}', [ResumeController::class, 'showPendingResume']);

        Route::post('{resume}/finish', [ResumeController::class, 'finish']);

    });

    Route::prefix('/services')->group(function () {
        Route::prefix('/bot')->group(function () {
            Route::post('/process', [ProcessBotService::class, 'analyse']);
        });
    });

});
