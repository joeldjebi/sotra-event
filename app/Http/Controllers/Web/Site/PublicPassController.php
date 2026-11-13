<?php

namespace App\Http\Controllers\Web\Site;

use App\Http\Controllers\Controller;
use App\Models\Pass;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pass ouvert depuis le lien signé reçu par SMS ou WhatsApp (WA-04), sans connexion.
 * Le lien porte la version du Pass : après un transfert, l'ancien lien ne fonctionne plus (RG-04).
 */
class PublicPassController extends Controller
{
    public function __invoke(Request $request, Pass $pass): View
    {
        abort_unless($request->integer('v') === $pass->token_version, 410, 'Ce lien n\'est plus valable : le Pass a été offert à quelqu\'un d\'autre.');

        return view('site.public-pass', ['pass' => $pass->load(['event.venue', 'event.dropZones', 'boardingPoint', 'holder'])]);
    }
}
