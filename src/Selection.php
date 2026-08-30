<?php

namespace Musing\InertiaTable;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** @implements Arrayable<string, mixed> */
final readonly class Selection implements Arrayable
{
    /**
     * @param  array<int, int|string>  $keys
     * @param  array<int, int|string>  $except
     * @param  array{search: string, filters: array<string, array{enabled: bool, clause: string, value: mixed}>}  $state
     */
    public function __construct(
        private Table $tableInstance,
        public bool $all,
        public array $keys,
        public array $except,
        public string $table,
        public array $state,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromArray(Table $table, array $payload): self
    {
        $tableName = $payload['table'] ?? null;

        if (! is_string($tableName) || $tableName !== $table->name()) {
            throw ValidationException::withMessages([
                'selection.table' => 'The selection does not belong to this table.',
            ]);
        }

        if (! is_bool($payload['all'] ?? null)) {
            throw ValidationException::withMessages([
                'selection.all' => 'The selection all field must be true or false.',
            ]);
        }

        $all = $payload['all'];
        $keys = self::normalizeKeys($payload['keys'] ?? [], 'selection.keys');
        $except = self::normalizeKeys($payload['except'] ?? [], 'selection.except');

        if (! $all && $keys === []) {
            throw ValidationException::withMessages([
                'selection.keys' => 'At least one selected key is required.',
            ]);
        }

        $state = $payload['state'] ?? [];

        if (! is_array($state)) {
            throw ValidationException::withMessages([
                'selection.state' => 'The selection state must be an object.',
            ]);
        }

        return new self(
            tableInstance: $table,
            all: $all,
            keys: $all ? [] : $keys,
            except: $all ? $except : [],
            table: $tableName,
            state: $table->normalizeSelectionState($state),
        );
    }

    /** @return Builder<Model> */
    public function query(): Builder
    {
        return $this->tableInstance->queryForSelection($this);
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    /** @return Collection<int, Model> */
    public function get(): Collection
    {
        return $this->query()->get();
    }

    public function firstOrFail(): Model
    {
        return $this->query()->firstOrFail();
    }

    /**
     * Iterate without loading the entire matching dataset into memory.
     *
     * @param  callable(Model, self): mixed  $callback
     */
    public function each(callable $callback, int $chunkSize = 1000): bool
    {
        return $this->query()->eachById(
            fn (Model $model) => $callback($model, $this),
            $chunkSize,
        );
    }

    public function toArray(): array
    {
        return [
            'all' => $this->all,
            'keys' => $this->keys,
            'except' => $this->except,
            'table' => $this->table,
            'state' => $this->state,
        ];
    }

    /** @return array<int, int|string> */
    private static function normalizeKeys(mixed $keys, string $field): array
    {
        if (! is_array($keys)) {
            throw ValidationException::withMessages([
                $field => 'The selection keys must be an array.',
            ]);
        }

        foreach ($keys as $key) {
            if (! is_int($key) && ! is_string($key)) {
                throw ValidationException::withMessages([
                    $field => 'Every selection key must be an integer or string.',
                ]);
            }
        }

        return array_values(array_unique($keys, SORT_REGULAR));
    }
}
