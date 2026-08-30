<?php

namespace Musing\InertiaTable;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int|string $id
 * @property string $table_key
 * @property string|null $table_name
 * @property string|null $user_id
 * @property string $name
 * @property array<string, mixed> $state
 * @property array<string, mixed>|null $attributes
 * @property string $context_hash
 * @property string $scope_hash
 * @property bool $is_shared
 * @property bool $is_default
 * @property int $lock_version
 */
class TableView extends Model
{
    /** @var array<int, string> */
    protected $guarded = [];

    public function getTable(): string
    {
        return (string) config('inertia-table.views.table', 'table_views');
    }

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'attributes' => 'array',
            'is_shared' => 'boolean',
            'is_default' => 'boolean',
            'lock_version' => 'integer',
        ];
    }
}
