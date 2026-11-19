<?php

namespace App\Livewire\Admin;

use App\Actions\Promoters\AttachPromoterDocument;
use App\Actions\Promoters\ReviewPromoter;
use App\Enums\Permission;
use App\Enums\PromoterStatus;
use App\Enums\Role;
use App\Livewire\Concerns\IssuesInitialPasswords;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\Promoter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Console ACCES EVENT · Promoteurs (AD-09).
 *
 * Un promoteur s'inscrit en ligne (en attente de validation) ou il est créé ici (validé d'office), avec le compte
 * de son administrateur et un mot de passe initial. ACCES EVENT examine le dossier (RCCM, justificatifs
 * facultatifs : « dossier incomplet » s'ils manquent), puis valide, refuse, suspend ou réactive le promoteur.
 */
class PromotersTable extends Component
{
    use IssuesInitialPasswords, ManagesRecords, WithFileUploads;

    #[Url(as: 'statut', except: '')]
    public string $statusFilter = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $rccm_number = '';

    public ?TemporaryUploadedFile $rccm_document = null;

    public ?TemporaryUploadedFile $identity_document = null;

    /** Créer en même temps le compte de connexion de l'administrateur du promoteur. */
    public bool $createAccount = true;

    public string $account_name = '';

    public string $account_email = '';

    public string $account_password = '';

    /** Promoteur examiné dans la feuille de décision. */
    public ?int $reviewingId = null;

    public bool $showReview = false;

    public string $decisionNote = '';

    protected function permission(): Permission
    {
        return Permission::PromotersManage;
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['name', 'email', 'phone', 'address', 'rccm_number', 'rccm_document', 'identity_document', 'editingId', 'account_name', 'account_email', 'account_password']);
        $this->createAccount = true;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $promoterId): void
    {
        $promoter = Promoter::query()->findOrFail($promoterId);
        $this->resetValidation();
        $this->editingId = $promoter->id;
        $this->name = $promoter->name;
        $this->email = (string) $promoter->email;
        $this->phone = (string) $promoter->phone;
        $this->address = (string) $promoter->address;
        $this->rccm_number = (string) $promoter->rccm_number;
        $this->reset(['account_name', 'account_email', 'account_password', 'rccm_document', 'identity_document']);
        // En modification, le compte n'est proposé que si le promoteur n'en a encore aucun.
        $this->createAccount = false;
        $this->showReview = false;
        $this->showForm = true;
    }

    public function save(AttachPromoterDocument $attachDocument): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('promoters', 'name')->ignore($this->editingId)],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'rccm_number' => ['nullable', 'string', 'max:60'],
        ], attributes: ['name' => 'nom', 'rccm_number' => 'numéro RCCM']);

        $this->validate([
            'rccm_document' => AttachPromoterDocument::RULES,
            'identity_document' => AttachPromoterDocument::RULES,
        ], attributes: ['rccm_document' => 'extrait RCCM', 'identity_document' => 'pièce d\'identité']);

        if ($this->createAccount) {
            $this->validate([
                'account_name' => ['required', 'string', 'max:120'],
                'account_email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')],
                'account_password' => ['required', 'string', PasswordRule::default()],
            ], attributes: ['account_name' => 'nom de l\'administrateur', 'account_email' => 'e-mail de connexion', 'account_password' => 'mot de passe']);
        }

        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);

        $promoter = DB::transaction(function () use ($data): Promoter {
            $promoter = $this->editingId === null
                // Créé par ACCES EVENT : validé d'office.
                ? Promoter::query()->create($data + ['status' => PromoterStatus::Approved, 'reviewed_at' => now(), 'reviewed_by' => auth()->id()])
                : tap(Promoter::query()->findOrFail($this->editingId))->update($data);

            if ($this->createAccount) {
                $user = User::query()->create([
                    'name' => trim($this->account_name),
                    'email' => Str::lower(trim($this->account_email)),
                    'promoter_id' => $promoter->id,
                    'is_active' => true,
                    'password' => Str::password(40),
                ]);
                $user->assignRole(Role::PromoterAdmin->value);
                $this->issueCredentials($user, $this->account_password);
            }

            return $promoter;
        });

        foreach (['rccm' => $this->rccm_document, 'identity' => $this->identity_document] as $document => $file) {
            if ($file !== null) {
                $attachDocument->handle($promoter, $document, $file);
            }
        }

        $this->showForm = false;
        $this->notify(match (true) {
            $this->createAccount && $this->editingId === null => "{$promoter->name} ajouté, avec son compte de connexion.",
            $this->createAccount => "Compte de connexion créé pour {$promoter->name}.",
            $this->editingId !== null => "{$promoter->name} mis à jour.",
            default => "{$promoter->name} ajouté.",
        });
        $this->reset(['account_password', 'rccm_document', 'identity_document']);
    }

    /**
     * Ouvre la feuille d'examen : dossier, justificatifs et décisions possibles.
     */
    public function review(int $promoterId): void
    {
        $this->reviewingId = Promoter::query()->findOrFail($promoterId)->id;
        $this->decisionNote = '';
        $this->resetValidation();
        $this->showForm = false;
        $this->showReview = true;
    }

    public function approve(ReviewPromoter $review): void
    {
        $promoter = $this->reviewed();
        $review->approve($promoter);
        $this->closeReview("{$promoter->name} validé ; il est prévenu par SMS.");
    }

    public function reject(ReviewPromoter $review): void
    {
        $this->validateNote();
        $promoter = $this->reviewed();
        $review->reject($promoter, trim($this->decisionNote));
        $this->closeReview("Demande de {$promoter->name} refusée.");
    }

    public function suspend(ReviewPromoter $review): void
    {
        $this->validateNote();
        $promoter = $this->reviewed();
        $review->suspend($promoter, trim($this->decisionNote));
        $this->closeReview("{$promoter->name} suspendu : son équipe n'a plus accès à l'espace promoteur.");
    }

    public function reactivate(ReviewPromoter $review): void
    {
        $promoter = $this->reviewed();
        $review->reactivate($promoter);
        $this->closeReview("{$promoter->name} réactivé.");
    }

    private function validateNote(): void
    {
        $this->validate(['decisionNote' => ['required', 'string', 'max:500']], [
            'decisionNote.required' => 'Indiquez le motif : il est envoyé au promoteur.',
        ]);
    }

    private function reviewed(): Promoter
    {
        return Promoter::query()->findOrFail($this->reviewingId);
    }

    private function closeReview(string $message): void
    {
        $this->showReview = false;
        $this->reviewingId = null;
        $this->notify($message);
    }

    /**
     * Reprend l'e-mail du promoteur pour le compte, si l'administrateur ne l'a pas déjà saisi.
     */
    public function updatedEmail(string $value): void
    {
        if ($this->createAccount && $this->account_email === '') {
            $this->account_email = $value;
        }
    }

    protected function passwordFields(): array
    {
        return ['account_password'];
    }

    public function render(): View
    {
        $status = PromoterStatus::tryFrom($this->statusFilter);

        return view('livewire.admin.promoters-table', [
            'editingHasAccount' => $this->editingId !== null
                && User::query()->where('promoter_id', $this->editingId)->exists(),
            'promoters' => Promoter::query()
                ->with(['users' => fn ($query) => $query->with('roles')->orderBy('name')])
                ->when($status !== null, fn ($query) => $query->where('status', $status))
                ->when($this->search !== '', fn ($query) => $query->whereRaw('lower(name) like ?', [$this->searchTerm()]))
                // Les demandes en attente d'abord, puis par nom.
                ->orderByRaw('case when status = ? then 0 else 1 end', [PromoterStatus::Pending->value])
                ->orderBy('name')
                ->paginate(15),
            'pendingCount' => Promoter::query()->where('status', PromoterStatus::Pending)->count(),
            'statuses' => PromoterStatus::options(),
            'reviewing' => $this->reviewingId === null ? null : Promoter::query()->with(['users.roles', 'reviewer'])->withCount('eventRequests')->find($this->reviewingId),
        ]);
    }
}
