<?php

namespace App\Actions\Promoters;

use App\Enums\Permission;
use App\Enums\PromoterStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Promoter;
use App\Models\User;
use App\Notifications\PromoterApplicationReceived;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Inscription en ligne d'un promoteur, après vérification de son téléphone par code SMS.
 *
 * Le promoteur est créé « en attente de validation » avec le compte de son responsable, qui devient
 * administrateur du promoteur. ACCES EVENT est prévenu et valide ou refuse la demande dans la console.
 */
class RegisterPromoter
{
    public function __construct(private readonly AttachPromoterDocument $attachDocument) {}

    /**
     * @param  array{name: string, email: string|null, phone: string, address: string|null, rccm_number: string|null, account_name: string, account_email: string, password: string}  $data
     * @param  array<string, UploadedFile|null>  $documents  justificatifs facultatifs, par clé de Promoter::DOCUMENTS
     */
    public function handle(array $data, array $documents = []): Promoter
    {
        $promoter = DB::transaction(function () use ($data): Promoter {
            $promoter = Promoter::query()->create([
                'name' => trim($data['name']),
                'email' => filled($data['email']) ? Str::lower(trim($data['email'])) : null,
                'phone' => $data['phone'],
                'address' => filled($data['address']) ? trim($data['address']) : null,
                'rccm_number' => filled($data['rccm_number']) ? trim($data['rccm_number']) : null,
                'status' => PromoterStatus::Pending,
                'applied_at' => now(),
                'phone_verified_at' => now(),
            ]);

            $user = User::query()->create([
                'name' => trim($data['account_name']),
                'email' => Str::lower(trim($data['account_email'])),
                'phone' => $data['phone'],
                'promoter_id' => $promoter->id,
                'is_active' => true,
                'password' => Hash::make($data['password']),
            ]);
            $user->assignRole(Role::PromoterAdmin->value);

            AuditLog::record('promoter_application_submitted', $promoter);

            return $promoter;
        });

        foreach ($documents as $document => $file) {
            if ($file instanceof UploadedFile) {
                $this->attachDocument->handle($promoter, $document, $file);
            }
        }

        $reviewers = User::query()->where('is_active', true)->permission(Permission::PromotersManage->value)->get();
        Notification::send($reviewers, new PromoterApplicationReceived($promoter->refresh()));

        return $promoter;
    }
}
