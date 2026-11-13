<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Enregistre le thème choisi par l'utilisateur : system (suit l'appareil), light ou dark.
 */
class ThemePreferenceController extends Controller
{
    public const THEMES = ['system', 'light', 'dark'];

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['theme' => ['required', Rule::in(self::THEMES)]]);

        /** @var User $user */
        $user = $request->user();
        $user->forceFill(['theme' => $validated['theme']])->saveQuietly();

        return response()->json(['theme' => $user->theme]);
    }
}
