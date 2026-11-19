<?php

namespace App\Livewire\Promoter;

use App\Actions\Promoters\AttachPromoterDocument;
use App\Models\Promoter;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Dossier du promoteur, vu par son équipe : statut de la demande (en attente, refusée, suspendue)
 * et complément du dossier (numéro RCCM, justificatifs). Seul l'administrateur du promoteur le complète.
 */
class Organisation extends Component
{
    use WithFileUploads;

    public string $rccm_number = '';

    public ?TemporaryUploadedFile $rccm_document = null;

    public ?TemporaryUploadedFile $identity_document = null;

    public function mount(): void
    {
        $this->rccm_number = (string) $this->promoter()->rccm_number;
    }

    public function save(AttachPromoterDocument $attachDocument): void
    {
        abort_unless($this->canEdit(), 403);

        $this->validate([
            'rccm_number' => ['nullable', 'string', 'max:60'],
            'rccm_document' => AttachPromoterDocument::RULES,
            'identity_document' => AttachPromoterDocument::RULES,
        ], attributes: ['rccm_number' => 'numéro RCCM', 'rccm_document' => 'extrait RCCM', 'identity_document' => 'pièce d\'identité']);

        $promoter = $this->promoter();
        $promoter->update(['rccm_number' => trim($this->rccm_number) ?: null]);

        foreach (['rccm' => $this->rccm_document, 'identity' => $this->identity_document] as $document => $file) {
            if ($file !== null) {
                $attachDocument->handle($promoter, $document, $file);
            }
        }

        $this->reset(['rccm_document', 'identity_document']);
        $this->dispatch('notify', message: 'Dossier mis à jour.', tone: 'success');
    }

    private function promoter(): Promoter
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->promoter !== null, 403);

        return $user->promoter;
    }

    private function canEdit(): bool
    {
        return auth()->user()?->can('promoter-team.manage') ?? false;
    }

    public function render(): View
    {
        return view('livewire.promoter.organisation', [
            'promoter' => $this->promoter()->refresh(),
            'canEdit' => $this->canEdit(),
        ]);
    }
}
