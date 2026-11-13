<?php

namespace App\Livewire\Company;

use App\Enums\BusStatus;
use App\Enums\DeviceStatus;
use App\Enums\Direction;
use App\Enums\EventStatus;
use App\Enums\Permission;
use App\Enums\StaffRole;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Bus;
use App\Models\Device;
use App\Models\Event;
use App\Models\EventReserveBus;
use App\Models\EventTechTeam;
use App\Models\Mission;
use App\Models\Staff;
use App\Models\TechTeam;
use App\Services\Operations\MissionAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Espace compagnie · Exploitation · Bus & affectations : bus, chauffeur, convoyeur et terminal par mission,
 * et bus de réserve de l'événement (§ 2 CDC, profil Exploitation).
 */
class Assignments extends Component
{
    use AuthorizesPermission;

    #[Url(as: 'evenement')]
    public ?int $eventId = null;

    #[Url(as: 'sens')]
    public string $direction = 'aller';

    public ?int $reserveBusId = null;

    public string $waitingPlace = '';

    public ?int $techTeamId = null;

    public string $sector = '';

    protected function permission(): Permission
    {
        return Permission::FleetManage;
    }

    public function mount(): void
    {
        $this->eventId ??= $this->events()->first()?->id;
    }

    /**
     * Enregistre immédiatement une affectation (bus, chauffeur, convoyeur ou terminal).
     */
    public function assign(int $missionId, string $field, ?string $value, MissionAssignment $assignment): void
    {
        abort_unless(in_array($field, ['bus_id', 'driver_id', 'conveyor_id', 'device_id'], true), 400);
        $mission = Mission::query()->where('event_id', $this->eventId)->findOrFail($missionId);

        try {
            $assignment->assign($mission, [$field => $value === null || $value === '' ? null : (int) $value]);
            $this->notify('Affectation enregistrée.');
        } catch (ValidationException $exception) {
            $this->notify((string) collect($exception->errors())->flatten()->first(), 'danger');
            $this->addError("mission.{$missionId}.{$field}", (string) collect($exception->errors())->flatten()->first());
        }
    }

    public function addReserve(): void
    {
        $event = $this->events()->findOrFail($this->eventId);

        $this->validate([
            'reserveBusId' => ['required', Rule::exists('buses', 'id')->where('company_id', $event->company_id)],
            'waitingPlace' => ['required', 'string', 'max:120'],
        ], attributes: ['reserveBusId' => 'bus', 'waitingPlace' => 'position d\'attente']);

        if (Mission::query()->where('event_id', $event->id)->where('bus_id', $this->reserveBusId)->exists()) {
            $this->addError('reserveBusId', 'Ce bus est déjà affecté à une mission.');

            return;
        }

        EventReserveBus::query()->updateOrCreate(['event_id' => $event->id, 'bus_id' => $this->reserveBusId], ['waiting_place' => $this->waitingPlace]);
        $this->reset(['reserveBusId', 'waitingPlace']);
        $this->notify('Bus de réserve ajouté.');
    }

    public function removeReserve(int $reserveId): void
    {
        EventReserveBus::query()->where('event_id', $this->eventId)->findOrFail($reserveId)->delete();
        $this->notify('Bus retiré de la réserve.');
    }

    /**
     * Mobilise une équipe technique sur l'événement : elle recevra les alertes de panne (ST-01).
     */
    public function addTechTeam(): void
    {
        $event = $this->events()->findOrFail($this->eventId);

        $this->validate([
            'techTeamId' => ['required', Rule::exists('tech_teams', 'id')->where('company_id', $event->company_id)],
            'sector' => ['required', 'string', 'max:120'],
        ], attributes: ['techTeamId' => 'équipe', 'sector' => 'secteur']);

        EventTechTeam::query()->updateOrCreate(['event_id' => $event->id, 'tech_team_id' => $this->techTeamId], ['sector' => $this->sector]);
        $this->reset(['techTeamId', 'sector']);
        $this->notify('Équipe mobilisée.');
    }

    public function removeTechTeam(int $assignmentId): void
    {
        EventTechTeam::query()->where('event_id', $this->eventId)->findOrFail($assignmentId)->delete();
        $this->notify('Équipe retirée de l\'événement.');
    }

    /**
     * @return Builder<Event>
     */
    private function events()
    {
        return Event::query()
            ->whereIn('status', [EventStatus::Accepted, EventStatus::OnSale])
            ->where('ends_at', '>', now()->subDay())
            ->orderBy('starts_at');
    }

    public function render(): View
    {
        $event = $this->eventId ? $this->events()->with('venue')->find($this->eventId) : null;
        $direction = Direction::tryFrom($this->direction) ?? Direction::Outbound;

        $missions = $event === null ? collect() : Mission::query()
            ->with(['boardingPoint', 'bus', 'driver', 'conveyor', 'device'])
            ->withCount('outboundPasses')
            ->where('event_id', $event->id)
            ->where('direction', $direction)
            ->orderBy('boarding_point_id')
            ->orderBy('offer')
            ->orderBy('sequence')
            ->get();

        $reserves = $event?->reserveBuses()->with('bus')->get() ?? collect();
        $usedBusIds = $event ? Mission::query()->where('event_id', $event->id)->whereNotNull('bus_id')->pluck('bus_id')->merge($reserves->pluck('bus_id')) : collect();

        return view('livewire.company.assignments', [
            'event' => $event,
            'eventOptions' => $this->events()->pluck('name', 'id')->all(),
            'missionsByPoint' => $missions->groupBy(fn (Mission $mission) => $mission->boardingPoint->commune.' · '.$mission->boardingPoint->place),
            'staffed' => $missions->filter(fn (Mission $mission) => $mission->isFullyStaffed())->count(),
            'total' => $missions->count(),
            'buses' => Bus::query()->whereNotIn('status', [BusStatus::Broken, BusStatus::OutOfService])->orderByRaw('length(number), number')->get(),
            'drivers' => Staff::query()->where('role', StaffRole::Driver)->where('is_active', true)->orderBy('last_name')->get(),
            'conveyors' => Staff::query()->where('role', StaffRole::Conveyor)->where('is_active', true)->orderBy('last_name')->get(),
            'devices' => Device::query()->where('status', DeviceStatus::Active)->orderBy('label')->get(),
            'reserves' => $reserves,
            'techAssignments' => $event?->techTeams()->with('techTeam.members')->get() ?? collect(),
            'teamOptions' => TechTeam::query()->orderBy('code')->get()->mapWithKeys(fn (TechTeam $team) => [$team->id => "Équipe {$team->code} · {$team->type->label()}"])->all(),
            'reserveOptions' => Bus::query()->whereNotIn('id', $usedBusIds)->whereNotIn('status', [BusStatus::Broken, BusStatus::OutOfService])
                ->orderByRaw('length(number), number')->get()->mapWithKeys(fn (Bus $bus) => [$bus->id => "Bus {$bus->number} · {$bus->capacity} places"])->all(),
        ]);
    }
}
