<?php

namespace Musing\InertiaTable\Exports;

use Closure;
use Illuminate\Http\Request;
use LogicException;
use Musing\InertiaTable\Table;

final class Export
{
    private bool|Closure $authorize;

    private string|Closure $label;

    private string|Closure $filename;

    private string $type;

    private ExportScope $scope = ExportScope::All;

    private bool $visibleColumnsOnly = false;

    /** @var array<string, mixed> */
    private array $meta = [];

    private function __construct(
        public readonly string $key,
        string|Closure $label,
        string|Closure $filename,
        string $type,
        bool|Closure $authorize,
    ) {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $key)) {
            throw new LogicException('Table export keys may contain only letters, numbers, dashes, and underscores.');
        }

        $this->label = $label;
        $this->filename = $filename;
        $this->type = $this->normalizeType($type);
        $this->authorize = $authorize;
    }

    public static function make(
        string $key = 'csv',
        string|Closure|null $label = null,
        string|Closure|null $filename = null,
        string $type = 'csv',
        bool|Closure $authorize = true,
    ): self {
        return new self(
            $key,
            $label ?? str($key)->headline()->append(' Export')->toString(),
            $filename ?? fn (Table $table) => $table->name().'.'.$type,
            $type,
            $authorize,
        );
    }

    public function label(string|Closure $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function filename(string|Closure $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $this->normalizeType($type);

        return $this;
    }

    public function authorize(bool|Closure $authorize = true): self
    {
        $this->authorize = $authorize;

        return $this;
    }

    public function allRows(): self
    {
        $this->scope = ExportScope::All;

        return $this;
    }

    public function filtered(): self
    {
        $this->scope = ExportScope::Filtered;

        return $this;
    }

    public function selected(): self
    {
        $this->scope = ExportScope::Selected;

        return $this;
    }

    public function visibleColumnsOnly(bool $visibleOnly = true): self
    {
        $this->visibleColumnsOnly = $visibleOnly;

        return $this;
    }

    /** @param array<string, mixed> $meta */
    public function meta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function isAuthorized(Request $request, Table $table): bool
    {
        return $this->authorize instanceof Closure
            ? (bool) app()->call($this->authorize, compact('request', 'table'))
            : $this->authorize;
    }

    public function resolvedLabel(Request $request, Table $table): string
    {
        $label = $this->label instanceof Closure
            ? app()->call($this->label, compact('request', 'table'))
            : $this->label;

        return trim((string) $label);
    }

    public function resolvedFilename(Request $request, Table $table): string
    {
        $filename = $this->filename instanceof Closure
            ? app()->call($this->filename, compact('request', 'table'))
            : $this->filename;
        $filename = basename(str_replace(["\0", "\r", "\n"], '', (string) $filename));

        if ($filename === '' || $filename === '.' || $filename === '..') {
            $filename = $table->name().'.'.$this->type;
        }

        return pathinfo($filename, PATHINFO_EXTENSION) === ''
            ? $filename.'.'.$this->type
            : $filename;
    }

    public function scope(): ExportScope
    {
        return $this->scope;
    }

    public function typeName(): string
    {
        return $this->type;
    }

    public function usesVisibleColumns(): bool
    {
        return $this->visibleColumnsOnly;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->meta;
    }

    /** @return array<string, mixed> */
    public function resolve(Request $request, Table $table, string $endpoint): array
    {
        return [
            'key' => $this->key,
            'label' => $this->resolvedLabel($request, $table),
            'filename' => $this->resolvedFilename($request, $table),
            'type' => $this->type,
            'scope' => $this->scope->value,
            'requiresSelection' => $this->scope === ExportScope::Selected,
            'endpoint' => $endpoint,
            'meta' => $this->meta,
        ];
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        if (! preg_match('/^[a-z0-9_-]+$/', $type)) {
            throw new LogicException('Table export types may contain only lowercase letters, numbers, dashes, and underscores.');
        }

        return $type;
    }
}
