<?php

declare(strict_types=1);

namespace Service\Repository\Contracts;

use Closure;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Service\Repository\Exceptions\RepositoryException;
use Service\Repository\Repositories\Repository;

/**
 * Interface BaseRepositoryContract
 *
 * @method forgetCache()
 * @method isCacheClearEnabled()
 * @method getCacheLifetime()
 * @method distanceCord($latitude, $longitude, string $distance = 1): self
 * @method distance($latitude, $longitude): self
 * @method withoutGlobalScopes($scopes = null)
 */
interface BaseRepositoryContract
{
    /**
     * Dynamically pass missing static methods to the model.
     *
     * @param $method
     * @param $parameters
     *
     * @return mixed
     */
    public static function __callStatic($method, $parameters);

    /**
     * @param array $attributes
     * @return Model
     */
    public function model($attributes = []): Model;

    /**
     * Get Model fillable array
     *
     * @return array
     */
    public function getFillable(): array;

    /**
     * Get Searchable Fields
     *
     * @return array
     */
    public function getFieldsSearchable(): array;

    /**
     * Get Model primary key name
     *
     * @return Model Primary Key Name
     */
    public function getKeyName(): ?string;

    /**
     * Get Model primary key name
     *
     * @return Model Primary Key Name
     */
    public function getTable(): ?string;

    /**
     * Retrieve the "count" result of the query.
     *
     * @param string $column
     * @return bool
     */
    public function exists(string $column = '*'): bool;

    /**
     * @param string $where
     * @param array $values
     * @return bool|null
     */
    public function deletesBy(string $where, array $values = []): ?bool;

    /**
     * @param null $column
     * @param array $attributes
     * @return object|null
     */
    public function firstLatest($column = null, array $attributes = ['*']): ?object;

    /**
     * @param null $column
     * @return object|null
     */
    public function firstOldest($column = null): ?object;

    /**
     * @param $column
     * @return Builder
     */
    public function latest($column = null): Builder;

    /**
     * @param null $column
     * @return Builder
     */
    public function oldest($column = null): Builder;

    /**
     * Update an entity with the given attributes.
     *
     * @param array $attrs
     * @param bool $syncRelations
     *
     * @return bool|Collection
     */
    public function updateSet(array $attrs = [], bool $syncRelations = false): Collection|bool;

    /**
     * Update an entity with the given attributes.
     *
     * @return bool
     */
    public function deletes(): bool;

    /**
     * @param $values
     * @return bool
     */
    public function insert($values): bool;

    /**
     * @param $against
     * @param mixed ...$matches
     * @return null|WhereClauseContract
     */
    public function fullSearch($against, ...$matches): ?static;

    /**
     * Find all entities matching where conditions.
     *
     * @param array $where
     * @param array $attributes
     *
     * @return object|null
     */
    public function firstWhere(array $where, $attributes = ['*']): ?object;

    /**
     * Set the IoC container instance.
     *
     * @param Container $container
     *
     * @return static
     */
    public function setContainer(Container $container);

    /**
     * Get the IoC container instance or any of its services.
     *
     * @param string|null $service
     *
     * @return mixed
     */
    public function getContainer($service = null);

    /**
     * Set the connection associated with the repository.
     *
     * @param string $name
     *
     * @return static
     */
    public function setConnection($name);

    /**
     * Get the current connection for the repository.
     *
     * @return string
     */
    public function getConnection(): string;

    /**
     * Set the repository identifier.
     *
     * @param string $repositoryId
     *
     * @return static
     */
    public function setRepositoryId($repositoryId): Repository|BaseRepositoryContract|static;

    /**
     * Get the repository identifier.
     *
     * @return string
     */
    public function getRepositoryId(): string;

    /**
     * Set the repository model.
     *
     * @param string $model
     *
     * @return static
     */
    public function setModel($model): static;

    /**
     * Get the repository model.
     *
     * @return string
     */
    public function getModel(): string;

    /**
     * Get the repository model.
     *
     * @return string|null
     */
    public function getMap(): ?string;

    /**
     * Create a new repository model instance.
     *
     * @return Model
     * @throws RepositoryException
     */
    public function createModel(): Model;

    /**
     * Set the relationships that should be eager loaded.
     *
     * @param array|string $rels
     *
     * @return static
     */
    public function with($rels): WhereClauseContract;

    /**
     * Find an entity by its primary key.
     *
     * @param int|string $id
     * @param string[] $attrs
     * @return object|null
     */
    public function find(int|string $id, $attrs = ['*']): ?object;

    /**
     * Find an entity by its primary key or throw an exception.
     *
     * @param mixed $id
     * @param array $attributes
     *
     * @return mixed
     * @throws RuntimeException
     */
    public function findOrFail($id, $attributes = ['*']);

    /**
     * Find an entity by its primary key or return fresh entity instance.
     *
     * @param int $id
     * @param array $attributes
     * @param bool $sync_relations
     * @return Model|mixed
     * @throws BindingResolutionException
     * @throws RepositoryException
     */
    public function findOrNew(int $id, array $attributes = ['*'], bool $sync_relations = false): ?object;

    /**
     * Find an entity by one of its attributes.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $attributes
     * @return object|null
     */
    public function findBy(string $attribute, mixed $value, array $attributes = ['*']): object|null;

    /**
     * Find the first entity.
     *
     * @param array $attr
     * @return object|null
     */
    public function findFirst($attr = ['*']): object|null;

    /**
     * Find all entities.
     *
     * @param array $attr
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findAll($attr = ['*']): \Illuminate\Database\Eloquent\Collection;

    /**
     * Paginate all entities.
     *
     * @param int|string|null $per_page
     * @param string[] $attributes
     * @param string $page_name
     * @param int|string|null $page
     * @return LengthAwarePaginator
     */
    public function paginate(
        int|string $per_page = null,
        array $attributes = ['*'],
        string $page_name = 'page',
        null|int|string $page = null
    ): LengthAwarePaginator;

    /**
     * Paginate all entities into a simple paginator.
     *
     * @param int|string|null $per_page
     * @param array $attributes
     * @param string $page_name
     * @param int|string|null $page
     * @return Paginator
     */
    public function simplePaginate(
        int|string $per_page = null,
        array $attributes = ['*'],
        string $page_name = 'page',
        null|int|string $page = null
    ): Paginator;

    /**
     * Cursor paginate is a lazy collection or php generators
     *
     * @param int|string|null $per_page
     * @param array $columns
     * @param string $cursor_name
     * @param $cursor
     * @return mixed
     */
    public function cursorPaginate(
        int|string $per_page = null,
        array $columns = ['*'],
        string $cursor_name = 'cursor',
        $cursor = null
    );

    /**
     * Find all entities matching where conditions.
     *
     * @param array $where
     * @param array $attrs
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findWhere(array $where, $attrs = ['*']): \Illuminate\Database\Eloquent\Collection;

    /**
     * Find all entities matching whereIn conditions.
     *
     * @param array $where
     * @param array $attrs
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findWhereIn(array $where, $attrs = ['*']): \Illuminate\Database\Eloquent\Collection;

    /**
     * Find all entities matching whereNotIn conditions.
     *
     * @param array $where
     * @param array $attributes
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findWhereNotIn(array $where, $attributes = ['*']): \Illuminate\Database\Eloquent\Collection;

    /**
     * Find all entities matching whereHas conditions.
     *
     * @param array $where
     * @param array $attributes
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findWhereHas(array $where, $attributes = ['*']): \Illuminate\Database\Eloquent\Collection;

    /**
     * @param array $where
     * @param array $attrs
     * @param bool $sync_relations
     * @param bool $merge
     * @return null|object
     */
    public function updateOrCreate(
        array $where,
        array $attrs,
        bool $sync_relations = false,
        bool $merge = false
    ): ?object;

    /**
     * @param $attrs
     * @param array $values
     * @param bool $sync_relations
     * @return object|bool|null
     * @throws ContainerExceptionInterface
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws RepositoryException
     */
    public function updateOrInsert($attrs, $values = [], $sync_relations = false): null|object|bool;

    /**
     * @param array $attrs
     * @param bool $sync_relations
     * @return Model|null
     * @throws BindingResolutionException
     * @throws RepositoryException
     */
    public function create(array $attrs = [], bool $sync_relations = false): ?object;

    /**
     * Update an entity with the given attributes.
     *
     * @param int|string|Model $id
     * @param array $attrs
     * @param bool $sync_relations
     * @return null|object
     */
    public function update(int|string|Model $id, array $attrs = [], bool $sync_relations = false): ?object;

    /**
     * Store the entity with the given attributes.
     *
     * @param mixed $id
     * @param array $attrs
     * @param bool $sync_relations
     * @return object|null|bool
     * @throws BindingResolutionException
     * @throws ContainerExceptionInterface
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws RepositoryException
     */
    public function store(int $id = null, array $attrs = [], bool $sync_relations = false): null|object|bool;

    /**
     * Delete an entity with the given id.
     *
     * @param mixed $id
     *
     * @return bool|object
     */
    public function delete(int|string $id): false|object;

    /**
     * Restore an entity with the given id.
     *
     * @param mixed $id
     *
     * @return mixed
     */
    public function restore($id);

    /**
     * Start a new database transaction.
     *
     * @param Closure|null $closure
     * @param Closure|null $closure_before
     * @param int $tries
     * @return void|mixed
     * @throws Exception
     */
    public function beginTransaction(Closure $closure = null, Closure $closure_before = null, int $tries = 1);

    /**
     * Commit the active database transaction.
     *
     * @return void
     */
    public function commit(): void;

    /**
     * Rollback the active database transaction.
     *
     * @return void
     */
    public function rollBack(): void;

    /**
     * Retrieve the "count" result of the query.
     *
     * @param string $columns
     * @return int
     */
    public function count($columns = '*'): int;

    /**
     * Retrieve the minimum value of a given column.
     *
     * @param string $column
     * @return mixed
     */
    public function min(string $column);

    /**
     * Retrieve the maximum value of a given column.
     *
     * @param string $column
     * @return mixed
     */
    public function max(string $column): mixed;

    /**
     * Retrieve the average value of a given column.
     *
     * @param string $column
     * @return mixed
     */
    public function avg(string $column): mixed;

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param string $column
     * @return mixed
     */
    public function sum(string $column): mixed;

    /**
     * Dynamically pass missing methods to the model.
     *
     * @param string $method
     * @param array $parameters
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters);
}
