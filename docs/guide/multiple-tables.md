# Multiple tables

Several table resources can share one Inertia page. Give each resource a unique
name so its URL state and partial reload prop stay independent.

## Runnable recipe

This example uses the Topic schema from [getting started](/guide/getting-started):
`id`, `name`, `status` and timestamps, with `published` and `draft` statuses.
It displays two independently scoped queries. The consumer uses an isolated
SQLite database containing Alpha and Gamma (published) and Beta (draft).

### Route

Place this route in your host's existing authorization group. Replace the
fixture's `Musing\InertiaTable\Tests\Consumer\Topic` import with
`App\Models\Topic`. The `name` arguments must match the Inertia prop keys.

<<< ../../tests/Consumer/multiple-tables.php

### Vue page

Register this page as `Topics/Multiple` in your host's Inertia resolver:

<<< ../../tests/Consumer/app/Multiple.vue

Run `npm run test:consumer` and `node tests/Consumer/serve.mjs` from the package
checkout, then open the printed `/multiple` URL. Search Gamma in Published;
Draft should still show Beta. Search Beta in Draft and sort Published; both
search values should remain in their own URL namespaces. Reload the URL to
check that both states are restored. Append `?csr=1` to start without SSR.

The packed consumer checks both queries, simultaneous namespaced state,
partial responses that omit the other prop, and SSR after manually merging
those props. It does not automate browser navigation or prove the client-side
merge behavior. Follow the steps above for that manual verification.

For class-based tables, set each class's protected `$name` to its matching prop
key and keep its query scope in `query()`. See
[table definitions](/guide/table-definitions) for the complete class structure.

## URL state

```text
?table[publishedTopics][search]=Gamma
&table[draftTopics][search]=Beta
&table[draftTopics][sort]=-name
```

Updating one table retains the other namespace. Its Inertia visit requests only
its own prop and declared `reloadProps`.

## Saved View scope

Two named instances of the same table class should use `scopeTableName()` when
their views must remain separate:

```php
use Musing\InertiaTable\Views;

public function views(): ?Views
{
    return Views::make()->scopeTableName();
}
```

Application context such as a workspace or tenant belongs in `attributes()`.
