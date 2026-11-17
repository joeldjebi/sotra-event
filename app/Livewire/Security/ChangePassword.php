<?php

namespace App\Livewire\Security;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Changement de mot de passe : obligatoire après un mot de passe initial donné par l'administrateur,
 * possible à tout moment depuis « Sécurité du compte ».
 */
class ChangePassword extends Component
{
    use PasswordValidationRules;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function save(): Redirector|RedirectResponse|null
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => [...$this->passwordRules(), 'different:current_password'],
        ], [
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.different' => 'Choisissez un mot de passe différent de l’actuel.',
        ], ['current_password' => 'mot de passe actuel', 'password' => 'nouveau mot de passe']);

        $user = $this->user();
        $wasForced = $user->must_change_password;
        $user->forceFill(['password' => Hash::make($this->password), 'must_change_password' => false])->save();
        AuditLog::record('password_changed', $user);

        $this->reset(['current_password', 'password', 'password_confirmation']);

        if ($wasForced) {
            return $this->redirectRoute('space');
        }

        $this->dispatch('notify', message: 'Mot de passe changé.');

        return null;
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function render(): View
    {
        return view('livewire.security.change-password', ['forced' => $this->user()->must_change_password]);
    }
}
