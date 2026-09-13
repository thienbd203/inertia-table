<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Table;
use Musing\InertiaTable\Tests\Consumer\Topic;

Route::get('/multiple', fn () => Inertia::render('Topics/Multiple', [
    'publishedTopics' => Table::build(
        resource: Topic::query()->where('status', 'published'),
        columns: [TextColumn::make('name')->searchable()->sortable()],
        search: ['name'],
        name: 'publishedTopics',
        defaultSort: 'name',
    ),
    'draftTopics' => Table::build(
        resource: Topic::query()->where('status', 'draft'),
        columns: [TextColumn::make('name')->searchable()->sortable()],
        search: ['name'],
        name: 'draftTopics',
        defaultSort: 'name',
    ),
]));
