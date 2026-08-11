<?php

namespace Toolbelt\InertiaTable;

use DateTimeInterface;
use Illuminate\Support\Facades\URL as UrlGenerator;
use Illuminate\Support\Traits\Conditionable;

final class Url
{
    use Conditionable;

    private ?string $url = null;

    private bool $preserveScroll = true;

    private bool $preserveState = true;

    private bool $newTab = false;

    private bool $download = false;

    private bool $disabled = false;

    private bool $hidden = false;

    public static function make(): static
    {
        return new self;
    }

    public function to(?string $url): static
    {
        $this->url = filled($url) ? $url : null;

        return $this;
    }

    public function route(string $name, mixed ...$parameters): static
    {
        return $this->to(route($name, $parameters));
    }

    public function signedRoute(string $name, mixed ...$parameters): static
    {
        return $this->to(UrlGenerator::signedRoute($name, $parameters));
    }

    public function temporarySignedRoute(string $name, DateTimeInterface $expiration, mixed ...$parameters): static
    {
        return $this->to(UrlGenerator::temporarySignedRoute($name, $expiration, $parameters));
    }

    public function preserveScroll(bool $preserve = true): static
    {
        $this->preserveScroll = $preserve;

        return $this;
    }

    public function preserveState(bool $preserve = true): static
    {
        $this->preserveState = $preserve;

        return $this;
    }

    public function openInNewTab(bool $open = true): static
    {
        $this->newTab = $open;

        return $this;
    }

    public function asDownload(bool $download = true): static
    {
        $this->download = $download;

        return $this;
    }

    public function disabled(bool $disabled = true): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function hidden(bool $hidden = true): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    /** @return array<string, bool|string> */
    public function toArray(): array
    {
        return [
            'url' => $this->url ?? '',
            'preserveScroll' => $this->preserveScroll,
            'preserveState' => $this->preserveState,
            'newTab' => $this->newTab,
            'download' => $this->download,
            'disabled' => $this->disabled,
        ];
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function hasUrl(): bool
    {
        return $this->url !== null;
    }
}
