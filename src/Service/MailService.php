<?php

namespace App\Service;

use App\Entity\Reservation;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Service d'envoi d'emails pour l'application EventReservation.
 * Envoie un email de confirmation lors d'une réservation validée.
 */
class MailService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail = 'noreply@eventreservation.com',
        private string $fromName  = 'EventReservation'
    ) {}

    /**
     * Envoie un email de confirmation de réservation.
     */
    public function sendReservationConfirmation(Reservation $reservation): void
    {
        $event = $reservation->getEvent();

        $htmlBody = $this->buildConfirmationHtml($reservation);

        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($reservation->getEmail(), $reservation->getName()))
            ->subject(' Confirmation de votre réservation — ' . $event->getTitle())
            ->html($htmlBody);

        $this->mailer->send($email);
    }

    private function buildConfirmationHtml(Reservation $reservation): string
    {
        $event   = $reservation->getEvent();
        $eventDate = $event->getDate()->format('d/m/Y à H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de réservation</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #198754, #0d6efd); padding: 40px 30px; text-align: center; color: white; }
        .header h1 { margin: 0 0 8px; font-size: 26px; }
        .header p { margin: 0; opacity: 0.9; font-size: 15px; }
        .icon { font-size: 50px; margin-bottom: 15px; display: block; }
        .body { padding: 35px 30px; }
        .greeting { font-size: 17px; margin-bottom: 20px; color: #444; }
        .recap-box { background: #f0f9ff; border: 1px solid #b6e0fe; border-radius: 10px; padding: 25px; margin: 25px 0; }
        .recap-box h3 { margin: 0 0 18px; color: #0d6efd; font-size: 16px; text-transform: uppercase; letter-spacing: 0.5px; }
        .recap-row { display: flex; padding: 8px 0; border-bottom: 1px solid #e2eeff; }
        .recap-row:last-child { border-bottom: none; }
        .recap-label { font-weight: bold; color: #555; min-width: 160px; font-size: 14px; }
        .recap-value { color: #222; font-size: 14px; }
        .badge { display: inline-block; background: #0d6efd; color: white; padding: 3px 10px; border-radius: 20px; font-weight: bold; font-size: 13px; }
        .btn { display: inline-block; background: #0d6efd; color: white; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 15px; margin-top: 15px; }
        .note { background: #fff8e1; border-left: 4px solid #ffc107; padding: 15px 20px; border-radius: 6px; margin-top: 20px; font-size: 14px; color: #555; }
        .footer { background: #f8f9fa; padding: 20px 30px; text-align: center; color: #888; font-size: 13px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <span class="icon"></span>
        <h1>Réservation confirmée !</h1>
        <p>EventReservation — ISSAT Sousse FIA3-GL</p>
    </div>
    <div class="body">
        <p class="greeting">Bonjour <strong>{$reservation->getName()}</strong>,</p>
        <p>Votre réservation a bien été enregistrée. Voici le récapitulatif :</p>

        <div class="recap-box">
            <h3>Récapitulatif de la réservation</h3>
            <div class="recap-row">
                <span class="recap-label"> Événement</span>
                <span class="recap-value"><strong>{$event->getTitle()}</strong></span>
            </div>
            <div class="recap-row">
                <span class="recap-label"> Date</span>
                <span class="recap-value">{$eventDate}</span>
            </div>
            <div class="recap-row">
                <span class="recap-label"> Lieu</span>
                <span class="recap-value">{$event->getLocation()}</span>
            </div>
            <div class="recap-row">
                <span class="recap-label"> Nom</span>
                <span class="recap-value">{$reservation->getName()}</span>
            </div>
            <div class="recap-row">
                <span class="recap-label"> Email</span>
                <span class="recap-value">{$reservation->getEmail()}</span>
            </div>
            <div class="recap-row">
                <span class="recap-label"> Téléphone</span>
                <span class="recap-value">{$reservation->getPhone()}</span>
            </div>
            <div class="recap-row">
                <span class="recap-label"> N° de réservation</span>
                <span class="recap-value"><span class="badge">#{$reservation->getId()}</span></span>
            </div>
        </div>

        <div class="note">
            <strong> À noter :</strong> Conservez cet email comme preuve de votre réservation.
            Présentez votre numéro de réservation <strong>#{$reservation->getId()}</strong> le jour de l'événement.
        </div>
    </div>
   
</div>
</body>
</html>
HTML;
    }
}