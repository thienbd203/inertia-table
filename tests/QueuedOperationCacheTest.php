<?php

use Illuminate\Support\Facades\Cache;
use Musing\InertiaTable\Support\QueuedOperationCache;

it('keeps request and status cache keys isolated by operation prefix', function () {
    $actions = new QueuedOperationCache('inertia-table:queued-action');
    $exports = new QueuedOperationCache('inertia-table:queued-export');

    expect($actions->reserve('same-request', 'action-1', 60))->toBeNull()
        ->and($exports->reserve('same-request', 'export-1', 60))->toBeNull()
        ->and($actions->reserve('same-request', 'action-2', 60))->toBe('action-1')
        ->and(Cache::get('inertia-table:queued-action:request:'.hash('sha256', 'same-request')))->toBe('action-1')
        ->and(Cache::get('inertia-table:queued-export:request:'.hash('sha256', 'same-request')))->toBe('export-1');

    $actions->put('shared-id', ['status' => 'queued'], 60);
    $exports->put('shared-id', ['status' => 'ready'], 60);

    expect($actions->get('shared-id'))->toBe(['status' => 'queued'])
        ->and($exports->get('shared-id'))->toBe(['status' => 'ready']);
});
