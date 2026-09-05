<?php

namespace Musing\InertiaTable\Summaries;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Musing\InertiaTable\Columns\Column;
use Musing\InertiaTable\Table;

/** @implements Arrayable<string, mixed> */
final class Summary implements Arrayable
{
    private ?string $format = null;

    /** @param Closure(Builder<Model>, Column, Table): mixed|null $resolver */
    private function __construct(
        private readonly SummaryAggregate $aggregate,
        private readonly ?string $attribute,
        private readonly ?Closure $resolver = null,
    ) {}

    public static function aggregate(
        string|SummaryAggregate $aggregate,
        string $defaultAttribute,
        ?string $attribute = null,
    ): self {
        $aggregate = is_string($aggregate)
            ? SummaryAggregate::tryFrom(strtolower(trim($aggregate)))
            : $aggregate;

        if (! $aggregate instanceof SummaryAggregate || $aggregate === SummaryAggregate::Custom) {
            throw new LogicException('Summary aggregates must be one of count, count_distinct, sum, avg, min, or max.');
        }

        if ($aggregate === SummaryAggregate::Count) {
            return new self($aggregate, null);
        }

        $attribute = trim($attribute ?? $defaultAttribute);

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $attribute)) {
            throw new LogicException('Built-in summaries require a base column name. Use summaryUsing() for relationships or expressions.');
        }

        return new self($aggregate, $attribute);
    }

    /** @param Closure(Builder<Model>, Column, Table): mixed $resolver */
    public static function custom(Closure $resolver): self
    {
        return new self(SummaryAggregate::Custom, null, $resolver);
    }

    public function format(?string $format): self
    {
        $format = is_string($format) ? trim($format) : null;
        $this->format = $format !== '' ? $format : null;

        return $this;
    }

    public function aggregateType(): SummaryAggregate
    {
        return $this->aggregate;
    }

    public function attribute(): ?string
    {
        return $this->attribute;
    }

    public function resolvedFormat(): ?string
    {
        return $this->format;
    }

    /** @param Builder<Model> $query */
    public function resolve(Builder $query, Column $column, Table $table): mixed
    {
        if (! $this->resolver instanceof Closure) {
            throw new LogicException('Only custom summaries can be resolved through a callback.');
        }

        return app()->call($this->resolver, compact('query', 'column', 'table'));
    }

    public function toArray(): array
    {
        return [
            'type' => $this->aggregate->value,
            'format' => $this->format,
        ];
    }
}
