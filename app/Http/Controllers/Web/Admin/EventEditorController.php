<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Configuration d'un événement en 4 étapes (AD-01 à AD-04), avec le panneau de statut à droite.
 */
class EventEditorController extends Controller
{
    public const STEPS = [
        1 => 'Informations',
        2 => 'Points d\'embarquement',
        3 => 'Grille tarifaire',
        4 => 'Canaux & publication',
    ];

    public function create(): View
    {
        return view('admin.events.edit', ['event' => null, 'step' => 1, 'steps' => self::STEPS]);
    }

    public function edit(Request $request, Event $event): View
    {
        $step = (int) $request->query('etape', 1);

        return view('admin.events.edit', [
            'event' => $event,
            'step' => array_key_exists($step, self::STEPS) ? $step : 1,
            'steps' => self::STEPS,
        ]);
    }

    /**
     * Annulation / report (AD-13).
     */
    public function disruption(Event $event): View
    {
        return view('admin.events.disruption', ['event' => $event]);
    }
}
