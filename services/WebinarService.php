<?php

declare(strict_types=1);

namespace Lms\Application\Conventions\Webinars;

use App\Lms\Infrastructure\Users\Repositories\FarUserRepository;
use Lms\Application\Share\RegionService;
use Lms\Application\Share\SpecialityService;
use Lms\Application\Tasks\TaskFileService;
use Lms\Infrastructure\Conventions\Db\Eloquent\Convention;
use Lms\Infrastructure\Conventions\Helpers\ConventionHelper;
use Lms\Infrastructure\Conventions\Repositories\WebinarRepository;
use Lms\Infrastructure\Users\Db\Eloquent\User;

class WebinarService
{
    protected WebinarRepository $webinarRepository;
    protected FarUserRepository $userRepository;
    protected WebinarApiClient $webinarApiClient;
    protected TaskFileService $taskFileService;
    protected WebinarExportService $webinarExportService;
    protected SpecialityService $specialityService;
    protected RegionService $regionService;

    public function __construct(
        WebinarRepository $webinarRepository,
        FarUserRepository $userRepository,
        WebinarApiClient $webinarApiClient,
        TaskFileService $taskFileService,
        WebinarExportService $webinarExportService,
        SpecialityService $specialityService,
        RegionService $regionService
    ) {
        $this->webinarRepository = $webinarRepository;
        $this->userRepository = $userRepository;
        $this->webinarApiClient = $webinarApiClient;
        $this->taskFileService = $taskFileService;
        $this->webinarExportService = $webinarExportService;
        $this->specialityService = $specialityService;
        $this->regionService = $regionService;
    }

    /**
     * список пользователей с результатами прохождения вебинара
     * @param int $webinarId
     * @return array
     * @throws \LMSCore\Infrastructure\Exceptions\NotFoundException
     */
    public function webinarVisitorsList(int $webinarId): array
    {
        $statistic = [];
        $webinarVisitors = $this->webinarRepository->getWebinarVisitors($webinarId);
        /** @var User $visitor */
        foreach ($webinarVisitors as $visitor) {
            $visitorDomainModel = $this->userRepository->find($visitor->getkey());
            $userAttributes = $visitorDomainModel->getResponseAttributes();
            $statistic[] = [
                'email' => $visitor->email,
                'duration' => $visitor->pivot->duration,
                'confirm_count' => $visitor->pivot->confirm_count,
                'control_count' => $visitor->pivot->control_count,
                'correctly_test_answers' => $visitor->pivot->correctly_test_answers,
                'test_is_passed' => $visitor->pivot->test_is_passed,
                'fullname' => $visitor->getFullName(),
                'work_place' => $userAttributes['work_place'] ?? '',
                'speciality' => $this->specialityService->getSpecialityNameById($userAttributes['speciality_id'] ?? 0),
                'region' => $this->regionService->getRegionNameByCountryAndCode(
                    $userAttributes['country_code'] ?? '',
                    $userAttributes['region_id'] ?? 0,
                )
            ];
        }

        return $statistic;
    }

    /**
     * запросс данных с webinar.ru
     * @param Convention $webinar
     * @return array
     * @throws \JsonException
     */
    public function getWebinarStatistic(Convention $webinar): array
    {
        if ($webinarProperties = $webinar->getHelper()->getWebinarProperties()) {
            return $this->webinarApiClient->getWebinarVisitorsInfo(
                $webinarProperties->getWebinarId(),
                $webinar->start_at->toDateString()
            );
        }
        return [];
    }

    /**
     * получение вебинаров проходящих в указанном временном периоде
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function webinarsByDate(string $startDate, string $endDate)
    {
        return $this->webinarRepository->getWebinarBetweenDates($startDate, $endDate);
    }

    /**
     * экспотр отчета о прохождении вебина в Excel
     * @param int $webinarId
     * @throws \LMSCore\Infrastructure\Exceptions\NotFoundException
     */
    public function webinarVisitorsStatisticExport(int $webinarId): void
    {
        $webinarVisitorsExportData = $this->webinarVisitorsList($webinarId);
        $webinar = $this->webinarRepository->getById($webinarId);

        $this->webinarExportService->createUsersExportTask(
            getAuthUser(),
            $this->webinarExportService->generateExportFileName($webinar),
            $webinarVisitorsExportData
        );
    }

    /**
     * проверяет зарегистрированн ли пользователь на вебинар
     * @param Convention $webinar
     * @param User $user
     * @return bool
     */
    public function checkIsParticipantForWebinar(Convention $webinar, User $user): bool
    {
        $result = false;
        $participant = $webinar->participants()->where('participant_id', $user->getKey())->first();
        if ($participant) {
            $result = true;
        }

        return $result;
    }

    /**
     * собирает данные по прохождению вебинара
     * @param Convention $webinar
     * @return int
     * @throws \JsonException
     */
    public function fillVisitorsStatistic(Convention $webinar): int
    {
        $visitorCount = 0;
        if (!$webinarProperties = $webinar->getHelper()->getWebinarProperties()) {
            return $visitorCount;
        }
        $webinarId = $webinarProperties->getWebinarId();
        $webinarVisitorsStatistic = $this->getWebinarStatistic($webinar);
        $webinarTestResults = $this->getWebinarTestResults(
            $this->webinarApiClient->getWebinarTestId($webinarProperties->getSessionId())
        );

        /** @var array $visitorInfo */
        foreach ($webinarVisitorsStatistic as $visitorInfo) {
            //только для зарегистрированных пользователей с указанной почтой
            if (isset($visitorInfo['email'])) {
                //берем данные только по интересующему нас событию
                $currentEventStatistic = $this->getCurrentEventStatistic($webinarId, $visitorInfo);
                $visitor = $this->userRepository->getUserByEmail($visitorInfo['email']);
                if ($visitor && $this->checkIsParticipantForWebinar($webinar, $visitor)) {
                    $webinar->webinarVisitors()->syncWithoutDetaching(
                        [$visitor->getkey() => [
                            'confirm_count' => $this->getConfirmedCount($currentEventStatistic),
                            'control_count' => $this->getShownCount($currentEventStatistic),
                            'duration' => $this->calculateDuration($currentEventStatistic),
                            'test_is_passed' => $webinarTestResults[$visitor->email]['test_is_passed'] ?? null,
                            'correctly_test_answers' =>
                                $webinarTestResults[$visitor->email]['correctly_test_answers'] ?? null,
                        ]]
                    );
                    $visitorCount++;
                }
            }
        }

        return $visitorCount;
    }

    /**
     * ручной вызов перерасчета данных о вебинаре
     * @param int $webinarId
     * @throws \JsonException
     * @throws \LMSCore\Infrastructure\Exceptions\NotFoundException
     */
    public function recalculateVisitorsStatistic(int $webinarId): void
    {
        $webinar = $this->webinarRepository->getById($webinarId);
        $this->fillVisitorsStatistic($webinar);
    }

    /**
     * получение персональной ссылки на вебинар
     * @param Convention $webinar
     * @param User $user
     * @return string|null
     */
    public function getPersonalWebinarUrl(Convention $webinar, User $user): ?string
    {
        $url = null;
        if ($this->checkIsParticipantForWebinar($webinar, $user)) {
            if ($participant = $webinar->participants()->where('participant_id', $user->getKey())->first()) {
                $url = $participant->pivot->personal_webinar_url;
            }
        }

        return $url;
    }

    /**
     * расчитывает время присутствия на вебинаре в минутах
     * @param array $visitorStatistic
     * @return float
     */
    protected function calculateDuration(array $visitorStatistic): float
    {
        $duration = 0;
        if (isset($visitorStatistic['connections'])) {
            foreach ($visitorStatistic['connections'] as $connection) {
                $duration += (int)$connection['duration'];
            }
        }

        return round($duration / 60);
    }

    public function getWebinarTestResults(?int $webinarTestId): array
    {
        $results = [];
        if (!$webinarTestId) {
            return $results;
        }
        $webinarTestData = $this->webinarApiClient->getWebinarTestResults($webinarTestId);
        if (isset($webinarTestData[0]['users'])) {
            foreach ($webinarTestData[0]['users'] as $userTestResult) {
                if (isset($userTestResult['email'])) {
                    $results[$userTestResult['email']] = [
                        //кол-во правильных ответов на тест
                        'correctly_test_answers' => $userTestResult['correctlyAnsweredQuestions'],
                        //прошел ли человек тест
                        'test_is_passed' => $userTestResult['isPassed']
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * кол-во ответов на контроль присутствия
     * @param array $visitorStatistic
     * @return int
     */
    protected function getConfirmedCount(array $visitorStatistic): int
    {
        return isset($visitorStatistic['attentionControl']['confirmedCount']) ?
            $visitorStatistic['attentionControl']['confirmedCount'] : 0;
    }

    /**
     * кол-во контролей присутствия
     * @param array $visitorStatistic
     * @return int
     */
    protected function getShownCount(array $visitorStatistic): int
    {
        return isset($visitorStatistic['attentionControl']['shownCount']) ?
            $visitorStatistic['attentionControl']['shownCount'] : 0;
    }

    /**
     * берет статистику только по запрошенному вебинару
     * @param int $webinarPlatformEventId
     * @param array $visitorStatistic
     * @return array
     */
    protected function getCurrentEventStatistic(int $webinarPlatformEventId, array $visitorStatistic): array
    {
        $eventCurrentEventStatistic = [];
        foreach ($visitorStatistic['eventSessions'] as $eventSession) {
            if ($eventSession['eventId'] == $webinarPlatformEventId) {
                $eventCurrentEventStatistic = $eventSession;
            }
        }

        return $eventCurrentEventStatistic;
    }

    public function isFinish(Convention $webinar): bool
    {
        if ($webinarProperties = $webinar->getHelper()->getWebinarProperties()) {
            $webinarInfos = $this->webinarApiClient->getWebinarInformation($webinarProperties->getSessionId());
            if (isset($webinarInfos['status'])) {
                if ($webinarInfos['status'] === ConventionHelper::WEBINAR_FINISH_STATUS) {
                    return true;
                }
            }
        }
        return false;
    }

    public function webinarStatus(int $webinarId): ?array
    {
        $webinar = $this->webinarRepository->getById($webinarId);

        if ($webinarProperties = $webinar->getHelper()->getWebinarProperties()) {
            $webinarInfos = $this->webinarApiClient->getWebinarInformation($webinarProperties->getSessionId());
            if (!empty($webinarInfos)) {
                return $webinarInfos;
            }
        }
        return null;
    }

    public function webinarRegistrationFailed(Convention $convention, User $user): bool
    {
        if ($participant = $convention->participants()->where('participant_id', $user->getkey())->first()) {
            if (!$participant->pivot->personal_webinar_url) {
                return true;
            }
        }

        return false;
    }
}
