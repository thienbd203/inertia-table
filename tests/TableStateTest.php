<?php

use Illuminate\Http\Request;
use Musing\InertiaTable\TableState;

function stateRequest(array $tableState = []): Request
{
    return Request::create('/topics', 'GET', [
        'table' => ['topics' => $tableState],
        'unrelated' => 'kept',
    ]);
}

it('falls back to defaults when the table namespace is missing', function () {
    $state = TableState::fromRequest(
        Request::create('/topics', 'GET'),
        'topics',
        'name',
        25,
        [10, 25, 50],
        ['name' => true, 'score' => false],
    );

    expect($state->search)->toBe('')
        ->and($state->sort)->toBe('name')
        ->and($state->filters)->toBe([])
        ->and($state->columns)->toBe(['name' => true, 'score' => false])
        ->and($state->page)->toBe(1)
        ->and($state->perPage)->toBe(25);
});

it('coerces a negative or zero page back to page one', function () {
    $negative = TableState::fromRequest(stateRequest(['page' => -5]), 'topics', null, 25, [25]);
    $zero = TableState::fromRequest(stateRequest(['page' => 0]), 'topics', null, 25, [25]);
    $nonNumeric = TableState::fromRequest(stateRequest(['page' => 'abc']), 'topics', null, 25, [25]);

    expect($negative->page)->toBe(1)
        ->and($zero->page)->toBe(1)
        ->and($nonNumeric->page)->toBe(1);
});

it('rejects a perPage value outside the configured options', function () {
    $state = TableState::fromRequest(stateRequest(['perPage' => 999]), 'topics', null, 25, [10, 25, 50]);

    expect($state->perPage)->toBe(25);
});

it('rejects a non numeric perPage value', function () {
    $state = TableState::fromRequest(stateRequest(['perPage' => 'lots']), 'topics', null, 25, [10, 25, 50]);

    expect($state->perPage)->toBe(25);
});

it('falls back to the declared default sort when none is requested', function () {
    $state = TableState::fromRequest(stateRequest(), 'topics', 'name', 25, [25]);

    expect($state->sort)->toBe('name');
});

it('only overrides column visibility for known columns', function () {
    $state = TableState::fromRequest(
        stateRequest(['columns' => ['score' => '0', 'unknown' => '0']]),
        'topics',
        null,
        25,
        [25],
        ['name' => true, 'score' => true],
    );

    expect($state->columns)->toBe(['name' => true, 'score' => false])
        ->and($state->columns)->not->toHaveKey('unknown');
});

it('does not read another table namespace', function () {
    $request = Request::create('/topics', 'GET', [
        'table' => ['other' => ['search' => 'leaked']],
    ]);

    $state = TableState::fromRequest($request, 'topics', null, 25, [25]);

    expect($state->search)->toBe('');
});

it('reads nested pinned columns and falls back to declared defaults', function () {
    $defaults = ['left' => ['id'], 'right' => ['__actions']];
    $defaultState = TableState::fromRequest(
        stateRequest(),
        'topics',
        null,
        25,
        [25],
        [],
        $defaults,
    );
    $requested = TableState::fromRequest(
        stateRequest(['pinnedColumns' => [
            'left' => ['name'],
            'right' => [],
        ]]),
        'topics',
        null,
        25,
        [25],
        [],
        $defaults,
    );

    expect($defaultState->pinnedColumns)->toBe($defaults)
        ->and($requested->pinnedColumns)->toBe([
            'left' => ['name'],
            'right' => [],
        ]);
});

it('parses an isolated saved view identifier', function () {
    $state = TableState::fromRequest(
        stateRequest(['view' => 'view-7']),
        'topics',
        null,
        25,
        [25],
    );

    expect($state->view)->toBe('view-7')
        ->and($state->withView(8)->view)->toBe(8)
        ->and($state->view)->toBe('view-7');
});

it('exposes immutable with* helpers that do not mutate the original state', function () {
    $original = TableState::fromRequest(stateRequest(), 'topics', null, 25, [25]);

    $sorted = $original->withSort('-name');
    $searched = $original->withSearch('amm');

    expect($original->sort)->toBeNull()
        ->and($sorted->sort)->toBe('-name')
        ->and($original->search)->toBe('')
        ->and($searched->search)->toBe('amm');
});
