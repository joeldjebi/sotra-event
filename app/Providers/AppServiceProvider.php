<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\PushSender;
use App\Contracts\SmsSender;
use App\Contracts\WhatsAppSender;
use App\Enums\Role;
use App\Integrations\Messaging\LogPushSender;
use App\Integrations\Messaging\LogWhatsAppSender;
use App\Integrations\Payment\FakeAggregator;
use App\Integrations\Sms\LogSmsSender;
use App\Integrations\Sms\MtargetSmsSender;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn (): PaymentGateway => match (config('eventtrans.drivers.payment')) {
            'fake' => new FakeAggregator,
            default => throw new InvalidArgumentException('Pilote de paiement inconnu : '.config('eventtrans.drivers.payment')),
        });

        $this->app->bind(WhatsAppSender::class, fn (): WhatsAppSender => match (config('eventtrans.drivers.whatsapp')) {
            'log' => new LogWhatsAppSender,
            default => throw new InvalidArgumentException('Pilote WhatsApp inconnu : '.config('eventtrans.drivers.whatsapp')),
        });

        $this->app->bind(PushSender::class, fn (): PushSender => match (config('eventtrans.drivers.push')) {
            'log' => new LogPushSender,
            default => throw new InvalidArgumentException('Pilote push inconnu : '.config('eventtrans.drivers.push')),
        });

        $this->app->bind(SmsSender::class, function (): SmsSender {
            return match (config('eventtrans.drivers.sms')) {
                'log' => new LogSmsSender,
                'mtarget' => MtargetSmsSender::fromConfig(),
                default => throw new InvalidArgumentException('Pilote SMS inconnu : '.config('eventtrans.drivers.sms')),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn (User $user): ?bool => $user->hasRole(Role::SuperAdmin->value) ? true : null);

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

        Password::defaults(fn (): Password => Password::min(12)->letters()->numbers());

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('otp-send', function (Request $request): array {
            $phone = PhoneNumber::isValid((string) $request->input('phone'))
                ? PhoneNumber::parse((string) $request->input('phone'))->e164
                : (string) $request->input('phone');

            return [
                Limit::perMinutes(10, 3)->by('otp-send-phone:'.$phone),
                Limit::perHour(20)->by('otp-send-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('otp-verify', fn (Request $request): Limit => Limit::perMinute(10)->by('otp-verify-ip:'.$request->ip()));
    }
}
