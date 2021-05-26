<?php

declare(strict_types=1);

namespace Lms\Infrastructure\Providers;

use App\Lms\Infrastructure\Programs\Factories\ProgramContentFactory;
use App\Lms\Infrastructure\Users\Repositories\FarUserRepository;
use Cms\Models\NMOCode;
use Cms\Repositories\NMOCodes\NMOCodesRepository;
use Edu\Users\Factories\UserFactory;
use Edu\Auth\Controllers\AuthController;
use Edu\Users\DbLayer\Eloquent\CoreUser as EduCoreUser;
use Edu\Users\Services\UsersService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Lms\Application\Conventions\Webinars\WebinarApiClient;
use Lms\Application\Conventions\Webinars\WebinarNotificator;
use Lms\Domain\Assignments\Repositories\AssignmentPackRepositoryInterface;
use Lms\Domain\Content\Player\CustomTinCanPlayer;
use Lms\Domain\Programs\Repositories\ProgramRepositoryInterface;
use Lms\Domain\Programs\Repositories\ProgramStructureRepositoryInterface;
use Lms\Domain\Tasks\Repositories\TaskRepositoryInterface;
use Lms\Infrastructure\Assignments\Repositories\AssignmentPackRepository;
use Lms\Infrastructure\Programs\Db\Eloquent\Program;
use Lms\Infrastructure\Programs\Repositories\ProgramRepository;
use Lms\Infrastructure\Programs\Repositories\ProgramStructureRepository;
use Lms\Infrastructure\Services\EduUsersService;
use Lms\Infrastructure\Tasks\Factories\TaskActionFactory;
use Lms\Infrastructure\Tasks\Factories\TaskFactory;
use Lms\Infrastructure\Tasks\Repositories\TaskRepository;
use Lms\Infrastructure\Users\Db\Eloquent\CoreUser;
use Lms\UI\Rest\Auth\Http\Controllers\EduAuthController;
use LMSCore\Application\Assignments\UserAssignmentsSpecificationFactoryInterface;
use Lms\Infrastructure\Assignments\Db\Eloquent\UserAssignmentsSpecificationFactory;
use LMSCore\Content\Player\TinCan;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            UserAssignmentsSpecificationFactoryInterface::class,
            function () {
                return new UserAssignmentsSpecificationFactory(
                    lmsUserAssignmentsQuery('filter'),
                    lmsUserAssignmentsQuery('order')
                );
            }
        );

        $this->app->bind(ProgramRepositoryInterface::class, function () {
            return new ProgramRepository(new Program());
        });

        $this->app->bind(NMOCodesRepository::class, function () {
            return new NMOCodesRepository(new NMOCode());
        });

        $this->app->bind(ProgramStructureRepositoryInterface::class, function () {
            return new ProgramStructureRepository();
        });

        $this->app->bind(TaskRepositoryInterface::class, function () {
            return new TaskRepository(new TaskFactory(new TaskActionFactory()));
        });

        $this->app->bind(ProgramContentFactory::class, function () {
            return new ProgramContentFactory(env('APP_URL') ?? 'localhost');
        });

        $this->app->bind(AssignmentPackRepositoryInterface::class, function () {
            return new AssignmentPackRepository();
        });

        $this->app->bind(AuthController::class, function () {
            return resolve(EduAuthController::class);
        });

        $this->app->bind(UsersService::class, function () {
            return resolve(EduUsersService::class);
        });

        $this->app->bind(WebinarApiClient::class, function () {
            return new WebinarApiClient();
        });

        $this->app->bind(FarUserRepository::class, function () {
            return new FarUserRepository(
                $this->app->make(UserFactory::class),
                config('user.eloquent')
            );
        });

        $this->app->bind(EduCoreUser::class, function () {
            return resolve(CoreUser::class);
        });

        $this->app->bind(TinCan::class, function () {
            return resolve(CustomTinCanPlayer::class);
        });
    }

    public function boot(): void
    {
        if (config('app.secure')) {
            URL::forceScheme('https');
        }
    }
}
