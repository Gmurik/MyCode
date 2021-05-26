<?php

namespace Lms\Infrastructure\Conventions\Db\Eloquent;

use Cms\Casts\Share\MoneyCast;
use Cms\Models\Lecturer;
use Cms\Models\Tag;
use EduShare\Domain\ValueObjects\CreatedAt as CreatedAtObject;
use EduShare\Domain\ValueObjects\DeletedAt as DeletedAtObject;
use EduShare\Domain\ValueObjects\UpdatedAt as UpdatedAtObject;
use EduShare\Infrastructure\Db\Casts\CreatedAt as CreatedAtCast;
use EduShare\Infrastructure\Db\Casts\DeletedAt as DeletedAtCast;
use EduShare\Infrastructure\Db\Casts\UpdatedAt as UpdatedAtCast;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lms\Domain\Conventions\ValueObjects\ConventionMeta;
use Cms\Models\Partners\Partner;
use Lms\Infrastructure\Conventions\Db\Eloquent\Casts\ConventionMetaCast;
use Lms\Infrastructure\Conventions\Db\Eloquent\Casts\StartAt as StartAtCast;
use Lms\Infrastructure\Conventions\Db\Eloquent\Casts\FinishAt as FinishAtCast;
use Lms\Infrastructure\Conventions\Helpers\ConventionHelper;
use Lms\Infrastructure\Users\Db\Eloquent\User;
use LMSCore\Assignments\ValueObjects\FinishAt as FinishAtValueObject;
use LMSCore\Assignments\ValueObjects\StartAt as StartAtValueObject;

/**
 * @property int $convention_id - идентификатор мероприятия
 * @property string $name - название мероприятия
 * @property string $type - тип мероприятия
 * @property string|null $location - место проведения мероприятия (оффлайн - адрес, онлайн - название площадки)
 * @property StartAtValueObject $start_at - дата, время начала мероприятия
 * @property FinishAtValueObject $finish_at - дата, время окончания мероприятия
 * @property bool $is_online - мероприятие онлайн или оффлайн
 * @property bool $is_paid - мероприятие платное или бесплатное
 * @property float $price - цена мероприятия
 * @property string|null $accreditation_status - статус аккредитации мероприятия (null при отсутствии аккредитации)
 * @property ConventionMeta $meta - мета поле
 * @property mixed $pivot - поля смежных таблиц
 *
 * @property CreatedAtObject|null $created_at - дата, время создания
 * @property UpdatedAtObject|null $updated_at - дата, время обновления
 * @property DeletedAtObject|null $deleted_at - дата, время удаления
 *
 * @property Collection $lecturers - лекторы, выступающие на мероприятии из смежной таблицы
 * @property Collection $partners - партнеры мероприятия из смежной таблицы
 * @property Collection $participants - только активные участники мероприятия из смежной таблицы
 * @property Collection $participantsWithNotActive - участники всемет с отменными регстрациями мероприятия
 * @property Collection $assignedParticipants - пользователе у которых текущее мероприятия в избранных
 * @property Collection $webinarVisitors - инфо по пользователям прошедшим вебинар
 */
class Convention extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const PAID_CONVENTION = 'paid';
    public const FREE_CONVENTION = 'free';

    public const DISTRIBUTION_SYSTEMS = [
        self::FREE_CONVENTION,
        self::PAID_CONVENTION,
    ];

    protected $table = 'conventions';

    protected $primaryKey = 'convention_id';

    protected $casts = [
        'start_at' => StartAtCast::class,
        'finish_at' => FinishAtCast::class,
        'is_online' => 'bool',
        'is_paid' => 'bool',
        'price' => MoneyCast::class,
        'meta' => ConventionMetaCast::class,
        'created_at' => CreatedAtCast::class,
        'updated_at' => UpdatedAtCast::class,
        'deleted_at' => DeletedAtCast::class,
    ];

    protected $fillable = [
        'name',
        'type',
        'location',
        'start_at',
        'finish_at',
        'is_online',
        'is_paid',
        'price',
        'accreditation_status',
        'meta',
    ];

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(
            Partner::class,
            'convention_partner',
            'convention_id',
            'partner_id',
            'convention_id',
            'partner_id',
        );
    }

    public function lecturers(): BelongsToMany
    {
        return $this->belongsToMany(
            Lecturer::class,
            'convention_lecturer',
            'convention_id',
            'lecturer_id',
            'convention_id',
            'lecturer_id',
        );
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'convention_participant',
            'convention_id',
            'participant_id',
            'convention_id',
            'user_id',
        )->wherePivot('enable', '=', 1)
            ->withPivot(['invoice_id', 'personal_webinar_url', 'webinar_registration_task_uuid', 'enable'])
            ->withTimestamps();
    }

    public function participantsWithNotActive(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'convention_participant',
            'convention_id',
            'participant_id',
            'convention_id',
            'user_id',
        )->withPivot(['invoice_id', 'personal_webinar_url', 'webinar_registration_task_uuid', 'enable'])
            ->withTimestamps();
    }

    public function assignedParticipants(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'favorite_convention_participant',
            'convention_id',
            'participant_id',
            'convention_id',
            'user_id',
        )->withTimestamps();
    }

    public function webinarVisitors(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'webinar_visitor',
            'webinar_id',
            'visitor_id',
            'convention_id',
            'user_id',
        )->withPivot([
            'control_count',
            'confirm_count',
            'duration',
            'correctly_test_answers',
            'test_is_passed',
        ])->withTimestamps()->orderByPivot('confirm_count');
    }

    public function getHelper(): ConventionHelper
    {
        return new ConventionHelper($this);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(
            Tag::class,
            'entity_id',
            'convention_id',
        );
    }
}
