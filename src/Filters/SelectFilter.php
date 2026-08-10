<?php

namespace Toolbelt\InertiaTable\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

class SelectFilter extends Filter
{
    /** @var array<string|int, string> */
    protected array $options = [];

    protected ?Closure $applyUsing = null;

    /**
     * @param  array<string|int, string>  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function applyUsing(Closure $callback): static
    {
        $this->applyUsing = $callback;

        return $this;
    }

    public function allowedFilter(): AllowedFilter
    {
        return AllowedFilter::callback(
            $this->attribute,
            function (Builder $query, mixed $value) {
                if ($this->applyUsing) {
                    ($this->applyUsing)($query, $value);

                    return;
                }

                $query->where($this->attribute, $value);
            },
        )->delimiter('');
    }

    public function normalize(mixed $value): string|int|null
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        foreach (array_keys($this->options) as $option) {
            if ((string) $option === (string) $value) {
                return $option;
            }
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'type' => 'select',
            'options' => $this->options,
        ];
    }
}
