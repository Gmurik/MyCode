<?php

declare(strict_types=1);

namespace App\Lms\UI\Rest\Conventions\Routes;

use Cms\Controllers\Lecturers\LecturersController;
use Cms\Controllers\NMOCodes\NMOCodesController;
use Illuminate\Support\Facades\Route;
use Lms\UI\Rest\Conventions\Http\Controllers\ConventionController;
use Lms\UI\Rest\Conventions\Http\Controllers\WebinarController;

class WebinarRoutes
{
    protected static string $resourcePrefix = '/webinar';
    protected static string $resourceName = 'webinar';

    public static function initRoutes(): void
    {
        Route::get(
            self::$resourcePrefix . '/{webinar_id}/statistic',
            [WebinarController::class, 'webinarIndex']
        )->name(self::$resourceName . '.' .  'info');
        Route::get(
            self::$resourcePrefix . '/{webinar_id}/statistic-export',
            [WebinarController::class, 'exportVisitors']
        )->name(self::$resourceName . '.' .  'export');
        Route::post(
            self::$resourcePrefix . '/{webinar_id}/statistic-recalculate',
            [WebinarController::class, 'recalculateWebinarStatistic']
        )->name(self::$resourceName . '.' .  'statistic.recalculate');
        Route::post(
            self::$resourcePrefix . '/{webinar_id}/register',
            [WebinarController::class, 'registerUser']
        )->name(self::$resourceName . '.' .  'register');
        Route::get(
            self::$resourcePrefix . '/{webinar_id}/webinar-status',
            [WebinarController::class, 'webinarStatus']
        )->name(self::$resourceName . '.' .  'status');
    }
}
