<?php

namespace Musing\InertiaTable\Columns;

final class ActionColumn extends Column
{
    private bool $asDropdown = false;

    public static function new(?string $header = null): static
    {
        return self::make(
            '__actions',
            $header ?? (string) trans('inertia-table::messages.columns.actions'),
        )
            ->toggleable(false)
            ->rightAligned();
    }

    public function asDropdown(bool $asDropdown = true): static
    {
        $this->asDropdown = $asDropdown;

        return $this;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'type' => 'action',
            'asDropdown' => $this->asDropdown,
        ];
    }
}
