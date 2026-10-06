<?php

namespace Domains\User\Infrastructure\Elasticsearch;

use Domains\User\Domain\Events\UserCreated;
use Domains\User\Domain\Events\UserDeleted;
use Domains\User\Domain\Events\UserUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserElasticsearchSyncListener implements ShouldQueue
{
    public string $queue = 'imports';

    public function __construct(private readonly UserElasticsearchIndexer $indexer) {}

    public function handleCreated(UserCreated $event): void
    {
        $this->indexer->indexByIds([$event->record->id]);
    }

    public function handleUpdated(UserUpdated $event): void
    {
        $this->indexer->indexByIds([$event->record->id]);
    }

    public function handleDeleted(UserDeleted $event): void
    {
        $this->indexer->deleteFromIndex($event->id);
    }
}
