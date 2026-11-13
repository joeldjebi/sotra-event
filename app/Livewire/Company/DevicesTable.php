<?php

namespace App\Livewire\Company;

use App\Enums\DeviceStatus;
use App\Enums\Permission;
use App\Enums\StaffRole;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Scopes\CompanyScope;
use App\Models\Staff;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace compagnie · Exploitation · PDA & terminaux : inventaire, attribution, blocage à distance.
 *
 * Bloquer un terminal révoque aussi les jetons de la personne à qui il est attribué
 * (elle devra se reconnecter). Le contrôle du terminal à chaque appel API arrive à l'étape 6.
 */
class DevicesTable extends Component
{
    use ManagesRecords;

    public string $serial_number = '';

    public string $label = '';

    public string $model = '';

    public ?int $staff_id = null;

    public ?int $blockingId = null;

    public string $blocked_reason = '';

    public bool $showBlock = false;

    protected function permission(): Permission
    {
        return Permission::DevicesManage;
    }

    public function create(): void
    {
        $this->reset(['serial_number', 'label', 'model', 'staff_id', 'editingId']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $deviceId): void
    {
        $device = Device::query()->findOrFail($deviceId);
        $this->resetValidation();
        $this->editingId = $device->id;
        $this->serial_number = $device->serial_number;
        $this->label = $device->label;
        $this->model = (string) $device->model;
        $this->staff_id = $device->staff_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'serial_number' => ['required', 'string', 'max:60', Rule::unique('devices', 'serial_number')->ignore($this->editingId)],
            'label' => ['required', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:80'],
            'staff_id' => ['nullable', Rule::exists('staff', 'id')
                ->where('company_id', CompanyScope::currentCompanyId())
                ->whereIn('role', [StaffRole::Conveyor->value, StaffRole::Technician->value])],
        ], attributes: ['serial_number' => 'numéro de série', 'label' => 'nom', 'staff_id' => 'attribué à']);

        $device = Device::query()->updateOrCreate(['id' => $this->editingId], [
            'serial_number' => $this->serial_number,
            'label' => $this->label,
            'model' => $this->model ?: null,
            'staff_id' => $this->staff_id,
        ] + ($this->editingId === null ? ['status' => DeviceStatus::Active] : []));

        $this->showForm = false;
        $this->notify($this->editingId ? "{$device->label} mis à jour." : "{$device->label} ajouté.");
    }

    public function confirmBlock(int $deviceId): void
    {
        $this->blockingId = Device::query()->findOrFail($deviceId)->id;
        $this->blocked_reason = '';
        $this->resetValidation();
        $this->showBlock = true;
    }

    public function block(): void
    {
        $this->validate(['blocked_reason' => ['required', 'string', 'max:255']], attributes: ['blocked_reason' => 'motif']);

        $device = Device::query()->with('staff')->findOrFail($this->blockingId);
        $device->update([
            'status' => DeviceStatus::Blocked,
            'blocked_at' => now(),
            'blocked_reason' => $this->blocked_reason,
        ]);

        $device->staff?->tokens()->delete();
        AuditLog::record('device_blocked', $device, [], ['reason' => $this->blocked_reason]);

        $this->showBlock = false;
        $this->notify("{$device->label} bloqué.");
    }

    public function unblock(int $deviceId): void
    {
        $device = Device::query()->findOrFail($deviceId);
        $device->update(['status' => DeviceStatus::Active, 'blocked_at' => null, 'blocked_reason' => null]);
        AuditLog::record('device_unblocked', $device);
        $this->notify("{$device->label} débloqué.");
    }

    public function render(): View
    {
        return view('livewire.company.devices-table', [
            'devices' => Device::query()
                ->with('staff')
                ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->whereRaw('lower(label) like ?', [$this->searchTerm()])
                    ->orWhereRaw('lower(serial_number) like ?', [$this->searchTerm()])))
                ->orderBy('label')
                ->paginate(20),
            'assignable' => Staff::query()
                ->whereIn('role', [StaffRole::Conveyor, StaffRole::Technician])
                ->where('is_active', true)
                ->orderBy('last_name')
                ->get()
                ->mapWithKeys(fn (Staff $staff): array => [$staff->id => $staff->fullName().' · '.$staff->role->label()])
                ->all(),
        ]);
    }
}
