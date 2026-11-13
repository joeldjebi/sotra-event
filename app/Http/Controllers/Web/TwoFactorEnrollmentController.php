<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Activation de la double authentification (obligatoire pour les espaces web, NF-03).
 * Les actions (activer, confirmer, régénérer) passent par les routes Fortify.
 */
class TwoFactorEnrollmentController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $step = match (true) {
            $user->two_factor_secret === null => 'start',
            $user->two_factor_confirmed_at === null => 'scan',
            default => 'done',
        };

        return view('security.two-factor', [
            'user' => $user,
            'step' => $step,
            'qrCode' => $step === 'scan' ? $user->twoFactorQrCodeSvg() : null,
            'secretKey' => $step === 'scan' ? decrypt((string) $user->two_factor_secret) : null,
            'recoveryCodes' => $step === 'done' ? $user->recoveryCodes() : [],
            'justConfirmed' => session('status') === 'two-factor-authentication-confirmed',
        ]);
    }
}
