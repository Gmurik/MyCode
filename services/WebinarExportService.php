<?php

declare(strict_types=1);

namespace Lms\Application\Conventions\Webinars;

use Lms\Domain\Tasks\Repositories\TaskRepositoryInterface;
use Lms\Domain\Users\Models\User;
use Lms\Infrastructure\Conventions\Db\Eloquent\Convention;
use Lms\Infrastructure\Tasks\Events\TaskStatusQueuedEvent;
use Lms\Infrastructure\Tasks\Factories\TaskFactory;
use Lms\Infrastructure\Tasks\Jobs\PerformTaskJob;

class WebinarExportService
{
    protected TaskFactory $taskFactory;
    protected TaskRepositoryInterface $taskRepo;

    public function __construct(
        TaskFactory $taskFactory,
        TaskRepositoryInterface $taskRepo
    ) {
        $this->taskFactory = $taskFactory;
        $this->taskRepo = $taskRepo;
    }

    public function createUsersExportTask(User $user, string $exportFileName, array $webinarExportProperties): void
    {
        $task = $this->taskFactory->makeNewExportWebinarVisitorsTask($user, $exportFileName, $webinarExportProperties);
        $task = $this->taskRepo->create($task);

        event(new TaskStatusQueuedEvent($task));

        PerformTaskJob::dispatch($task->getTaskDescription()->getTaskId());
    }

    public function generateExportFileName(Convention $webinar): string
    {
        return 'webinar_' . $webinar->convention_id . '_' . $webinar->start_at->toDateString();
    }
}
