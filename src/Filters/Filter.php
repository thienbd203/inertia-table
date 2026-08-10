<?php

namespace Toolbelt\InertiaTable\Filters;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

/** @implements Arrayable<string, mixed> */
abstract class Filter implements Arrayable
{
    /** @var array<int, string> */
    protected array $clauses;

    /** @var array<string, mixed> */
    protected array $meta = [];

    protected mixed $defaultValue = null;

    protected ?string $defaultClauseValue = null;

    protected bool $hasDefaultValue = false;

    protected bool $showClause = true;

    final public function __construct(
        public readonly string $attribute,
        public readonly string $label,
    ) {
        $this->clauses = array_map(fn (Clause $clause) => $clause->value, $this->defaultClauses());
    }

    public static function make(string $attribute, ?string $label = null): static
    {
        return new static($attribute, $label ?? str($attribute)->headline()->toString());
    }

    /** @return array<int, Clause> */
    abstract protected function defaultClauses(): array;

    abstract protected function apply(Builder $query, string $clause, mixed $value): void;

    public function defaultClause(): string
    {
        return $this->defaultClauseValue ?? $this->clauses[0];
    }

    public function normalize(mixed $value, ?string $clause = null): mixed
    {
        return $value;
    }

    /** @param array<int, Clause|string> $clauses */
    public function clauses(array $clauses): static
    {
        $values = array_values(array_unique(array_filter(array_map(
            fn (Clause|string $clause) => $clause instanceof Clause ? $clause->value : $clause,
            $clauses,
        ))));

        if ($values !== []) {
            $this->clauses = $values;
        }

        return $this;
    }

    public function default(mixed $value, Clause|string|null $clause = null): static
    {
        $this->defaultValue = $value;
        $this->defaultClauseValue = $clause instanceof Clause ? $clause->value : $clause;
        $this->hasDefaultValue = true;

        return $this;
    }

    public function withoutClause(): static
    {
        $this->showClause = false;

        if (in_array(Clause::Equals->value, $this->clauses, true)) {
            $this->clauses = [Clause::Equals->value];
            $this->defaultClauseValue = Clause::Equals->value;
        }

        return $this;
    }

    public function nullable(bool $nullable = true): static
    {
        if ($nullable) {
            $this->clauses([...$this->clauses, Clause::IsSet, Clause::IsNotSet]);
        }

        return $this;
    }

    /** @param array<string, mixed> $meta */
    public function meta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function allowedFilter(): AllowedFilter
    {
        return AllowedFilter::callback($this->attribute, function (Builder $query, mixed $state) {
            if (is_string($state)) {
                $decoded = json_decode($state, true);
                $state = is_array($decoded) ? $decoded : $state;
            }

            if (! is_array($state)) {
                return;
            }

            $clause = $state['clause'] ?? null;
            if (! is_string($clause) || ! in_array($clause, $this->clauses, true)) {
                return;
            }

            if ($clause === Clause::IsSet->value) {
                $query->whereNotNull($this->attribute);

                return;
            }

            if ($clause === Clause::IsNotSet->value) {
                $query->whereNull($this->attribute);

                return;
            }

            $this->apply($query, $clause, $state['value'] ?? null);
        })->delimiter('');
    }

    /** @return array{enabled: bool, clause: string, value: mixed} */
    public function normalizeState(mixed $state): array
    {
        if (! is_array($state)) {
            return $this->defaultState();
        }

        $enabled = filter_var($state['enabled'] ?? false, FILTER_VALIDATE_BOOL);
        $clause = is_string($state['clause'] ?? null) ? $state['clause'] : $this->defaultClause();

        if (! $enabled || ! in_array($clause, $this->clauses, true)) {
            return ['enabled' => false, 'clause' => $this->defaultClause(), 'value' => null];
        }

        $value = $this->normalize($state['value'] ?? null, $clause);
        if ($value === null && ! in_array($clause, [Clause::IsTrue->value, Clause::IsFalse->value, Clause::IsSet->value, Clause::IsNotSet->value], true)) {
            return ['enabled' => false, 'clause' => $this->defaultClause(), 'value' => null];
        }

        return ['enabled' => true, 'clause' => $clause, 'value' => $value];
    }

    /** @return array{enabled: bool, clause: string, value: mixed} */
    public function defaultState(): array
    {
        if (! $this->hasDefaultValue) {
            return ['enabled' => false, 'clause' => $this->defaultClause(), 'value' => null];
        }

        return $this->normalizeState([
            'enabled' => true,
            'clause' => $this->defaultClause(),
            'value' => $this->defaultValue,
        ]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'attribute' => $this->attribute,
            'label' => $this->label,
            'clauses' => $this->clauses,
            'meta' => $this->meta,
            'hasDefaultValue' => $this->hasDefaultValue,
            'showClause' => $this->showClause,
        ];
    }
}
