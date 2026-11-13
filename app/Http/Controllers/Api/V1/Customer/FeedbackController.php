<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\ComplaintCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ComplaintResource;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\Pass;
use App\Models\Review;
use App\Services\Quality\ComplaintService;
use App\Services\Quality\ReviewService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Avis après le trajet (SP-22) et réclamations (SP-23).
 */
#[Group('Avis et réclamations', 'App spectateur · avis après le trajet et réclamations, avec leur suivi.', weight: 5)]
class FeedbackController extends Controller
{
    /**
     * Donner son avis : une note de 1 à 5 par critère, et un commentaire facultatif. Un avis par Pass,
     * possible dès qu'un trajet a été scanné.
     */
    public function review(Request $request, Pass $pass, ReviewService $reviews): JsonResponse
    {
        $this->ensureHolder($request, $pass);
        $rating = ['required', 'integer', 'between:1,5'];
        $validated = $request->validate(array_fill_keys(array_keys(Review::CRITERIA), $rating) + ['comment' => ['nullable', 'string', 'max:1000']]);

        /** @var array{punctuality: int, comfort: int, courtesy: int, driving: int, information: int} $ratings */
        $ratings = array_map('intval', array_intersect_key($validated, Review::CRITERIA));
        $review = $reviews->submit($pass, $ratings, $validated['comment'] ?? null);

        return response()->json(['data' => ['id' => $review->id, 'average' => $review->average()], 'message' => 'Merci pour votre avis !'], 201);
    }

    /**
     * Déposer une réclamation liée à un Pass (ex. « bus retour parti sans moi »).
     */
    public function complain(Request $request, Pass $pass, ComplaintService $complaints): JsonResponse
    {
        $this->ensureHolder($request, $pass);
        $validated = $request->validate([
            'category' => ['required', Rule::enum(ComplaintCategory::class)],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $complaint = $complaints->open($pass, ComplaintCategory::from($validated['category']), $validated['details'] ?? null);

        return (new ComplaintResource($complaint->load('pass')))->response()->setStatusCode(201);
    }

    /**
     * Mes réclamations et leur statut (Ouverte, En cours, Traitée).
     */
    public function complaints(Request $request): AnonymousResourceCollection
    {
        return ComplaintResource::collection(
            Complaint::query()->withoutGlobalScopes()->with('pass')->where('customer_id', $this->customer($request)->id)->latest('id')->get()
        );
    }

    private function ensureHolder(Request $request, Pass $pass): void
    {
        abort_unless($pass->holder_id === $this->customer($request)->id, 404);
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user('sanctum');
        abort_unless($customer instanceof Customer, 403);

        return $customer;
    }
}
