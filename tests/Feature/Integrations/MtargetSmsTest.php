<?php

use App\Contracts\SmsSender;
use App\Integrations\Sms\MtargetSmsSender;
use App\Models\OtpCode;
use App\Models\SmsMessage;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config([
        'eventtrans.drivers.sms' => 'mtarget',
        'services.mtarget' => [
            'url' => 'https://api-public.mtarget.fr/api-sms.json',
            'username' => 'compte-test',
            'password' => 'secret-test',
            'sender' => 'EVENTTRANS',
            'timeout' => 30,
            'verify_ssl' => true,
        ],
    ]);
});

function mtargetAccepted(): array
{
    return ['results' => [['msisdn' => '+2250707070707', 'smscount' => '2', 'code' => '0', 'reason' => 'ACCEPTED', 'ticket' => 'TCK-42']]];
}

it('envoie le SMS à MTarget en formulaire et trace le ticket (SMS_DRIVER=mtarget)', function () {
    Http::fake(['api-public.mtarget.fr/*' => Http::response(mtargetAccepted())]);

    $sms = app(SmsSender::class)->send(PhoneNumber::parse('07 07 07 07 07'), 'EVENT TRANS : bonjour');

    expect(app(SmsSender::class))->toBeInstanceOf(MtargetSmsSender::class)
        ->and($sms->fresh())
        ->driver->toBe('mtarget')
        ->status->toBe(SmsMessage::ACCEPTED)
        ->provider_reference->toBe('TCK-42')
        ->segments->toBe(2);

    Http::assertSent(fn (Request $request) => $request->isForm()
        && $request['msisdn'] === '+2250707070707'
        && $request['msg'] === 'EVENT TRANS : bonjour'
        && $request['sender'] === 'EVENTTRANS'
        && $request['username'] === 'compte-test'
        && $request['remoteid'] === (string) $sms->id);
});

it('marque le SMS en échec sans interrompre l\'appelant quand MTarget refuse ou ne répond pas', function () {
    Http::fakeSequence('api-public.mtarget.fr/*')
        ->push(['results' => [['code' => '6', 'reason' => 'INVALID_MSISDN']]])
        ->push('Erreur serveur', 500)
        ->pushFailedConnection();

    $sender = app(SmsSender::class);
    $phone = PhoneNumber::parse('0707070707');

    expect($sender->send($phone, 'un')->error)->toBe('Refusé par MTarget (HTTP 200, INVALID_MSISDN).')
        ->and($sender->send($phone, 'deux')->error)->toBe('Refusé par MTarget (HTTP 500, réponse inattendue).')
        ->and($sender->send($phone, 'trois')->error)->toBe('MTarget injoignable (erreur réseau ou délai dépassé).')
        ->and(SmsMessage::query()->where('status', SmsMessage::FAILED)->count())->toBe(3);
});

it('n\'appelle pas MTarget sans identifiants et ne journalise jamais le mot de passe ni le numéro complet', function () {
    Http::fake(['*' => Http::response(mtargetAccepted())]);
    Log::spy();
    config(['services.mtarget.password' => '']);

    expect(app(SmsSender::class)->send(PhoneNumber::parse('0707070707'), 'test')->error)->toBe('Identifiants MTarget non configurés.');
    Http::assertNothingSent();

    config(['services.mtarget.password' => 'secret-test']);
    app(SmsSender::class)->send(PhoneNumber::parse('0707070707'), 'test');

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context) => $message === '[SMS MTarget] accepté'
        && $context['to'] === '+22507******07'
        && ! str_contains(json_encode($context), 'secret-test'));
});

it('signale au spectateur un code OTP non envoyé et le laisse redemander aussitôt', function () {
    Http::fakeSequence()
        ->push(['results' => [['code' => '9', 'reason' => 'NO_CREDIT']]])
        ->push(mtargetAccepted());

    $this->postJson('/api/v1/auth/otp', ['phone' => '0707070707', 'audience' => 'customer'])
        ->assertStatus(503)
        ->assertJsonPath('code', 'SMS_NOT_SENT');

    expect(OtpCode::query()->whereNull('consumed_at')->count())->toBe(0);

    $this->postJson('/api/v1/auth/otp', ['phone' => '0707070707', 'audience' => 'customer'])->assertSuccessful();
});

it('diagnostique l\'agrégateur avec sms:test', function () {
    Http::fake(['*' => Http::response(mtargetAccepted())]);

    $this->artisan('sms:test', ['phone' => '0707070707'])
        ->expectsOutputToContain('SMS accepté par MTarget.')
        ->assertSuccessful();

});
