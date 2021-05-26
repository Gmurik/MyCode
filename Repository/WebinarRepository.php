<?php

declare(strict_types=1);

namespace Lms\Infrastructure\Conventions\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Lms\Infrastructure\Conventions\Db\Eloquent\Convention;
use LMSCore\Infrastructure\Exceptions\NotFoundException;

class WebinarRepository extends ConventionRepository
{
    /**
     * @param int $webinarId
     * @return Collection
     * @throws NotFoundException
     */
    public function getWebinarVisitors(int $webinarId): Collection
    {
        $webinar = $this->getById($webinarId);

        return $webinar->webinarVisitors;
    }

    public function getWebinarBetweenDates(string $firstDate, string $secondDate): Collection
    {
        return Convention::where('is_online', 1)->whereBetween('start_at', [$firstDate, $secondDate])->get();
    }
}
