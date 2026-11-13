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

    protected function notify(string $message, string $tone = 'success'): void
    {
        $this->dispatch('notify', message: $message, tone: $tone);
    }
}
