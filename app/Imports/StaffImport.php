<?php

namespace App\Imports;

use App\Enums\StaffRole;
use App\Models\Staff;
use App\Support\PhoneNumber;

/**
 * Import du personnel : fonction, prenom, nom, telephone, matricule.
 * Un téléphone déjà connu dans la compagnie met la fiche à jour.
 */
class StaffImport extends RowImport
{
    public static function headings(): array
    {
        return ['fonction', 'prenom', 'nom', 'telephone', 'matricule'];
    }

    public static function example(): array
    {
        return ['Convoyeur', 'Moussa', 'Bamba', '07 00 00 00 01', 'SOT-0001'];
    }

    protected function rules(): array
    {
        return [
            'fonction' => ['required', 'string'],
            'prenom' => ['required', 'string', 'max:80'],
            'nom' => ['required', 'string', 'max:80'],
            'telephone' => ['required'],
            'matricule' => ['nullable'],
        ];
    }

    protected function check(array $row): ?string
    {
        if (self::matchOption($row['fonction'], StaffRole::options()) === null) {
            return "fonction « {$row['fonction']} » inconnue (Chauffeur, Convoyeur ou Technicien).";
        }

        if (! PhoneNumber::isValid(self::phoneInput($row))) {
            return "téléphone « {$row['telephone']} » invalide.";
        }

        $owner = Staff::query()->withoutGlobalScopes()
            ->where('phone', PhoneNumber::parse(self::phoneInput($row))->e164)
            ->value('company_id');

        if ($owner !== null && (int) $owner !== $this->companyId) {
            return 'ce téléphone est déjà utilisé par une autre compagnie.';
        }

        return null;
    }

    protected function persist(array $row): bool
    {
        $staff = Staff::query()->withoutGlobalScopes()->updateOrCreate(
            ['phone' => PhoneNumber::parse(self::phoneInput($row))->e164],
            [
                'company_id' => $this->companyId,
                'role' => self::matchOption($row['fonction'], StaffRole::options()),
                'first_name' => $row['prenom'],
                'last_name' => $row['nom'],
                'employee_number' => ($row['matricule'] ?? null) !== null && $row['matricule'] !== '' ? (string) $row['matricule'] : null,
            ],
        );

        return $staff->wasRecentlyCreated;
    }

    /**
     * Excel supprime le 0 initial des numéros saisis comme nombres (0700000001 → 700000001).
     *
     * @param  array<string, mixed>  $row
     */
    private static function phoneInput(array $row): string
    {
        $value = trim((string) $row['telephone']);

        return preg_match('/^\d{9}$/', $value) === 1 ? '0'.$value : $value;
    }
}
