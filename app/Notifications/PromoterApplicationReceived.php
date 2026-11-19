<?php

namespace App\Notifications;

use App\Models\Promoter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prévient les administrateurs ACCES EVENT qu'une demande de compte promoteur attend leur validation.
 * Mise en file : une panne du serveur d'envoi ne bloque pas l'inscription.
 */
class PromoterApplicationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Promoter $promoter) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Nouvelle demande de compte promoteur : '.$this->promoter->name)
            ->line("{$this->promoter->name} demande un compte promoteur sur ACCES TRANS.");

        if (! $this->promoter->isFileComplete()) {
            $mail->line('Dossier incomplet : '.implode(', ', $this->promoter->missingItems()).'.');
        }

        return $mail->action('Examiner la demande', route('admin.promoters', ['statut' => 'en_attente']));
    }
}
