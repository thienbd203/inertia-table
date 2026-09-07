# Translations

Laravel owns server-generated defaults. The Vue renderer owns interactive table
messages. Application labels remain the responsibility of the host application.

## Vue locale

The renderer defaults to English and ships complete English and Vietnamese
catalogs.

```ts
import {
    createInertiaTable,
    vi,
} from "@musing/inertia-table-vue";

app.use(
    createInertiaTable({
        locale: "vi-VN",
        messages: vi,
    }),
);
```

The plugin uses Vue provide/inject, so every SSR application instance keeps its
own locale.

## Per-table overrides

```vue
<script setup lang="ts">
import { DataTable, vi } from "@musing/inertia-table-vue";
</script>

<template>
    <DataTable
        :resource="topics"
        locale="vi-VN"
        :messages="{
            ...vi,
            noResults: 'Chưa có chủ đề nào.',
        }"
    />
</template>
```

Local messages fall back to the application catalog, then to English.
Interpolation uses `{name}` syntax. Missing parameters remain visible so an
incomplete translation is easy to find during development.

## Laravel translations

Laravel-generated confirmation defaults, boolean labels, the action-column
heading, and the default empty-state title follow `app()->getLocale()`.

```bash
php artisan vendor:publish --tag=inertia-table-translations
```

Edit the published language files when an application needs different wording.

## Application-owned labels

Translate these before passing them to the package:

- column and filter labels;
- set-filter option labels;
- action and export labels;
- custom empty-state content;
- slot content.

`locale` controls calendars and locale-sensitive UI. `messages` controls only
package-owned interface text.
