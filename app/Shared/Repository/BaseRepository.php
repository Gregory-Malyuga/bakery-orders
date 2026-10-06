<?php

namespace Shared\Repository;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Shared\Data\PaginationData;
use Shared\Data\SortData;
use Shared\FilterBuilder\BaseFilterBuilder;
use Shared\Filters\FilterInterface;
use Shared\Filters\SearchFilter;
use Shared\QueryBuilder\BaseQueryBuilder;
use Shared\QueryBuilder\BaseQueryBuilderPostprocessor;
use Spatie\LaravelData\Data;
use TypeError;

abstract class BaseRepository
{
    // ── Must be set in the domain ──────────────────────────────────────────────
    /** @var class-string<Model> */
    protected string $model;

    // ── Replaceable only by subclasses (checked in bootOnce()) ────────────────
    /** @var class-string */
    protected string $queryBuilder = BaseQueryBuilder::class;

    /** @var class-string */
    protected string $filterBuilder = BaseFilterBuilder::class;

    /** @var class-string */
    protected string $collectionProcessor = BaseQueryBuilderPostprocessor::class;

    // ── Query configuration (set in domain) ───────────────────────────────────
    /** @var list<string> */
    protected array $defaultSelect = ['*'];

    /** @var array<string> */
    protected array $defaultWith = [];

    // ── Custom filter map (set in domain) ─────────────────────────────────────
    /** @var array<string, class-string<FilterInterface>> */
    protected array $filterMap = [];

    // ── Not-found exception (set in domain) ───────────────────────────────────
    /** @var class-string<\Throwable> */
    protected string $notFoundException = ModelNotFoundException::class;

    private bool $booted = false;

    // ── Public API ─────────────────────────────────────────────────────────────

    public function find(int|string $id): ?Model
    {
        return $this->model::with($this->defaultWith)->find($id);
    }

    public function findOrFail(int|string $id): Model
    {
        $model = $this->find($id);
        if ($model === null) {
            $this->throwNotFound($id);
        }

        return $model;
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    public function list(
        Data $filters,
        ?SortData $sort = null,
        ?PaginationData $pagination = null,
    ): LengthAwarePaginator {
        $this->bootOnce();

        $sort = $sort ?? new SortData;
        $pagination = $pagination ?? new PaginationData;

        /** @var BaseQueryBuilder<Model> $qb */
        $qb = new $this->queryBuilder($this->model);
        $qb->select($this->defaultSelect)->with($this->defaultWith);

        $qb = $this->applyBaseFilters($qb);
        $qb = $this->postProcessBuilder($qb, $filters);

        /** @var BaseFilterBuilder $fb */
        $fb = new $this->filterBuilder;
        $builder = $fb->apply($qb->getQuery(), $filters, $this->getEffectiveFilterMap());

        /** @var BaseQueryBuilderPostprocessor $cp */
        $cp = new $this->collectionProcessor;

        return $cp->process($builder, $sort, $pagination);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->model::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model $model, array $data): Model
    {
        $model->update($data);

        return $model->fresh() ?? $model;
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }

    /**
     * @return BaseQueryBuilder<Model>
     */
    public function newQuery(): BaseQueryBuilder
    {
        $this->bootOnce();

        /** @var BaseQueryBuilder<Model> $qb */
        $qb = new $this->queryBuilder($this->model);

        return $qb->select($this->defaultSelect)->with($this->defaultWith);
    }

    // ── Filter map ────────────────────────────────────────────────────────────

    /**
     * Merges the default search filter with domain-specific filterMap.
     * Domain repos override 'search' by declaring it in $filterMap (e.g. NoOpFilter for ES repos).
     *
     * @return array<string, class-string<FilterInterface>>
     */
    protected function getEffectiveFilterMap(): array
    {
        return array_merge(['search' => SearchFilter::class], $this->filterMap);
    }

    // ── Hooks ──────────────────────────────────────────────────────────────────

    /**
     * Always-on constraints applied before dynamic filters and ES pre-processing.
     * Override in domain to add tenant, soft-delete, active-flag, etc. conditions.
     *
     * @param  BaseQueryBuilder<Model>  $qb
     * @return BaseQueryBuilder<Model>
     */
    protected function applyBaseFilters(BaseQueryBuilder $qb): BaseQueryBuilder
    {
        return $qb;
    }

    /**
     * Override to modify the query builder before filters are applied.
     * InteractsWithElasticsearch overrides this to inject ES-based id constraints.
     *
     * @param  BaseQueryBuilder<Model>  $qb
     * @return BaseQueryBuilder<Model>
     */
    protected function postProcessBuilder(BaseQueryBuilder $qb, Data $filters): BaseQueryBuilder
    {
        return $qb;
    }

    // ── Internals ──────────────────────────────────────────────────────────────

    protected function throwNotFound(int|string $id): never
    {
        if ($this->notFoundException === ModelNotFoundException::class) {
            throw (new ModelNotFoundException)->setModel($this->model, [$id]);
        }

        // @phpstan-ignore-next-line — domain exceptions expose forId(int|string): static
        throw $this->notFoundException::forId($id);
    }

    private function bootOnce(): void
    {
        if ($this->booted) {
            return;
        }

        if (! is_a($this->queryBuilder, BaseQueryBuilder::class, true)) {
            throw new TypeError(sprintf('queryBuilder must extend %s', BaseQueryBuilder::class));
        }

        if (! is_a($this->filterBuilder, BaseFilterBuilder::class, true)) {
            throw new TypeError(sprintf('filterBuilder must extend %s', BaseFilterBuilder::class));
        }

        if (! is_a($this->collectionProcessor, BaseQueryBuilderPostprocessor::class, true)) {
            throw new TypeError(sprintf('collectionProcessor must extend %s', BaseQueryBuilderPostprocessor::class));
        }

        $this->booted = true;
    }
}
