<?php

declare(strict_types=1);

namespace Lms\Domain\Conventions\ValueObjects;

use Illuminate\Support\Arr;

final class ConventionMeta
{
    // uuid превью мероприятия
    private ?string $previewUuid;
    // анонс мероприятия
    private ?string $announcement;
    // программа мероприятия
    private ?string $program;
    // организаторы мероприятия
    private array $organizers;
    // временные партнеры мероприятия
    private array $tempPartners;
    //кол-о начисляемых балов
    private int $nmoCoins;
    //нформация об аккредитации
    private ?string $accreditationInfo;
    //ссылка на вебинар
    private ?string $webinarUrl;
    //сслка на запись вебинара
    private ?string $webinarVideoUrl;

    public function __construct(array $attributes)
    {
        $this->previewUuid = Arr::get($attributes, 'preview_uuid');
        $this->announcement = Arr::get($attributes, 'announcement');
        $this->program = Arr::get($attributes, 'program');
        $this->organizers = (array)Arr::get($attributes, 'organizers');
        $this->tempPartners = (array)Arr::get($attributes, 'temp_partners');
        $this->webinarUrl = Arr::get($attributes, 'webinar_url');
        $this->accreditationInfo = Arr::get($attributes, 'accreditation_info');
        $this->nmoCoins = (int)Arr::get($attributes, 'nmo_coins');
        $this->webinarVideoUrl = Arr::get($attributes, 'webinar_video_url');
    }

    public function getPreviewUuid(): ?string
    {
        return $this->previewUuid;
    }

    public function getAnnouncement(): ?string
    {
        return $this->announcement;
    }

    public function getProgram(): ?string
    {
        return $this->program;
    }

    public function getOrganizers(): array
    {
        return $this->organizers;
    }

    public function getTempPartners(): array
    {
        return $this->tempPartners;
    }

    public function getAccreditationInfo(): ?string
    {
        return $this->accreditationInfo;
    }

    public function getNmoCoins(): int
    {
        return $this->nmoCoins;
    }

    public function getWebinarUrl(): ?string
    {
        return $this->webinarUrl;
    }

    public function getWebinarVideoUrl(): ?string
    {
        return $this->webinarVideoUrl;
    }

    public function toArray(): array
    {
        return [
            'preview_uuid' => $this->previewUuid,
            'announcement' => $this->announcement,
            'program' => $this->program,
            'organizers' => $this->organizers,
            'temp_partners' => $this->tempPartners,
            'accreditation_info' => $this->accreditationInfo,
            'nmo_coins' => $this->nmoCoins,
            'webinar_url' => $this->webinarUrl,
            'webinar_video_url' => $this->webinarVideoUrl
        ];
    }
}
