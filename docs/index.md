---
layout: home
titleTemplate: false

hero:
  name: Musing Inertia Table
  text: Server-driven tables for Laravel and Inertia
  tagline: Define query capabilities and workflows in PHP, then render a complete Vue data table with one component.
  actions:
    - theme: brand
      text: Get started
      link: /guide/getting-started
    - theme: alt
      text: Browse features
      link: /features/actions

features:
  - title: Server authoritative
    details: Search, sort, filters, selection, actions, and exports stay inside the capabilities declared by your table.
  - title: Product-ready renderer
    details: Vue 3, Tailwind CSS, keyboard-friendly controls, sticky layouts, summaries, and saved views are included.
  - title: Large-result workflows
    details: Select all matching rows, queue bulk actions, and produce synchronous or queued exports without loading every ID.
  - title: Escape hatches included
    details: Customize cells and workflows with slots, or compose the typed headless APIs for an application-owned renderer.
---

![Product table with active filters, layout controls, actions, exports, and server-side summaries](/images/table-overview.png)

## One definition, two packages

The Laravel package owns normalized query state and server-side behavior. The Vue
package renders the resource and sends every interaction back through Inertia.
The browser never turns an undeclared column or filter into SQL.

```php
use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Musing\InertiaTable\Columns\DateTimeColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Table;

final class TopicsTable extends Table
{
    public function query(): Builder
    {
        return Topic::query();
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->searchable()->sortable(),
            DateTimeColumn::make('created_at')->sortable(),
        ];
    }
}
```

```vue
<DataTable :resource="topics" />
```

<div class="feature-grid">
  <div>
    <h3>Build your first table</h3>
    <p>Install both packages and render a searchable, sortable resource in a few minutes.</p>
  </div>
  <div>
    <h3>Explore the playground</h3>
    <p>See filters, summaries, saved views, queued work, exports, and column layout together.</p>
  </div>
</div>

[Start the guide](/guide/getting-started) or inspect the
[playground source](https://github.com/thienbd203/inertia-table-playground).
