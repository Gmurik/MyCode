<?php

namespace Lms\Application\Conventions\Webinars;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebinarApiClient
{
    private string $apiKey;
    private string $apiEndpoint;
    private const STATUS_OK = 200;

    public function __construct()
    {
        $this->apiKey = config('far.webinar.api.key');
        $this->apiEndpoint = config('far.webinar.api.url');
    }

    /**
     * @param int $eventSessionId
     * @return array
     * @throws \JsonException
     */
    public function getWebinarInformation(int $eventSessionId): array
    {
        $response = Http::withHeaders([
            'x-auth-token' => $this->apiKey,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->get($this->apiEndpoint . '/eventsessions/' . $eventSessionId);

        return json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param int $eventSessionId
     * @param array $userInfo
     * @return array
     * @throws \JsonException
     */
    public function register(int $eventSessionId, array $userInfo): array
    {
        $response = Http::withHeaders([
            'x-auth-token' => $this->apiKey,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($this->apiEndpoint . '/eventsessions/' . $eventSessionId . '/register', $userInfo);

        if ($response->failed()) {
            $errorMessage = 'Error while webinar participant registration: ' . 'eventSessionId= ' . $eventSessionId .
                ' user data= ' . Arr::get($userInfo, 'email', '');
            Log::error($errorMessage);
            throw new \Exception($errorMessage);
        }

        return json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function getWebinarVisitorsInfo(int $webinarPlatformId, string $webinarStartAt): array
    {
        $webinarVisitorInfo = [];
        $response = Http::withHeaders([
            'x-auth-token' => $this->apiKey,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->get($this->apiEndpoint . '/stats/users?from=' . $webinarStartAt . '&eventId=' . $webinarPlatformId);

        if ($response->status() === self::STATUS_OK) {
            $webinarVisitorInfo = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        }

        return $webinarVisitorInfo;
    }

    public function getWebinarTestResults(int $testId): array
    {
        $webinarTestResults = [];
        $response = Http::withHeaders([
            'x-auth-token' => $this->apiKey,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->get($this->apiEndpoint . '/tests/' . $testId . '/results');

        if ($response->status() === self::STATUS_OK) {
            $webinarTestResults = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        }

        return $webinarTestResults;
    }

    public function getWebinarTestId(int $eventSessionId): ?int
    {
        $webinarTestId = null;
        $webinarInfo = $this->getWebinarInformation($eventSessionId);
        if (isset($webinarInfo['files'])) {
            foreach ($webinarInfo['files'] as $file) {
                if ($file['fileType'] === 'test') {
                    $webinarTestId = $file['id'];
                }
            }
        }

        return $webinarTestId;
    }
}
