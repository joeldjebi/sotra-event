<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Supervision des files (NF-08) : tableau Horizon réservé au super administrateur, alertes d'exploitation
 * (attente trop longue d'une file, échec en série) par e-mail ou Slack selon OPS_ALERT_EMAIL / OPS_SLACK_WEBHOOK.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        if (is_string(config('eventtrans.ops.alert_email')) && config('eventtrans.ops.alert_email') !== '') {
            Horizon::routeMailNotificationsTo((string) config('eventtrans.ops.alert_email'));
        }

        if (is_string(config('eventtrans.ops.slack_webhook')) && config('eventtrans.ops.slack_webhook') !== '') {
            Horizon::routeSlackNotificationsTo((string) config('eventtrans.ops.slack_webhook'), '#eventtrans-ops');
        }
    }

    /**
     * Accès au tableau Horizon hors local : super administrateur ACCES EVENT uniquement.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null): bool => $user?->hasRole(Role::SuperAdmin->value) ?? false);
    }
}
