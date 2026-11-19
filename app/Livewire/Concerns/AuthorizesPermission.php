<?php

namespace App\Livewire\Concerns;

use App\Enums\Permission;
use Illuminate\Support\Facades\Gate;

/**
 * Vérifie une permission au montage et à chaque requête Livewire.
 */
trait AuthorizesPermission
{
    abstract protected function permission(): Permission;

    public function bootAuthorizesPermission(): void
    {
        Gate::authorize($this->permission()->value);
    }

    /**
     * Permission requise pour agir, quand l'écran s'ouvre aussi en consultation seule (null : la même).
     */
    protected function managePermission(): ?Permission
    {
        return null;
    }

    public function canManage(): bool
    {
        $permission = $this->managePermission();

        return $permission === null || Gate::allows($permission->value);
    }

    protected function authorizeManage(): void
    {
        $permission = $this->managePermission();

        if ($permission !== null) {
            Gate::authorize($permission->value);
        }
    }

    protected function notify(string $message, string $tone = 'success'): void
    {
        $this->dispatch('notify', message: $message, tone: $tone);
    }
}
