<?php

namespace App\Services\Operations;

use App\Enums\BusStatus;
use App\Enums\DeviceStatus;
use App\Enums\StaffRole;
use App\Models\Bus;
use App\Models\Device;
use App\Models\Mission;
use App\Models\Staff;
use Illuminate\Validation\ValidationException;

/**
 * Affectation d'un bus, d'un chauffeur, d'un convoyeur et d'un terminal à une mission.
 *
 * Contrôles : ressources de la même compagnie, bon rôle, bus utilisable et assez grand pour les passagers
 * affectés, et pas de double affectation sur deux missions simultanées (même événement, même sens).
 */
class MissionAssignment
{
    /**
     * @param  array{bus_id?: int|null, driver_id?: int|null, conveyor_id?: int|null, device_id?: int|null}  $assignment
     */
    public function assign(Mission $mission, array $assignment): Mission
    {
        $errors = [];

        if (array_key_exists('bus_id', $assignment) && $assignment['bus_id'] !== null) {
            $bus = Bus::query()->withoutGlobalScopes()->where('company_id', $mission->company_id)->find($assignment['bus_id']);

            if ($bus === null) {
                $errors['bus_id'] = 'Bus inconnu pour cette compagnie.';
            } elseif (in_array($bus->status, [BusStatus::Broken, BusStatus::OutOfService], true)) {
                $errors['bus_id'] = "Le bus {$bus->number} est {$bus->status->label()}.";
            } elseif ($bus->capacity < $mission->outboundPasses()->count()) {
                $errors['bus_id'] = "Le bus {$bus->number} ({$bus->capacity} places) est trop petit pour les passagers affectés.";
            } elseif ($this->taken($mission, 'bus_id', $bus->id)) {
                $errors['bus_id'] = "Le bus {$bus->number} est déjà affecté à une autre mission {$mission->direction->label()}.";
            }
        }

        foreach (['driver_id' => StaffRole::Driver, 'conveyor_id' => StaffRole::Conveyor] as $field => $role) {
            if (! array_key_exists($field, $assignment) || $assignment[$field] === null) {
                continue;
            }

            $staff = Staff::query()->withoutGlobalScopes()->where('company_id', $mission->company_id)->find($assignment[$field]);

            if ($staff === null || $staff->role !== $role || ! $staff->is_active) {
                $errors[$field] = "Choisissez un {$role->label()} actif de la compagnie.";
            } elseif ($this->taken($mission, $field, $staff->id)) {
                $errors[$field] = "{$staff->fullName()} est déjà affecté à une autre mission {$mission->direction->label()}.";
            }
        }

        if (array_key_exists('device_id', $assignment) && $assignment['device_id'] !== null) {
            $device = Device::query()->withoutGlobalScopes()->where('company_id', $mission->company_id)->find($assignment['device_id']);

            if ($device === null || $device->status === DeviceStatus::Blocked) {
                $errors['device_id'] = 'Terminal inconnu ou bloqué.';
            } elseif ($this->taken($mission, 'device_id', $device->id)) {
                $errors['device_id'] = "{$device->label} est déjà utilisé sur une autre mission {$mission->direction->label()}.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $mission->update(array_intersect_key($assignment, array_flip(['bus_id', 'driver_id', 'conveyor_id', 'device_id'])));

        return $mission;
    }

    private function taken(Mission $mission, string $field, int $id): bool
    {
        return Mission::query()->withoutGlobalScopes()
            ->where('event_id', $mission->event_id)
            ->where('direction', $mission->direction)
            ->whereKeyNot($mission->id)
            ->where($field, $id)
            ->exists();
    }
}
