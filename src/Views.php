<?php

namespace Musing\InertiaTable;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use LogicException;
use Musing\InertiaTable\Support\TableReference;

final class Views
{
    private bool $scopeUser;

    private ?Closure $userResolver;

    private bool $scopeTableName;

    /** @var array<string, mixed>|Closure */
    private array|Closure $scopeAttributes;

    /** @var class-string<TableView> */
    private string $viewModel;

    private bool $persistSearch;

    /** @var array<string, bool|Closure> */
    private array $authorizers = [];

    /**
     * @param  array<string, mixed>|Closure  $attributes
     * @param  class-string<TableView>  $modelClass
     */
    private function __construct(
        bool $scopeUser = true,
        ?Closure $userResolver = null,
        array|Closure $attributes = [],
        bool $scopeTableName = false,
        string $modelClass = TableView::class,
        bool $includeSearch = false,
    ) {
        $this->scopeUser = $scopeUser;
        $this->userResolver = $userResolver;
        $this->scopeAttributes = $attributes;
        $this->scopeTableName = $scopeTableName;
        $this->viewModel = $this->validateModelClass($modelClass);
        $this->persistSearch = $includeSearch;
    }

    /**
     * @param  array<string, mixed>|Closure  $attributes
     * @param  class-string<TableView>  $modelClass
     */
    public static function make(
        bool $scopeUser = true,
        ?Closure $userResolver = null,
        array|Closure $attributes = [],
        bool $scopeTableName = false,
        string $modelClass = TableView::class,
        bool $includeSearch = false,
    ): self {
        return new self(
            $scopeUser,
            $userResolver,
            $attributes,
            $scopeTableName,
            $modelClass,
            $includeSearch,
        );
    }

    public function scopeUser(bool $scope = true): self
    {
        $this->scopeUser = $scope;

        return $this;
    }

    public function userResolver(?Closure $resolver): self
    {
        $this->userResolver = $resolver;

        return $this;
    }

    /** @param array<string, mixed>|Closure $attributes */
    public function attributes(array|Closure $attributes): self
    {
        $this->scopeAttributes = $attributes;

        return $this;
    }

    public function scopeTableName(bool $scope = true): self
    {
        $this->scopeTableName = $scope;

        return $this;
    }

    /** @param class-string<TableView> $modelClass */
    public function modelClass(string $modelClass): self
    {
        $this->viewModel = $this->validateModelClass($modelClass);

        return $this;
    }

    public function includeSearch(bool $include = true): self
    {
        $this->persistSearch = $include;

        return $this;
    }

    public function authorizeCreate(bool|Closure $authorizer = true): self
    {
        return $this->setAuthorizer('create', $authorizer);
    }

    public function authorizeUpdate(bool|Closure $authorizer = true): self
    {
        return $this->setAuthorizer('update', $authorizer);
    }

    public function authorizeDelete(bool|Closure $authorizer = true): self
    {
        return $this->setAuthorizer('delete', $authorizer);
    }

    public function authorizeShare(bool|Closure $authorizer = true): self
    {
        return $this->setAuthorizer('share', $authorizer);
    }

    public function authorizeDefault(bool|Closure $authorizer = true): self
    {
        return $this->setAuthorizer('default', $authorizer);
    }

    /**
     * @return array{
     *     state: array<string, mixed>,
     *     resource: array<string, mixed>
     * }
     */
    public function resolve(Table $table, Request $request): array
    {
        $models = $this->visibleQuery($table, $request)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
        $requestedId = data_get(
            $request->query(),
            "table.{$table->name()}.view",
        );
        $selected = is_scalar($requestedId)
            ? $models->first(fn (TableView $view) => (string) $view->getKey() === (string) $requestedId)
            : null;
        $scopeHash = $this->scopeHash($table, $request);
        $selected ??= $models->first(
            fn (TableView $view) => $view->is_default && hash_equals($scopeHash, $view->scope_hash),
        );
        $items = $models->map(
            fn (TableView $view) => $this->serializeView($view, $table, $request, $scopeHash),
        )->values()->all();
        $selectedItem = $selected instanceof TableView
            ? collect($items)->first(fn (array $item) => (string) $item['id'] === (string) $selected->getKey())
            : null;

        return [
            'state' => is_array($selectedItem) ? $selectedItem['state'] : [],
            'resource' => [
                'items' => $items,
                'selected' => $selected?->getKey(),
                'includeSearch' => $this->persistSearch,
                'canCreate' => $this->authorized('create', $request, $table),
                'storeEndpoint' => $this->authorized('create', $request, $table)
                    ? $this->signedRoute('inertia-table.views.store', $table)
                    : null,
            ],
        ];
    }

    /** @return Builder<TableView> */
    public function visibleQuery(Table $table, Request $request): Builder
    {
        $query = $this->newQuery()
            ->where('context_hash', $this->contextHash($table, $request));

        if (! $this->scopeUser) {
            return $query->whereNull('user_id');
        }

        $userId = $this->userId($request, $table);

        if ($userId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($userId) {
            $query->where('user_id', $userId)->orWhere('is_shared', true);
        });
    }

    public function findVisible(Table $table, Request $request, int|string $id): ?TableView
    {
        return $this->visibleQuery($table, $request)->whereKey($id)->first();
    }

    /** @return array<string, mixed> */
    public function valuesFor(Table $table, Request $request, string $name, array $state): array
    {
        $userId = $this->scopeUser ? $this->userId($request, $table) : null;

        if ($this->scopeUser && $userId === null) {
            throw ValidationException::withMessages([
                'view' => 'A user is required to save table views.',
            ]);
        }

        return [
            'table_key' => $table::class,
            'table_name' => $this->scopeTableName ? $table->name() : null,
            'user_id' => $userId,
            'name' => $name,
            'state' => $table->normalizeViewState($state, $this->persistSearch),
            'attributes' => $this->resolvedAttributes($request, $table),
            'context_hash' => $this->contextHash($table, $request),
            'scope_hash' => $this->scopeHash($table, $request),
            'is_shared' => ! $this->scopeUser,
            'is_default' => false,
            'lock_version' => 0,
        ];
    }

    public function normalizeState(Table $table, array $state): array
    {
        return $table->normalizeViewState($state, $this->persistSearch);
    }

    public function authorized(
        string $operation,
        Request $request,
        Table $table,
        ?TableView $view = null,
    ): bool {
        $authorizer = $this->authorizers[$operation] ?? null;

        if ($authorizer instanceof Closure) {
            return (bool) app()->call($authorizer, compact('request', 'table', 'view', 'operation'));
        }

        if (is_bool($authorizer)) {
            return $authorizer;
        }

        if ($operation === 'create') {
            return ! $this->scopeUser || $this->userId($request, $table) !== null;
        }

        if (! $view instanceof TableView) {
            return false;
        }

        if (! $this->scopeUser) {
            return $view->user_id === null;
        }

        $userId = $this->userId($request, $table);
        $ownsView = $userId !== null && (string) $view->user_id === $userId;

        return $ownsView;
    }

    public function ensureAuthorized(
        string $operation,
        Request $request,
        Table $table,
        ?TableView $view = null,
    ): void {
        abort_unless($this->authorized($operation, $request, $table, $view), 403);
    }

    public function scopeHash(Table $table, Request $request): string
    {
        $user = $this->scopeUser ? $this->userId($request, $table) : null;

        return hash('sha256', $this->contextHash($table, $request).'|'.($user ?? '*'));
    }

    public function usesUserScope(): bool
    {
        return $this->scopeUser;
    }

    /** @return Builder<TableView> */
    public function newQuery(): Builder
    {
        $modelClass = $this->viewModel;

        return $modelClass::query();
    }

    private function setAuthorizer(string $operation, bool|Closure $authorizer): self
    {
        $this->authorizers[$operation] = $authorizer;

        return $this;
    }

    /** @return array<string, mixed> */
    private function serializeView(
        TableView $view,
        Table $table,
        Request $request,
        string $scopeHash,
    ): array {
        return [
            'id' => $view->getKey(),
            'name' => $view->name,
            'state' => $table->normalizeViewState($view->state, $this->persistSearch),
            'isDefault' => $view->is_default && hash_equals($scopeHash, $view->scope_hash),
            'isShared' => $view->is_shared,
            'version' => $view->lock_version,
            'canUpdate' => $this->authorized('update', $request, $table, $view),
            'canDelete' => $this->authorized('delete', $request, $table, $view),
            'canShare' => $this->scopeUser && $this->authorized('share', $request, $table, $view),
            'canDefault' => $this->authorized('default', $request, $table, $view),
            'endpoints' => [
                'update' => $this->authorized('update', $request, $table, $view)
                    ? $this->signedRoute('inertia-table.views.update', $table, $view)
                    : null,
                'delete' => $this->authorized('delete', $request, $table, $view)
                    ? $this->signedRoute('inertia-table.views.destroy', $table, $view)
                    : null,
                'default' => $this->authorized('default', $request, $table, $view)
                    ? $this->signedRoute('inertia-table.views.default', $table, $view)
                    : null,
                'share' => $this->scopeUser && $this->authorized('share', $request, $table, $view)
                    ? $this->signedRoute('inertia-table.views.share', $table, $view)
                    : null,
            ],
        ];
    }

    private function contextHash(Table $table, Request $request): string
    {
        return hash('sha256', json_encode([
            'table' => $table::class,
            'name' => $this->scopeTableName ? $table->name() : null,
            'attributes' => $this->resolvedAttributes($request, $table),
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function resolvedAttributes(Request $request, Table $table): array
    {
        $attributes = $this->scopeAttributes instanceof Closure
            ? app()->call($this->scopeAttributes, compact('request', 'table'))
            : $this->scopeAttributes;

        if (! is_array($attributes)) {
            throw new LogicException('Table view attributes must resolve to an array.');
        }

        return $this->sortRecursively($attributes);
    }

    private function userId(Request $request, Table $table): ?string
    {
        $user = $this->userResolver instanceof Closure
            ? app()->call($this->userResolver, compact('request', 'table'))
            : Auth::id();

        if ($user === null) {
            return null;
        }

        if (! is_int($user) && ! is_string($user)) {
            throw new LogicException('The table view user resolver must return an integer, string, or null.');
        }

        return (string) $user;
    }

    /** @return array<string, mixed> */
    private function sortRecursively(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->sortRecursively($value);
            }
        }

        if (! array_is_list($values)) {
            ksort($values);
        }

        return $values;
    }

    /** @param class-string<TableView> $modelClass */
    private function validateModelClass(string $modelClass): string
    {
        if ($modelClass !== TableView::class && ! is_subclass_of($modelClass, TableView::class)) {
            throw new LogicException('The table view model must extend '.TableView::class.'.');
        }

        return $modelClass;
    }

    private function signedRoute(string $name, Table $table, ?TableView $view = null): string
    {
        return url()->signedRoute($name, array_filter([
            'table' => TableReference::encode($table::class),
            'view' => $view?->getKey(),
        ], fn (mixed $value) => $value !== null), absolute: false);
    }
}
