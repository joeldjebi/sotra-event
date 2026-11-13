<?php

namespace Database\Seeders;

use App\Actions\Orders\CreateOrder;
use App\Actions\Payments\ApplyPaymentResult;
use App\Actions\Payments\InitiatePayment;
use App\Enums\BreakdownType;
use App\Enums\BusCategory;
use App\Enums\BusStatus;
use App\Enums\ComplaintCategory;
use App\Enums\Direction;
use App\Enums\DropZoneUsage;
use App\Enums\EventStatus;
use App\Enums\InterventionResult;
use App\Enums\InterventionStep;
use App\Enums\MessageAudience;
use App\Enums\MissionStatus;
use App\Enums\Offer;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Enums\RefundReason;
use App\Enums\SalesChannel;
use App\Enums\StaffRole;
use App\Enums\TechTeamStatus;
use App\Exceptions\ApiException;
use App\Models\BoardingPoint;
use App\Models\Bus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Mission;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Promoter;
use App\Models\Refund;
use App\Models\Staff;
use App\Models\TechTeam;
use App\Models\User;
use App\Models\Venue;
use App\Services\Finance\PayoutCalculator;
use App\Services\Finance\RefundService;
use App\Services\Operations\BreakdownService;
use App\Services\Operations\DelayMonitor;
use App\Services\Operations\MissionPlanner;
use App\Services\Operations\ScanValidator;
use App\Services\Operations\TripTracker;
use App\Services\Promoter\PromoterMessenger;
use App\Services\Quality\ComplaintService;
use App\Services\Quality\ReviewService;
use App\Services\Reporting\QualityMetrics;
use App\Services\Sales\PassTokenService;
use App\Services\ZoneResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Jeu de démonstration « jour J et bilan », pour présenter tous les écrans remplis.
 * Tout passe par les vrais services (vente, paiement, scans, départs, panne, avis, réclamations,
 * remboursements, reversement), l'horloge étant antidatée quand il le faut.
 *
 * - « Festival des Grillades » (il y a 12 jours, terminé) : bilan complet, qualité, pilote, remboursements,
 *   reversement payé avec justificatif.
 * - « Match de gala » (aujourd'hui, en direct) : bus arrivés, en route, une panne urgente en attente
 *   de remplacement, un départ en retard, des bus en embarquement. Les horaires sont calés sur l'heure
 *   du lancement : relancez le seeder juste avant une présentation.
 *
 * php artisan db:seed --class=DemoOperationsSeeder (après DemoSeeder et DemoEventsSeeder ; relançable).
 */
class DemoOperationsSeeder extends Seeder
{
    private const string PAST = 'Festival des Grillades d\'Abidjan';

    private const string LIVE = 'Match de gala · Stade Félix Houphouët-Boigny';

    private int $phone = 0;

    private Company $sotra;

    private ?User $admin = null;

    private ?User $lead = null;

    private CreateOrder $createOrder;

    private InitiatePayment $initiatePayment;

    private ApplyPaymentResult $applyPaymentResult;

    private MissionPlanner $planner;

    private ScanValidator $scans;

    private PassTokenService $tokens;

    private TripTracker $tracker;

    private DelayMonitor $delays;

    private BreakdownService $breakdowns;

    private ReviewService $reviews;

    private ComplaintService $complaints;

    private RefundService $refunds;

    private PromoterMessenger $messenger;

    private PayoutCalculator $payouts;

    private ZoneResolver $zones;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Jeu de démonstration interdit en production.');
        }

        // Jamais de vrai SMS ni de file en attente pendant la démo : pilotes simulés et traitement immédiat.
        config(['eventtrans.drivers.sms' => 'log', 'eventtrans.drivers.whatsapp' => 'log', 'eventtrans.drivers.push' => 'log', 'queue.default' => 'sync', 'broadcasting.default' => 'log']);
        $this->createOrder = app(CreateOrder::class);
        $this->initiatePayment = app(InitiatePayment::class);
        $this->applyPaymentResult = app(ApplyPaymentResult::class);
        $this->planner = app(MissionPlanner::class);
        $this->scans = app(ScanValidator::class);
        $this->tokens = app(PassTokenService::class);
        $this->tracker = app(TripTracker::class);
        $this->delays = app(DelayMonitor::class);
        $this->breakdowns = app(BreakdownService::class);
        $this->reviews = app(ReviewService::class);
        $this->complaints = app(ComplaintService::class);
        $this->refunds = app(RefundService::class);
        $this->messenger = app(PromoterMessenger::class);
        $this->payouts = app(PayoutCalculator::class);
        $this->zones = app(ZoneResolver::class);

        mt_srand(20261113);
        $this->sotra = Company::query()->where('slug', 'sotra')->firstOrFail();
        $this->admin = User::query()->where('email', 'admin@eventtrans.test')->first();
        $this->lead = User::query()->where('email', 'chef.projet@promoteur.test')->first();
        $this->phone = 2_000_000 + (int) Customer::query()->count();

        $this->removePrevious();
        $realNow = Carbon::now();

        try {
            $past = $this->pastEvent($realNow);
            $live = $this->liveEvent($realNow);
        } finally {
            Carbon::setTestNow();
        }

        $this->command?->info("« {$past->name} » : bilan, qualité, remboursements et reversement payé.");
        $this->command?->info("« {$live->name} » : jour J en direct (horaires calés sur {$realNow->format('H\hi')}).");
    }

    /**
     * Événement terminé : cycle complet jusqu'au reversement payé.
     */
    private function pastEvent(Carbon $realNow): Event
    {
        $day = $realNow->copy()->subDays(12)->startOfDay();
        $venue = Venue::query()->updateOrCreate(['name' => 'Palais de la Culture de Treichville'], ['address' => 'Treichville', 'city' => 'Abidjan', 'location' => Point::makeGeodetic(5.2990, -4.0080)]);
        $event = $this->event(self::PAST, $venue, $day->copy()->setTime(18, 0), $realNow->copy()->subDays(40));
        $this->points($event, [['YOP', 'Gare Siporex', 5.3446, -4.0802, '15:45'], ['ABO', 'Gare d\'Abobo', 5.4180, -4.0200, '15:30'], ['COC', 'Carrefour Riviera 2', 5.3600, -3.9620, '16:00']], '23:30');
        $this->offers($event, [[Offer::Standard, 4, 40], [Offer::Premium, 1, 40]]);
        $this->dropZones($event, 5.2995, -4.0075);

        // Ventes sur trois semaines, puis quelques annulations.
        $this->sales($event, $day->copy()->subDays(25), $day->copy()->subDays(1), 150);
        Carbon::setTestNow($day->copy()->subDays(3)->setTime(10, 0));
        $cancellable = Pass::query()->where('event_id', $event->id)->where('status', PassStatus::Valid)->inRandomOrder()->limit(4)->get();

        foreach ($cancellable as $index => $pass) {
            if ($index === 0) {
                Payment::query()->where('order_id', $pass->order_id)->update(['method' => PaymentMethod::MoovMoney]);
            }

            $this->refunds->cancel($pass->fresh(['event']) ?? $pass, $index === 3 ? RefundReason::Goodwill : RefundReason::Customer, $index === 3 ? $this->admin : null, $index === 3 ? 'Spectateur hospitalisé' : null);
        }

        $manual = Refund::query()->where('event_id', $event->id)->where('mode', 'manuel')->first();

        if ($manual !== null && $this->admin !== null) {
            Carbon::setTestNow($day->copy()->subDays(2)->setTime(11, 0));
            $this->refunds->markRefunded($manual, $this->admin, 'MOOV-'.Str::upper(Str::random(8)));
        }

        // Jour J : plan, équipages, réserve, équipes techniques.
        Carbon::setTestNow($day->copy()->setTime(9, 0));
        $this->planner->plan($event);
        $this->staff($event);
        $reserve = $this->reserveBus($event, '140');
        $this->techTeams($event);
        $this->messenger->send($event, $this->lead ?? $this->admin ?? User::query()->firstOrFail(), MessageAudience::All, null,
            'Rendez-vous 30 min avant le départ à votre point. Présentez votre Pass au convoyeur.', ['push', 'sms']);

        // Aller : montée, départs (dont un en retard de 25 min), arrivées.
        $gaps = [2, 6, 0, 14, 25, 4, 9];
        $outbound = $this->missions($event, Direction::Outbound);

        foreach ($outbound as $index => $mission) {
            $gap = $gaps[$index % count($gaps)];
            $this->board($mission, $mission->planned_departure_at->copy()->subMinutes(25), 0.93);
            $this->presentAgain($mission, $index % 3 === 0 ? 2 : 1);
            $this->depart($mission, $gap);
            $this->arriveAtVenue($mission, $mission->actual_departure_at?->copy()->addMinutes($this->travel($mission)) ?? now());
        }

        // Retour : une panne, le bus est remplacé par un bus de réserve.
        $return = $this->missions($event, Direction::Return);

        foreach ($return as $index => $mission) {
            $this->board($mission, $mission->planned_departure_at->copy()->subMinutes(20), 0.88);
            $this->depart($mission, [3, 1, 7, 0, 5][$index % 5]);

            if ($index === 1) {
                $this->breakdownReplaced($event, $mission, $reserve);

                continue;
            }

            Carbon::setTestNow($mission->actual_departure_at?->copy()->addMinutes($this->travel($mission)) ?? now());
            $mission->update(['status' => MissionStatus::Arrived, 'arrived_at' => now()]);
        }

        Mission::query()->where('event_id', $event->id)->update(['status' => MissionStatus::Finished]);

        // Lendemain : avis, réclamations, bilan, reversement.
        Carbon::setTestNow($day->copy()->addDay()->setTime(10, 0));
        $this->feedback($event);

        Carbon::setTestNow($day->copy()->addDay()->setTime(3, 0));
        $event->update(['status' => EventStatus::Finished]);
        $payout = $this->payouts->generate($event->fresh() ?? $event);

        Carbon::setTestNow(PayoutCalculator::dueOn($event->ends_at)->setTime(9, 30));
        $payout->update(['status' => PayoutStatus::Validated, 'validated_by' => $this->admin?->id, 'validated_at' => now()]);
        Carbon::setTestNow(now()->addHours(5));
        $proof = 'payouts/demo-justificatif-'.$payout->reference.'.pdf';
        Storage::disk('local')->put($proof, Pdf::loadHTML('<h2>Ordre de virement (démonstration)</h2><p>Bénéficiaire : '.e($this->sotra->name).'</p><p>Montant : '.number_format($payout->net, 0, ',', ' ').' FCFA</p><p>Référence : '.e($payout->reference).'</p>')->output());
        $payout->update([
            'status' => PayoutStatus::Paid, 'paid_by' => $this->admin?->id, 'paid_at' => now(),
            'payment_method' => PayoutMethod::BankTransfer, 'payment_reference' => 'VIR-'.now()->format('ymd').'-0042', 'proof_path' => $proof,
        ]);

        return $event;
    }

    /**
     * Événement du jour, en cours : arrivées, bus en route, panne urgente, retard, embarquement.
     */
    private function liveEvent(Carbon $realNow): Event
    {
        Carbon::setTestNow($realNow);
        $start = $realNow->copy()->addMinutes(90)->startOfHour()->addMinutes(30);
        $venue = Venue::query()->where('name', 'Stade Félix Houphouët-Boigny')->first()
            ?? Venue::query()->create(['name' => 'Stade Félix Houphouët-Boigny', 'address' => 'Plateau', 'city' => 'Abidjan', 'location' => Point::makeGeodetic(5.3266, -4.0189)]);
        $event = $this->event(self::LIVE, $venue, $start, $realNow->copy()->subDays(20));
        $minutesAgo = fn (int $minutes): string => $realNow->copy()->subMinutes($minutes)->format('H:i');
        $this->points($event, [
            ['YOP', 'Gare Siporex', 5.3446, -4.0802, $minutesAgo(55)],
            ['ABO', 'Gare d\'Abobo', 5.4180, -4.0200, $minutesAgo(40)],
            ['COC', 'Carrefour Riviera 2', 5.3600, -3.9620, $minutesAgo(30)],
            ['MAR', 'Place Marcory Zone 4', 5.3030, -3.9870, $realNow->copy()->addMinutes(20)->format('H:i')],
        ], $start->copy()->addHours(3)->format('H:i'));
        $this->offers($event, [[Offer::Standard, 6, 40], [Offer::Premium, 1, 40]]);
        $this->dropZones($event, 5.3275, -4.0180);
        $event->update(['doors_open_at' => $start->copy()->subMinutes(60)]);

        $this->sales($event, $realNow->copy()->subDays(18), $realNow->copy()->subHours(3), 170);

        Carbon::setTestNow($realNow->copy()->subHours(2));
        $this->planner->plan($event);
        $this->staff($event);
        $this->reserveBus($event, '141');
        $this->techTeams($event);
        $this->messenger->send($event, $this->lead ?? $this->admin ?? User::query()->firstOrFail(), MessageAudience::All, null,
            'Ce soir, ouverture des portes à '.($event->doors_open_at?->format('H\hi') ?? '').'. Les bus vous déposent au parking Nord.', ['push', 'sms', 'whatsapp']);

        $breakdownDone = false;

        foreach ($this->missions($event, Direction::Outbound) as $index => $mission) {
            $commune = $mission->boardingPoint->commune_code;
            $breakdown = $commune === 'COC' && ! $breakdownDone;
            $breakdownDone = $breakdownDone || $breakdown;
            $this->board($mission, $mission->planned_departure_at->copy()->subMinutes(20)->min($realNow->copy()->subMinutes(2)), $commune === 'MAR' ? 0.45 : 0.92, $realNow);

            match (true) {
                // Yopougon : partis à l'heure, arrivés au stade.
                $commune === 'YOP' => $this->liveArrived($mission, $realNow),
                // Abobo : un bus en route, le suivant n'est toujours pas parti (retard > 20 min).
                $commune === 'ABO' && $index % 2 === 1 => $this->delays->check($mission),
                $commune === 'ABO' => $this->liveOnRoute($mission, 6, 0.55, $realNow),
                // Cocody : panne urgente avec passagers à bord, équipe en route, remplacement à décider.
                $breakdown => $this->liveBreakdown($event, $mission, $realNow),
                $commune === 'COC' => $this->liveOnRoute($mission, 1, 0.7, $realNow),
                // Marcory : embarquement en cours.
                default => null,
            };
        }

        Carbon::setTestNow($realNow);
        $this->delays->sweep();

        return $event;
    }

    private function liveArrived(Mission $mission, Carbon $realNow): void
    {
        $this->depart($mission, 2);
        $this->arriveAtVenue($mission, $realNow->copy()->subMinutes(8));
        Carbon::setTestNow($realNow);
    }

    private function liveOnRoute(Mission $mission, int $gap, float $progress, Carbon $realNow): void
    {
        $this->depart($mission, $gap);
        Carbon::setTestNow($realNow->copy()->subMinute());
        $this->drive($mission, $progress, $realNow->copy()->subMinute());
        Carbon::setTestNow($realNow);
    }

    private function liveBreakdown(Event $event, Mission $mission, Carbon $realNow): void
    {
        $this->depart($mission, 3);
        $this->drive($mission, 0.4, $realNow->copy()->subMinutes(12));

        if ($mission->conveyor === null) {
            return;
        }

        Carbon::setTestNow($realNow->copy()->subMinutes(11));
        $alert = $this->breakdowns->report($mission->fresh() ?? $mission, $mission->conveyor, BreakdownType::Engine, 'Moteur en surchauffe, fumée sous le capot', $this->along($mission, 0.4), 12);

        $team = TechTeam::query()->withoutGlobalScopes()->where('company_id', $event->company_id)->where('code', 'T3')->first();

        if ($team !== null) {
            Carbon::setTestNow($realNow->copy()->subMinutes(9));
            $intervention = $this->breakdowns->take($alert, $team);
            Carbon::setTestNow($realNow->copy()->subMinutes(7));
            $this->breakdowns->advance($intervention, InterventionStep::EnRoute);
        }

        Carbon::setTestNow($realNow);
    }

    private function breakdownReplaced(Event $event, Mission $mission, Bus $reserve): void
    {
        $this->drive($mission, 0.3, $mission->actual_departure_at?->copy()->addMinutes(10) ?? now());
        Carbon::setTestNow($mission->actual_departure_at?->copy()->addMinutes(11) ?? now());
        $alert = $this->breakdowns->report($mission->fresh() ?? $mission, $mission->conveyor ?? Staff::query()->firstOrFail(), BreakdownType::Tyre, 'Crevaison pneu avant droit', $this->along($mission, 0.3), 10);

        $team = TechTeam::query()->withoutGlobalScopes()->where('company_id', $event->company_id)->where('code', 'T1')->first();

        if ($team === null) {
            return;
        }

        $intervention = $this->breakdowns->take($alert, $team);
        Carbon::setTestNow(now()->addMinutes(4));
        $this->breakdowns->advance($intervention, InterventionStep::EnRoute);
        Carbon::setTestNow(now()->addMinutes(14));
        $this->breakdowns->advance($intervention, InterventionStep::OnSite);
        Carbon::setTestNow(now()->addMinutes(6));
        $this->breakdowns->close($intervention, ['result' => InterventionResult::Replaced, 'replacement_bus_id' => $reserve->id, 'diagnosis' => 'Jante voilée, remorquage demandé', 'towing_requested' => true, 'duration_min' => 24]);

        $replacement = Mission::query()->where('event_id', $event->id)->where('bus_id', $reserve->id)->first();

        if ($replacement !== null) {
            $this->board($replacement, now()->addMinutes(2), 1.0, null, control: $mission);
            $replacement->update(['status' => MissionStatus::Arrived, 'actual_departure_at' => now()->addMinutes(5), 'arrived_at' => now()->addMinutes(35)]);
        }
    }

    /**
     * Avis (environ 55 % des voyageurs, notes plus basses dans les bus en retard) et réclamations.
     */
    private function feedback(Event $event): void
    {
        $passes = Pass::query()->where('event_id', $event->id)->whereIn('status', [PassStatus::OutboundUsed, PassStatus::ReturnUsed, PassStatus::Completed])->with('event')->get();
        $comments = ['Très bien organisé, merci !', 'Bus propre et à l\'heure.', 'Convoyeur très aimable.', 'Un peu d\'attente au retour.', 'La climatisation ne marchait pas.', 'Parfait, je reprendrai le Pass.', null, null, null];

        foreach ($passes as $pass) {
            if (mt_rand(1, 100) > 55) {
                continue;
            }

            $outbound = $pass->outboundMission()->withoutGlobalScopes()->first();
            $late = $outbound !== null && (QualityMetrics::gap($outbound) ?? 0) > QualityMetrics::tolerance();
            $base = $late ? 3 : 4;
            $rating = fn (int $spread = 1): int => max(1, min(5, $base + mt_rand(-$spread, 1)));

            try {
                $this->reviews->submit($pass, [
                    'punctuality' => $late ? mt_rand(1, 3) : $rating(),
                    'comfort' => $rating(),
                    'courtesy' => min(5, $rating() + 1),
                    'driving' => $rating(),
                    'information' => $rating(),
                ], $comments[mt_rand(0, count($comments) - 1)]);
            } catch (ApiException) {
            }
        }

        $samples = [
            [ComplaintCategory::MissedBus, 'Le bus retour de Yopougon est parti alors que j\'étais au point de reprise.', 'Nous vous prions de nous excuser : votre trajet retour vous est remboursé.'],
            [ComplaintCategory::Comfort, 'Sièges sales dans le bus, climatisation en panne.', 'Le bus a été nettoyé et la climatisation réparée. Merci du signalement.'],
            [ComplaintCategory::Behaviour, 'Le chauffeur roulait trop vite sur l\'autoroute.', null],
            [ComplaintCategory::LostItem, 'J\'ai oublié ma veste noire dans le bus retour Abobo.', null],
        ];

        foreach ($samples as $index => [$category, $details, $answer]) {
            $pass = $passes->get($index * 7);

            if ($pass === null) {
                continue;
            }

            $complaint = $this->complaints->open($pass, $category, $details);
            $handler = User::query()->where('email', 'qualite@sotra.test')->first() ?? $this->admin;

            if ($handler !== null && $index < 3) {
                $this->complaints->take($complaint, $handler);
            }

            if ($handler !== null && $answer !== null) {
                $this->complaints->resolve($complaint, $handler, $answer);
            }
        }

        $collective = $event->complaints()->where('is_collective', true)->first();
        $handler = User::query()->where('email', 'qualite@sotra.test')->first();

        if ($collective !== null && $handler !== null) {
            $this->complaints->resolve($collective, $handler, 'Nous nous excusons pour ce retard dû à la circulation. Un geste commercial vous sera proposé pour le prochain événement.');
        }
    }

    /**
     * Ventes réelles réparties entre deux dates : commande, paiement, confirmation de l'agrégateur.
     */
    private function sales(Event $event, Carbon $from, Carbon $to, int $target): void
    {
        $points = $event->boardingPoints()->orderBy('position')->get()->values();
        $sold = 0;
        $attempts = 0;
        $span = max(1, $from->diffInMinutes($to));

        while ($sold < $target && $attempts++ < $target * 3) {
            Carbon::setTestNow($from->copy()->addMinutes((int) ($span * (mt_rand(0, 1000) / 1000) ** 0.6)));
            $customer = Customer::query()->create(['phone' => $this->msisdn(), 'first_name' => fake('fr_FR')->firstName(), 'last_name' => fake('fr_FR')->lastName(), 'locale' => 'fr']);
            $offer = mt_rand(1, 100) > 82 ? Offer::Premium : Offer::Standard;
            $type = mt_rand(1, 100) > 92 ? PassType::Outbound : PassType::RoundTrip;
            $quantity = [1, 1, 2, 2, 2, 3, 4][mt_rand(0, 6)];
            $point = $points[[0, 0, 0, 1, 1, 2, 2, 3][mt_rand(0, 7)] % $points->count()];
            $channel = [SalesChannel::Web, SalesChannel::Web, SalesChannel::App, SalesChannel::App, SalesChannel::WhatsApp][mt_rand(0, 4)];

            try {
                $order = $this->createOrder->handle($customer, $event, $point->id, $offer, $type, $quantity, $channel === SalesChannel::WhatsApp && ! $event->hasChannel(SalesChannel::WhatsApp) ? SalesChannel::Web : $channel);
            } catch (ApiException) {
                continue;
            }

            $method = [PaymentMethod::OrangeMoney, PaymentMethod::Wave, PaymentMethod::Wave, PaymentMethod::MtnMomo][mt_rand(0, 3)];
            $payment = $this->initiatePayment->handle($order, $method, $customer->phone)['payment'];
            Carbon::setTestNow(now()->addMinutes(mt_rand(1, 4)));
            $this->applyPaymentResult->handle($payment, PaymentStatus::Succeeded);
            $sold += $quantity;
        }
    }

    /**
     * Montée : scans des Pass prévus dans le bus, quelques-uns depuis un autre bus du même point (orange).
     */
    private function board(Mission $mission, Carbon $at, float $share, ?Carbon $notAfter = null, ?Mission $control = null): void
    {
        if ($mission->conveyor === null) {
            return;
        }

        $passes = match (true) {
            // Bus de remplacement : les passagers déjà montés dans le bus en panne (scan de contrôle).
            $control !== null => Pass::query()->whereIn('id', $control->scans()->where('result', 'vert')->pluck('pass_id'))->get(),
            $mission->direction === Direction::Outbound => Pass::query()->where('outbound_mission_id', $mission->id)->where('status', PassStatus::Valid)->get(),
            default => Pass::query()->where('event_id', $mission->event_id)->where('boarding_point_id', $mission->boarding_point_id)
                ->where('offer', $mission->offer)->whereNull('return_mission_id')
                ->whereIn('status', [PassStatus::Valid, PassStatus::OutboundUsed])
                ->whereIn('pass_type', [PassType::RoundTrip, PassType::Return])
                ->limit($mission->seats)->get(),
        };

        foreach ($passes as $index => $pass) {
            if (mt_rand(1, 1000) > $share * 1000) {
                continue;
            }

            $scannedAt = $at->copy()->addSeconds(20 * $index);

            if ($notAfter !== null && $scannedAt->greaterThan($notAfter)) {
                break;
            }

            Carbon::setTestNow($scannedAt);
            $this->scans->validate($mission, $mission->conveyor, $this->tokens->sign($pass->fresh() ?? $pass), (string) Str::uuid(), $scannedAt);
        }
    }

    /**
     * Pass présentés une seconde fois (capture d'écran partagée) : refusés en rouge « déjà utilisé ».
     */
    private function presentAgain(Mission $mission, int $count): void
    {
        $scanned = $mission->scans()->where('result', 'vert')->with('pass')->limit($count)->get();

        foreach ($scanned as $scan) {
            if ($scan->pass !== null && $mission->conveyor !== null) {
                Carbon::setTestNow($scan->scanned_at->copy()->addMinutes(8));
                $this->scans->validate($mission, $mission->conveyor, $this->tokens->sign($scan->pass), (string) Str::uuid());
            }
        }
    }

    private function depart(Mission $mission, int $gapMinutes): void
    {
        Carbon::setTestNow($mission->planned_departure_at->copy()->addMinutes($gapMinutes));
        $mission->update(['status' => MissionStatus::OnRoute, 'actual_departure_at' => now()]);
        $this->delays->check($mission);
    }

    /**
     * Quelques positions GPS sur le trajet, puis l'entrée dans la zone de dépose (arrivée détectée).
     */
    private function arriveAtVenue(Mission $mission, Carbon $arrival): void
    {
        $this->drive($mission, 0.5, $arrival->copy()->subMinutes(10));
        Carbon::setTestNow($arrival);
        $zone = $mission->event->dropZones()->where('usage', DropZoneUsage::DropOff)->first();

        if ($zone !== null) {
            $this->tracker->record($mission->fresh() ?? $mission, [[
                'latitude' => $zone->location->getLatitude(), 'longitude' => $zone->location->getLongitude(), 'recorded_at' => $arrival->toIso8601String(), 'speed_kmh' => 8.0,
            ]]);
        }

        if ($mission->fresh()?->arrived_at === null) {
            $mission->update(['status' => MissionStatus::Arrived, 'arrived_at' => $arrival]);
        }
    }

    private function drive(Mission $mission, float $progress, Carbon $at): void
    {
        Carbon::setTestNow($at);
        $points = [];

        foreach ([0.2, 0.6, 1.0] as $step) {
            $point = $this->along($mission, $progress * $step);
            $points[] = ['latitude' => $point->getLatitude(), 'longitude' => $point->getLongitude(), 'recorded_at' => $at->copy()->subMinutes((int) (6 * (1 - $step)))->toIso8601String(), 'speed_kmh' => (float) mt_rand(25, 45)];
        }

        $this->tracker->record($mission->fresh() ?? $mission, $points);
    }

    /**
     * Point intermédiaire entre le point d'embarquement et le lieu (ou l'inverse au retour).
     */
    private function along(Mission $mission, float $progress): Point
    {
        $from = $mission->boardingPoint->location;
        $to = $mission->event->venue->location;

        if ($mission->direction === Direction::Return) {
            [$from, $to] = [$to, $from];
        }

        return Point::makeGeodetic(
            $from->getLatitude() + ($to->getLatitude() - $from->getLatitude()) * $progress,
            $from->getLongitude() + ($to->getLongitude() - $from->getLongitude()) * $progress,
        );
    }

    private function travel(Mission $mission): int
    {
        return $mission->boardingPoint->travel_minutes ?? 35;
    }

    /**
     * @return list<Mission>
     */
    private function missions(Event $event, Direction $direction): array
    {
        return Mission::query()->with(['boardingPoint', 'conveyor', 'event.venue', 'event.dropZones'])->where('event_id', $event->id)
            ->where('direction', $direction)->whereNull('replaced_by_mission_id')->orderBy('boarding_point_id')->orderBy('sequence')->get()->all();
    }

    /**
     * Bus, chauffeurs et convoyeurs de démonstration affectés aux missions de l'événement.
     */
    private function staff(Event $event): void
    {
        foreach (Mission::query()->where('event_id', $event->id)->orderBy('direction')->orderBy('id')->get() as $index => $mission) {
            $slot = $index % 12;
            $bus = Bus::query()->withoutGlobalScopes()->firstOrCreate(
                ['company_id' => $this->sotra->id, 'number' => (string) (101 + $slot)],
                ['plate' => sprintf('%04d AB 01', 3100 + $slot), 'capacity' => 50, 'category' => $mission->offer === Offer::Premium ? BusCategory::Premium : BusCategory::Standard, 'status' => BusStatus::InService],
            );
            $mission->update([
                'bus_id' => $bus->id,
                'driver_id' => $this->member(StaffRole::Driver, $slot)->id,
                'conveyor_id' => $this->member(StaffRole::Conveyor, $slot)->id,
            ]);
        }
    }

    private function member(StaffRole $role, int $slot): Staff
    {
        $prefix = $role === StaffRole::Driver ? '+225050100' : '+225050200';

        return Staff::query()->withoutGlobalScopes()->firstOrCreate(
            ['phone' => $prefix.str_pad((string) $slot, 4, '0', STR_PAD_LEFT)],
            ['company_id' => $this->sotra->id, 'role' => $role, 'first_name' => fake('fr_FR')->firstName('male'), 'last_name' => fake('fr_FR')->lastName(),
                'employee_number' => ($role === StaffRole::Driver ? 'CH-' : 'CV-').(500 + $slot), 'is_active' => true],
        );
    }

    private function reserveBus(Event $event, string $number): Bus
    {
        $bus = Bus::query()->withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $this->sotra->id, 'number' => $number],
            ['plate' => '9'.$number.' RS 01', 'capacity' => 50, 'category' => BusCategory::Standard, 'status' => BusStatus::Reserve],
        );
        $bus->update(['status' => BusStatus::Reserve]);
        $event->reserveBuses()->updateOrCreate(['bus_id' => $bus->id], ['waiting_place' => 'Dépôt de Yopougon']);

        return $bus;
    }

    private function techTeams(Event $event): void
    {
        foreach (['T1' => 'Secteur Nord', 'T3' => 'Secteur Sud'] as $code => $sector) {
            $team = TechTeam::query()->withoutGlobalScopes()->where('company_id', $this->sotra->id)->where('code', $code)->first();

            if ($team !== null) {
                $team->update(['status' => TechTeamStatus::Available]);
                $event->techTeams()->updateOrCreate(['tech_team_id' => $team->id], ['sector' => $sector]);
            }
        }
    }

    private function event(string $name, Venue $venue, Carbon $startsAt, Carbon $createdAt): Event
    {
        $promoter = Promoter::query()->where('name', 'Promoteur Démo')->firstOrFail();

        return Event::query()->withoutGlobalScopes()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.$startsAt->format('Y-m-d'),
            'description' => 'Événement de démonstration EVENT TRANS.',
            'venue_id' => $venue->id,
            'promoter_id' => $promoter->id,
            'transport_lead_id' => $this->lead?->id,
            'company_id' => $this->sotra->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(3),
            'doors_open_at' => $startsAt->copy()->subHour(),
            'estimated_passes' => 400,
            'commission_rate' => '10.00',
            'fee_rate' => '2.00',
            'channels' => ['web', 'app'],
            'status' => EventStatus::OnSale,
            'created_by' => $this->admin?->id,
            'proposed_at' => $createdAt,
            'accepted_at' => $createdAt->copy()->addDay(),
            'sale_opened_at' => $createdAt->copy()->addDays(2),
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: float, 3: float, 4: string}>  $points
     */
    private function points(Event $event, array $points, string $returnTime): void
    {
        $day = $event->starts_at->toDateString();

        foreach ($points as $index => [$code, $place, $lat, $lng, $departure]) {
            $location = Point::makeGeodetic($lat, $lng);
            $distance = $this->zones->distanceKm($event->venue->location, $location);
            $return = Carbon::parse("{$day} {$returnTime}");

            BoardingPoint::query()->create([
                'event_id' => $event->id,
                'commune' => config('eventtrans.communes')[$code],
                'commune_code' => $code,
                'place' => $place,
                'location' => $location,
                'distance_km' => $distance,
                'zone' => $this->zones->zoneFor($distance),
                'departure_at' => Carbon::parse("{$day} {$departure}"),
                'return_departure_at' => $return->lessThan($event->ends_at) ? $return->addDay() : $return,
                'travel_minutes' => $this->zones->estimatedTravelMinutes($distance),
                'position' => $index + 1,
            ]);
        }
    }

    /**
     * @param  list<array{0: Offer, 1: int, 2: int}>  $offers
     */
    private function offers(Event $event, array $offers): void
    {
        $defaults = (array) config('eventtrans.events.default_price_grid');

        foreach ($offers as [$offer, $buses, $seats]) {
            $event->offers()->create(['offer' => $offer, 'bus_count' => $buses, 'seats_per_bus' => $seats]);

            foreach (PassType::cases() as $type) {
                foreach (ZoneResolver::zones() as $zone) {
                    $price = $defaults[$type->value][$zone][$offer->value] ?? null;

                    if ($price !== null) {
                        $event->prices()->create(['zone' => $zone, 'offer' => $offer, 'pass_type' => $type, 'price' => $price]);
                    }
                }
            }
        }
    }

    private function dropZones(Event $event, float $lat, float $lng): void
    {
        $event->dropZones()->createMany([
            ['name' => 'Parking Nord · porte A', 'usage' => DropZoneUsage::DropOff, 'location' => Point::makeGeodetic($lat, $lng), 'radius_m' => 250],
            ['name' => 'Parking Est · porte C', 'usage' => DropZoneUsage::PickUp, 'scheduled_at' => $event->ends_at->copy()->addMinutes(30), 'location' => Point::makeGeodetic($lat - 0.0015, $lng + 0.0012), 'radius_m' => 200],
            ['name' => 'Stand d\'accueil EVENT TRANS', 'usage' => DropZoneUsage::Welcome, 'location' => Point::makeGeodetic($lat + 0.0004, $lng - 0.0003), 'radius_m' => 40],
        ]);
    }

    /**
     * Supprime le jeu précédent (événements de démonstration et leurs données) pour pouvoir le relancer.
     */
    private function removePrevious(): void
    {
        foreach (Event::query()->withoutGlobalScopes()->whereIn('name', [self::PAST, self::LIVE])->get() as $event) {
            Refund::query()->withoutGlobalScopes()->where('event_id', $event->id)->delete();
            Payout::query()->withoutGlobalScopes()->where('event_id', $event->id)->delete();
            Pass::query()->where('event_id', $event->id)->delete();
            Order::query()->where('event_id', $event->id)->delete();
            $event->delete();
        }

        // Spectateurs de démonstration sans autre achat.
        Customer::query()->where('phone', 'like', '+225071%')->whereDoesntHave('orders')->delete();
    }

    private function msisdn(): string
    {
        $this->phone++;

        return '+225071'.str_pad((string) $this->phone, 7, '0', STR_PAD_LEFT);
    }
}
