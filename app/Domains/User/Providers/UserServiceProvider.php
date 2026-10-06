<?php

namespace Domains\User\Providers;

use Domains\User\Application\Commands\Create\CreateUserHandler;
use Domains\User\Application\Commands\Delete\DeleteUserHandler;
use Domains\User\Application\Commands\Logout\LogoutHandler;
use Domains\User\Application\Commands\Register\RegisterHandler;
use Domains\User\Application\Commands\Update\UpdateUserHandler;
use Domains\User\Application\Queries\FindByCredentials\FindUserByCredentialsHandler;
use Domains\User\Application\Queries\FindById\FindUserByIdHandler;
use Domains\User\Application\Queries\ListAll\ListUsersHandler;
use Domains\User\Application\Repositories\UserRepositoryInterface;
use Domains\User\Domain\Events\UserCreated;
use Domains\User\Domain\Events\UserDeleted;
use Domains\User\Domain\Events\UserUpdated;
use Domains\User\Infrastructure\Elasticsearch\UserElasticsearchIndexer;
use Domains\User\Infrastructure\Elasticsearch\UserElasticsearchSyncListener;
use Domains\User\Infrastructure\Repositories\UserRepository;
use Domains\User\Presentation\Http\Middleware\EnsureUserIsVerified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CreateUserHandler::class);
        $this->app->bind(UpdateUserHandler::class);
        $this->app->bind(DeleteUserHandler::class);
        $this->app->bind(ListUsersHandler::class);
        $this->app->bind(FindUserByIdHandler::class);
        $this->app->bind(FindUserByCredentialsHandler::class);
        $this->app->bind(LogoutHandler::class);
        $this->app->bind(RegisterHandler::class);

        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);

        $this->app->bind(UserElasticsearchIndexer::class);
        $this->app->tag([UserElasticsearchIndexer::class], ['es-indexers']);

        Route::aliasMiddleware('user.verified', EnsureUserIsVerified::class);
    }

    public function boot(): void
    {
        Event::listen(UserCreated::class, [UserElasticsearchSyncListener::class, 'handleCreated']);
        Event::listen(UserUpdated::class, [UserElasticsearchSyncListener::class, 'handleUpdated']);
        Event::listen(UserDeleted::class, [UserElasticsearchSyncListener::class, 'handleDeleted']);
    }
}
