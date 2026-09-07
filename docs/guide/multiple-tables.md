# Multiple tables

Several table resources can share one Inertia page. Give each resource a unique
name so its URL state and partial reload prop stay independent.

## Controller

```php
return inertia('Dashboard', [
    'activeTopics' => ActiveTopicsTable::make(),
    'archivedTopics' => ArchivedTopicsTable::make(),
]);
```

Give the classes unique protected names that match their Inertia props:

```php
final class ActiveTopicsTable extends Table
{
    protected ?string $name = 'activeTopics';
}

final class ArchivedTopicsTable extends Table
{
    protected ?string $name = 'archivedTopics';
}
```

Use `Table::build(..., name: 'activeTopics')` when two anonymous tables share a
page.

## Vue page

```vue
<script setup lang="ts">
import { DataTable, type TableResource } from "@musing/inertia-table-vue";

defineProps<{
    activeTopics: TableResource<Topic>;
    archivedTopics: TableResource<Topic>;
}>();
</script>

<template>
    <section>
        <h2>Active</h2>
        <DataTable :resource="activeTopics" />
    </section>

    <section>
        <h2>Archived</h2>
        <DataTable :resource="archivedTopics" />
    </section>
</template>
```

## URL state

```text
?table[activeTopics][search]=laravel
&table[archivedTopics][sort]=-created_at
```

Updating one table retains the other namespace. Its Inertia visit requests only
its own prop and declared `reloadProps`.

## Saved View scope

Two named instances of the same table class should use `scopeTableName()` when
their views must remain separate:

```php
public function views(): ?Views
{
    return Views::make()->scopeTableName();
}
```

Application context such as a workspace or tenant belongs in `attributes()`.
