<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Relevé de reversement PDF et justificatif de paiement (AD-11, CO-05).
 * Pour un utilisateur compagnie, la liaison de route applique le cloisonnement (une autre compagnie obtient 404).
 */
class PayoutDocumentController extends Controller
{
    public function statement(Payout $payout): Response
    {
        return Pdf::loadView('pdf.payout-statement', ['payout' => $payout->load(['event.venue', 'company'])])
            ->download('releve-'.$payout->reference.'.pdf');
    }

    public function proof(Payout $payout): StreamedResponse
    {
        abort_if($payout->proof_path === null, 404);

        return Storage::disk('local')->download($payout->proof_path, 'justificatif-'.$payout->reference.'.'.pathinfo($payout->proof_path, PATHINFO_EXTENSION));
    }
}
