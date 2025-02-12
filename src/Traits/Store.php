<?php

declare(strict_types=1);

namespace Service\Repository\Traits;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Service\Repository\Exceptions\RepositoryException;

use function count;
use function func_get_args;
use function in_array;

trait Store
{
    /**
     * @inheritDoc
     *
     * @throws ContainerExceptionInterface
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws RepositoryException
     */
    public function updateSet(array $attrs = [], bool $syncRelations = false): Collection|bool
    {
        $this->prepareQuery($this->model());

        $entities = $this->findAll($this->model()->getKeyName());

        if (1 > $entities->count()) {
            // empty Collection
            return false;
        }

        $updated = [];

        foreach ($entities as $entity) {
            // Extract relationships
            if ($syncRelations) {
                $relations = $this->extractRelations($entity, $attrs);
                Arr::forget($attrs, array_keys($relations));
            }

            // Fill instance with data
            $entity->fill($attrs);

            // Update the instance
            $updated[] = $entity->save();

            // Sync relationships
            if ($syncRelations && isset($relations)) {
                $this->syncRelations($entity, $relations, 'update');
            }

            if ($updated) {
                // Fire the updated event
                DB::afterCommit(
                    fn() => $this->getContainer('events')->dispatch(
                        $this->getRepositoryId() . '.entity.updated',
                        [$this, $entity]
                    )
                );
            }
        }

        return !in_array(false, $updated, true);
    }

    /**
     * @inheritDoc
     */
    public function deletes(): ?bool
    {
        // Find the given instance
        $entity = $this->createModel();
        $result = $this->prepareQuery($entity)->get($entity->getKeyName());
        $count = $result->count();

        if (!$count) {
            $deleted = null;
        } elseif (1 < $count) {
            foreach ($result as $entity) {
                // Delete the instance
                $deleted = $entity->delete();
            }
        } else {
            $deleted = $result[0]->delete();
        }

        return $deleted ?? null;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ContainerExceptionInterface
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws RepositoryException
     */
    public function delete(int|string $id, array $sync_relations = []): false|object
    {
        $deleted = false;

        // Find the given instance
        $entity = $id instanceof Model ? $id : $this->find($id);

        if ($entity) {
            // Delete the instance
            if (!empty($sync_relations)) {
                $relations = $this->extractRelations($entity, $sync_relations);
                $this->syncRelations($entity, $relations, 'delete');
            }

            $deleted = $entity->delete();
            // Fire the deleted event
            DB::afterCommit(
                fn() => $this->getContainer('events')->dispatch(
                    $this->getRepositoryId() . '.entity.deleted',
                    [$this, $entity]
                )
            );
        }

        return $deleted ? $entity : $deleted;
    }

    /**
     * @inheritDoc
     */
    public function createMany(array $attrs = [], bool $sync_relations = false): Collection
    {
        $result = new Collection();

        if (array_is_list($attrs)) {
            foreach ($attrs as $attr) {
                $result->push($this->create($attr, $sync_relations));
            }
        } else {
            $result->push($this->create($attrs, $sync_relations));
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function create(array $attrs = [], bool $sync_relations = false): ?Model
    {
        // Create a new instance
        $entity = $this->createModel();

        // Extract relationships
        if ($sync_relations) {
            $relations = $this->extractRelations($entity, $attrs);
            Arr::forget($attrs, array_keys($relations));
        }

        // Fill instance with data
        $entity->fill($attrs);

        // Save the instance
        $created = $entity->save();

        // Sync relationships
        if ($sync_relations && isset($relations)) {
            $this->syncRelations($entity, $relations);
        }

        // The Fire created event
        DB::afterCommit(
            fn() => $this->getContainer('events')->dispatch(
                $this->getRepositoryId() . '.entity.created',
                [$this, $entity]
            )
        );

        // Return instance
        return $created ? $entity : null;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ContainerExceptionInterface
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws RepositoryException
     */
    public function update(int|string|Model $id, array $attrs = [], bool $sync_relations = false): ?object
    {
        // Find the given instance
        $entity = $id instanceof Model ? $id : $this->find($id, [$this->model()->getKeyName()]);

        if (!$entity) {
            return null;
        }

        // Extract relationships
        if ($sync_relations) {
            $relations = $this->extractRelations($entity, $attrs);
            Arr::forget($attrs, array_keys($relations));
        }

        // Fill instance with data
        $entity->fill($attrs);

        //Check if we are updating attributes values
        $dirty = $sync_relations ? [1] : $entity->getDirty();

        // Update the instance
        $updated = $entity->save();

        // Sync relationships
        if ($sync_relations && isset($relations)) {
            $this->syncRelations($entity, $relations, 'update');
        }

        if (count($dirty) > 0) {
            // Fire the updated event
            DB::afterCommit(
                fn() => $this->getContainer('events')->dispatch(
                    $this->getRepositoryId() . '.entity.updated',
                    [$this, $entity]
                )
            );
        }

        return $updated ? $entity : null;
    }

    /**
     * @inheritdoc
     * @throws BindingResolutionException
     * @throws ContainerExceptionInterface
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws RepositoryException
     */
    public function updateOrCreate(
        array $where,
        array $attrs,
        bool $sync_relations = false,
        bool $merge = false
    ): ?object
    {
        $queries_chunk = array_chunk($where, 3);

        if (1 < count($queries_chunk)) {
            foreach ($queries_chunk as $query) {
                $this->where($query[0], $query[1], $query[2]);
            }
        } else {
            $this->where($queries_chunk[0][0], $queries_chunk[0][1], $queries_chunk[0][2]);
        }

        $result = null;
        $entities = $this->findAll();
        $entities_count = $entities->count();

        $query_attribute[$queries_chunk[0][0]] = $queries_chunk[0][2];
        $attributes = $merge ? array_merge($query_attribute, $attrs) : $attrs;

        if (1 < $entities_count) {
            foreach ($entities as $entity) {
                $result = $this->update($entity->{$entity->getKeyName()}, $attributes, $sync_relations);
            }
        } elseif (1 === $entities_count) {
            $result = $this->update($entities[0]->{$entities[0]->getKeyName()}, $attributes, $sync_relations);
        } else {
            $result = $this->create($attributes, $sync_relations);
        }

        return $result;
    }

    /**
     * @inheritDoc
     * @throws RepositoryException
     */
    public function insert($values): bool
    {
        // Create a new instance
        $entity = $this->createModel();

        $inserted = $this->executeCallback(
            static::class,
            __FUNCTION__,
            func_get_args(),
            fn() => $this->prepareQuery($this->createModel())->insert($values)
        );

        // Fire the created event
        DB::afterCommit(
            fn() => $this->getContainer('events')->dispatch(
                $this->getRepositoryId() . '.entity.created',
                [$this, $entity]
            )
        );

        return $inserted;
    }

    /**
     * @inheritdoc
     */
    public function restore(int|string $id)
    {
        $restored = false;

        // Find the given instance
        $entity = $id instanceof Model ? $id : $this->withTrashed()->find($id);

        if ($entity) {
            // Restore the instance
            $restored = $entity->restore();

            // Fire the restored event
            DB::afterCommit(
                fn() => $this->getContainer('events')->dispatch(
                    $this->getRepositoryId() . '.entity.restored',
                    [$this, $entity]
                )
            );
        }

        return $restored ? $entity : $restored;
    }
}
