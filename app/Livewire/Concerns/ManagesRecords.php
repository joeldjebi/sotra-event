<?php

namespace App\Livewire\Concerns;

use App\Enums\Permission;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Comportements communs des écrans de gestion : recherche, pagination, feuille de formulaire,
 * contrôle de permission (au montage et à chaque action) et notifications.
 */
trait ManagesRecords
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    abstract protected function permission(): Permission;

    public function bootManagesRecords(): void
    {
        Gate::authorize($this->permission()->value);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    protected function notify(string $message, string $tone = 'success'): void
    {
        $this->dispatch('notify', message: $message, tone: $tone);
    }

    protected function searchTerm(): string
    {
        return '%'.mb_strtolower(trim($this->search)).'%';
    }
}
