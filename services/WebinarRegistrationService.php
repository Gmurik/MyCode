<?php

declare(strict_types=1);

namespace Lms\Application\Conventions\Webinars;

use App\Cms\Jobs\WebinarStartNotification;
use Illuminate\Support\Facades\Log;
use Lms\Domain\Tasks\Repositories\TaskRepositoryInterface;
use Lms\Domain\Tasks\ValueObjects\TaskUuid;
use Lms\Infrastructure\Conventions\Db\Eloquent\Convention;
use Lms\Infrastructure\Conventions\Helpers\ConventionHelper;
use Lms\Infrastructure\Conventions\Repositories\WebinarRepository;
use Lms\Infrastructure\Tasks\Events\TaskStatusQueuedEvent;
use Lms\Infrastructure\Tasks\Factories\TaskFactory;
use Lms\Infrastructure\Tasks\Jobs\PerformTaskJob;
use Lms\Infrastructure\Tasks\Repositories\TaskRepository;
use Lms\Infrastructure\Users\Db\Eloquent\User;

class WebinarRegistrationService
{
    protected TaskFactory $taskFactory;
    protected TaskRepositoryInterface $taskRepo;
    protected WebinarRepository $webinarRepository;
    protected WebinarApiClient $webinarApiClient;
    protected TaskRepository $taskRepository;

    public function __construct(
        TaskFactory $taskFactory,
        TaskRepositoryInterface $taskRepo,
        WebinarApiClient $webinarApiClient,
        TaskRepository $taskRepository
    ) {
        $this->taskFactory = $taskFactory;
        $this->taskRepo = $taskRepo;
        $this->webinarApiClient = $webinarApiClient;
        $this->taskRepository = $taskRepository;
    }

    public function createWebinarRegistrationTask(int $userId, int $webinarId): void
    {
        $task = $this->taskFactory->makeNewUserWebinarRegistrationTask($userId, $webinarId);
        $task = $this->taskRepo->create($task);

        event(new TaskStatusQueuedEvent($task));

        PerformTaskJob::dispatch($task->getTaskDescription()->getTaskId());
    }

    /**
     * @param User $user
     * @param Convention $webinar
     * @throws \JsonException
     */
    public function registerUser(User $user, Convention $webinar): void
    {
        $webinarProperties = $webinar->getHelper()->getWebinarProperties();
        if ($webinarProperties) {
            $registration = $this->webinarApiClient->register(
                $webinarProperties->getSessionId(),
                ['email' => $user->email, 'sendEmail' => 'false']
            );
            if ($registration) {
                //добавляем персональную ссылку пользователю
                $webinar->participants()->updateExistingPivot($user->getKey(), [
                    'personal_webinar_url' => $registration['link'],
                ]);
                //рассылка писем перед началом вебинара
                WebinarStartNotification::dispatch($user, $webinar)
                    ->delay($webinar->start_at->value()->subHours(ConventionHelper::STARTING_SOON_HOUR_VALUE));
                WebinarStartNotification::dispatch($user, $webinar, 24)
                    ->delay($webinar->start_at->value()->subHours(24));
            }
        }
    }

    public function getWebinarRegistrationStatus(User $user, Convention $webinar): ?string
    {
        if ($participant = $webinar->participants()->where('participant_id', $user->getkey())->first()) {
            if ($taskUuid = $participant->pivot->webinar_registration_task_uuid) {
                if ($registrationTask = $this->taskRepository->getByUuid(new TaskUuid($taskUuid))) {
                    return $registrationTask->getStatusName();
                }
            }
        }

        return null;
    }
}
