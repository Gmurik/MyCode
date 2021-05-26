<?php

declare(strict_types=1);

namespace Lms\Infrastructure\Conventions\Helpers;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lms\Domain\Conventions\ValueObjects\WebinarPlatformProperties;
use Lms\Infrastructure\Conventions\Db\Eloquent\Convention;

class ConventionHelper
{
    public const NMO_FLAG_FIELD_NAME = 'is_related_to_nmo';

    //отвечает за какое время до начала мероприятия начинаются действия(рассылки, ссылки и тд)
    public const WEBINAR_REGISTRATION_STATUS_KYE = 'webinar_registration_status';
    public const CONVENTION_PAYMENT_STATUS_KYE = 'convention_is_paid';
    public const STARTING_SOON_HOUR_VALUE = 1;
    public const FINISH_HOUR_VALUE = 5;
    public const WEBINAR_FINISH_STATUS = 'STOP';
    public const PERSONAL_WEBINAR_URL_KEY = 'personal_webinar_url';
    public const TYPE_FAR = 'far';
    public const TYPE_SCHOOL_FAR = 'school_far';
    public const TYPE_PARTNER = 'partner';
    public const TYPES = [
        self::TYPE_FAR,
        self::TYPE_SCHOOL_FAR,
        self::TYPE_PARTNER,
    ];

    public const ACCREDITATION_STATUS_SUBMITTED = 'submitted';
    public const ACCREDITATION_STATUS_APPROVED = 'approved';
    public const ACCREDITATION_STATUSES = [
        self::ACCREDITATION_STATUS_SUBMITTED,
        self::ACCREDITATION_STATUS_APPROVED,
    ];

    protected Convention $convention;

    public function __construct(Convention $convention)
    {
        $this->convention = $convention;
    }

    public function typeToRus(): string
    {
        $type = $this->convention->type ?
            Arr::get(trans('convention.types'), $this->convention->type, 'Нет данных') :
            'Нет данных';
        return $type;
    }

    public function accreditationStatusToRus(): ?string
    {
        if (is_null($this->convention->accreditation_status)) {
            return null;
        }

        return Arr::get(
            trans('convention.accreditation_statuses'),
            $this->convention->accreditation_status,
            'Нет данных'
        );
    }

    public function getWebinarProperties(): ?WebinarPlatformProperties
    {
        if ($webinarUrl = $this->convention->meta->getWebinarUrl()) {
            preg_match(
                '|(.*event\/)(\d*)(\/)(\d*)(\/edit)|',
                $webinarUrl,
                $webinarUrlParsedData
            );
            if (isset($webinarUrlParsedData[4]) && isset($webinarUrlParsedData[2])) {
                return new WebinarPlatformProperties((int)$webinarUrlParsedData[4], (int)$webinarUrlParsedData[2]);
            }
        }

        return null;
    }

    public function checkConventionIsActive(): bool
    {
        $isActive = false;
        $now = Carbon::now();
        $startAt = $this->convention->start_at->value();
        $finishAt = $this->convention->finish_at->value();
        $earlyStartAt = Carbon::parse($startAt)->subHours(self::STARTING_SOON_HOUR_VALUE);
        $lateFinishAt = Carbon::parse($finishAt)->addHours(self::FINISH_HOUR_VALUE);

        if ($now->between($earlyStartAt, $lateFinishAt)) {
            $isActive = true;
        }

        return $isActive;
    }
}
