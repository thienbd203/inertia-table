<?php

use Illuminate\Support\Facades\Route;
use Musing\InertiaTable\Url;

it('has no url by default', function () {
    $url = Url::make();

    expect($url->hasUrl())->toBeFalse()
        ->and($url->toArray()['url'])->toBe('');
});

it('treats an empty or null url as no url', function () {
    expect(Url::make()->to(null)->hasUrl())->toBeFalse()
        ->and(Url::make()->to('')->hasUrl())->toBeFalse()
        ->and(Url::make()->to('/topics')->hasUrl())->toBeTrue();
});

it('resolves a named route to an absolute url', function () {
    Route::get('/topics/{topic}', fn () => null)->name('topics.show');

    $url = Url::make()->route('topics.show', 1);

    expect($url->toArray()['url'])->toBe(route('topics.show', 1));
});

it('serializes navigation options', function () {
    $url = Url::make()
        ->to('/topics/1')
        ->openInNewTab()
        ->asDownload()
        ->preserveScroll(false)
        ->preserveState(false)
        ->disabled();

    expect($url->toArray())->toBe([
        'url' => '/topics/1',
        'preserveScroll' => false,
        'preserveState' => false,
        'newTab' => true,
        'download' => true,
        'disabled' => true,
    ]);
});

it('defaults to preserving scroll and state', function () {
    $url = Url::make()->to('/topics/1');

    expect($url->toArray())
        ->preserveScroll->toBeTrue()
        ->preserveState->toBeTrue()
        ->newTab->toBeFalse()
        ->download->toBeFalse()
        ->disabled->toBeFalse();
});

it('tracks hidden state separately from the serialized array', function () {
    $url = Url::make()->to('/topics/1')->hidden();

    expect($url->isHidden())->toBeTrue()
        ->and($url->toArray())->not->toHaveKey('hidden');
});
