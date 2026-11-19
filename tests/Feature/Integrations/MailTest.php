<?php

use App\Enums\Role;
use App\Models\Promoter;
use App\Notifications\PromoterApplicationReceived;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;

it('envoie un e-mail de test depuis l\'adresse no-reply configurée', function () {
    config(['mail.default' => 'array', 'mail.from.address' => 'no-reply@accestrans.com']);

    $this->artisan('mail:test', ['email' => 'ops@example.com'])
        ->expectsOutputToContain('no-reply@accestrans.com')
        ->expectsOutputToContain('MAIL_MAILER=array')
        ->assertFailed();

    $message = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage();

    expect($message->getFrom()[0]->getAddress())->toBe('no-reply@accestrans.com')
        ->and($message->getTo()[0]->getAddress())->toBe('ops@example.com');
});

it('refuse une adresse invalide', function () {
    $this->artisan('mail:test', ['email' => 'pas-une-adresse'])->assertExitCode(2);
});

it('rédige en français l\'e-mail de choix du mot de passe', function () {
    app()->setLocale('fr');
    $user = userWithRole(Role::Commercial);

    $mail = (new ResetPassword('jeton'))->toMail($user);

    expect($mail->subject)->toBe('Choisissez votre mot de passe ACCES TRANS')
        ->and($mail->actionText)->toBe('Choisir mon mot de passe');
});

it('met en file l\'alerte de nouvelle demande de promoteur', function () {
    expect(new PromoterApplicationReceived(Promoter::factory()->create()))->toBeInstanceOf(ShouldQueue::class);
});
