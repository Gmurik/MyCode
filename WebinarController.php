<?php

namespace Lms\UI\Rest\Conventions\Http\Controllers;

use Cms\Responses\Share\DataJsonResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Lms\Application\Conventions\Webinars\WebinarRegistrationService;
use Lms\Application\Conventions\Webinars\WebinarService;
use Lms\Infrastructure\Conventions\Db\Eloquent\Convention;
use Lms\UI\Rest\Conventions\Http\Requests\WebinarRegisterUserRequest;

class WebinarController extends Controller
{
    protected WebinarService $service;
    protected WebinarRegistrationService $webinarRegistrationService;

    public function __construct(
        WebinarService $service,
        WebinarRegistrationService $webinarRegistrationService
    ) {
        $this->service = $service;
        $this->webinarRegistrationService = $webinarRegistrationService;
    }

    public function webinarIndex(): JsonResponse
    {
        $webinarId = request()->route('webinar_id');

        return response()->json(['data' => $this->service->webinarVisitorsList((int)$webinarId)], 200);
    }

    public function exportVisitors(): JsonResponse
    {
        $webinarId = request()->route('webinar_id');
        $this->service->webinarVisitorsStatisticExport((int)$webinarId);

        return DataJsonResponse::makeOkResponse('The webinar visitors list is being created');
    }

    public function recalculateWebinarStatistic(): JsonResponse
    {
        $webinarId = request()->route('webinar_id');
        $this->service->recalculateVisitorsStatistic((int)$webinarId);

        return DataJsonResponse::makeOkResponse('The webinar visitors statistic recalculated');
    }

    public function registerUser(WebinarRegisterUserRequest $request): JsonResponse
    {
        $webinarId = request()->route('webinar_id');
        $this->webinarRegistrationService->createWebinarRegistrationTask($request->getUserId(), (int)$webinarId);

        return DataJsonResponse::makeOkResponse('Create registration task');
    }

    public function webinarStatus(): JsonResponse
    {
        $webinarId = request()->route('webinar_id');

        return response()->json(['data' => $this->service->webinarStatus((int)$webinarId)], 200);
    }
}
