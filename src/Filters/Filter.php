<?php

namespace Toolbelt\InertiaTable\Filters;

use Illuminate\Contracts\Support\Arrayable;
use Spatie\QueryBuilder\AllowedFilter;

/** @implements Arrayable<string, mixed> */
abstract class Filter implements Arrayable
{
    /** @var array<int, string> */
    protected array $clauses;

    /** @var array<string, mixed> */
    protected array $meta = [];

    final public function __construct(
        public readonly string $attribute,
        public readonly string $label,
    ) {
        $this->clauses = [$this->defaultClause()];
    }

    public static function make(string $attribute, ?string $label = null): static
    {
        return new static($attribute, $label ?? str($attribute)->headline()->toString());
    }

    abstract public function allowedFilter(): AllowedFilter;

    abstract public function defaultClause(): string;

    public function normalize(mixed $value): mixed
    {
        return $value;
    }

    /** @param array<int, string> $clauses */
    public function clauses(array $clauses): static
    {
        $clauses = array_values(array_unique(array_filter(
            $clauses,
            fn (string $clause) => $clause !== '',
        )));

        $this->clauses = $clauses === [] ? [$this->defaultClause()] : $clauses;

        return $this;
    }

    /** @param array<string, mixed> $meta */
    public function meta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    /** @return array{enabled: true, clause: string, value: mixed}|null */
    public function normalizeState(mixed $state): ?array
    {
        if (! is_array($state) || ! filter_var($state['enabled'] ?? false, FILTER_VALIDATE_BOOL)) {
            return null;
        }

        $clause = is_string($state['clause'] ?? null) ? $state['clause'] : $this->defaultClause();

        if (! in_array($clause, $this->clauses, true)) {
            return null;
        }

        $value = $this->normalize($state['value'] ?? null);

        if ($value === null) {
            return null;
        }

        return ['enabled' => true, 'clause' => $clause, 'value' => $value];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'attribute' => $this->attribute,
            'label' => $this->label,
            'clauses' => $this->clauses,
            'meta' => $this->meta,
        ];
    }
}
