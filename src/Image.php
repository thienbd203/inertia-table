<?php

namespace Toolbelt\InertiaTable;

use Illuminate\Support\Facades\URL;

class Image
{
    /** @var array<int, string> */
    private array $urls = [];

    private ?string $icon = null;

    private ImageSize $size = ImageSize::Medium;

    private ImagePosition $position = ImagePosition::Start;

    private bool $rounded = false;

    private ?int $width = null;

    private ?int $height = null;

    private ?int $limit = null;

    private ?string $class = null;

    private ?string $alt = null;

    private ?string $title = null;

    public function url(string|array|null $url): static
    {
        $this->urls = array_values(array_filter((array) $url, fn (mixed $value) => is_string($value) && $value !== ''));

        return $this;
    }

    public function to(string|array|null $url): static
    {
        return $this->url($url);
    }

    public function route(string $name, mixed ...$parameters): static
    {
        return $this->url(route($name, $parameters));
    }

    public function signedRoute(string $name, mixed ...$parameters): static
    {
        return $this->url(URL::signedRoute($name, $parameters));
    }

    public function temporarySignedRoute(string $name, \DateTimeInterface $expiration, mixed ...$parameters): static
    {
        return $this->url(URL::temporarySignedRoute($name, $expiration, $parameters));
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function size(ImageSize $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function small(): static
    {
        return $this->size(ImageSize::Small);
    }

    public function medium(): static
    {
        return $this->size(ImageSize::Medium);
    }

    public function large(): static
    {
        return $this->size(ImageSize::Large);
    }

    public function extraLarge(): static
    {
        return $this->size(ImageSize::ExtraLarge);
    }

    public function position(ImagePosition $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function start(): static
    {
        return $this->position(ImagePosition::Start);
    }

    public function end(): static
    {
        return $this->position(ImagePosition::End);
    }

    public function rounded(bool $rounded = true): static
    {
        $this->rounded = $rounded;

        return $this;
    }

    public function width(int $width): static
    {
        $this->width = $width > 0 ? $width : null;

        return $this;
    }

    public function height(int $height): static
    {
        $this->height = $height > 0 ? $height : null;

        return $this;
    }

    public function dimensions(int $width, int $height): static
    {
        return $this->width($width)->height($height);
    }

    public function limit(?int $limit): static
    {
        $this->limit = $limit !== null && $limit > 0 ? $limit : null;

        return $this;
    }

    public function class(?string $class): static
    {
        $this->class = $class;

        return $this;
    }

    public function alt(?string $alt): static
    {
        $this->alt = $alt;

        return $this;
    }

    public function title(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $visible = $this->limit === null ? $this->urls : array_slice($this->urls, 0, $this->limit);

        return [
            'urls' => $visible,
            'overflow' => count($this->urls) - count($visible),
            'icon' => $this->icon,
            'size' => $this->size->value,
            'position' => $this->position->value,
            'rounded' => $this->rounded,
            'width' => $this->width,
            'height' => $this->height,
            'class' => $this->class,
            'alt' => $this->alt,
            'title' => $this->title,
        ];
    }
}
