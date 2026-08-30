<?php

namespace Musing\InertiaTable\Exports;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Musing\InertiaTable\Contracts\ExportContext;
use Musing\InertiaTable\Table;

final class AuthenticatedExportContext implements ExportContext
{
    private ?Authenticatable $previousUser = null;

    public function actorId(Request $request, Table $table, Export $export): int|string|null
    {
        $id = $request->user()?->getAuthIdentifier() ?? Auth::id();

        if ($id !== null && ! is_int($id) && ! is_string($id)) {
            throw new LogicException('Queued export actor identifiers must be integers, strings, or null.');
        }

        return $id;
    }

    public function restore(int|string|null $actorId, array $attributes): void
    {
        $guard = Auth::guard();
        $this->previousUser = $guard->user();

        if ($actorId === null) {
            $guard->forgetUser();

            return;
        }

        $actor = $guard->getProvider()->retrieveById($actorId);

        if (! $actor instanceof Authenticatable) {
            throw new LogicException('The queued export actor no longer exists.');
        }

        $guard->setUser($actor);
    }

    public function release(): void
    {
        $guard = Auth::guard();

        if ($this->previousUser instanceof Authenticatable) {
            $guard->setUser($this->previousUser);
        } else {
            $guard->forgetUser();
        }
    }
}
