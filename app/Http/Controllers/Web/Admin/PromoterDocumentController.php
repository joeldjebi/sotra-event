<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promoter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Justificatif d'un dossier promoteur, ouvert par ACCES EVENT depuis le disque privé.
 */
class PromoterDocumentController extends Controller
{
    public function __invoke(Promoter $promoter, string $document): StreamedResponse
    {
        $path = $promoter->{Promoter::DOCUMENTS[$document]['column'] ?? abort(404)};
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        $filename = Str::slug($promoter->name.' '.Promoter::DOCUMENTS[$document]['label']).'.'.pathinfo($path, PATHINFO_EXTENSION);

        return Storage::disk('local')->response($path, $filename);
    }
}
