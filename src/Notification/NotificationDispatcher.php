<?php

namespace App\Notification;

use App\Entity\Checkout;
use App\Entity\Member;
use App\Entity\Reservation;
use Twig\Environment;

final class NotificationDispatcher
{
    public function __construct(private Environment $twig)
    {
    }

    public function buildReservationMessage(Reservation $reservation, ?string $recipient = null): NotificationMessage
    {
        $context = [
            'reservation' => $reservation,
            'member' => $reservation->getMember(),
            'manifestation' => $reservation->getManifestation(),
        ];

        return new NotificationMessage(
            $this->twig->render('notification/reservation_subject.txt.twig', $context),
            $this->twig->render('notification/reservation.txt.twig', $context),
            $recipient !== null ? [$recipient] : [],
            $this->twig->render('notification/slack/reservation.txt.twig', $context)
        );
    }

    /**
     * @param Checkout[] $checkouts
     */
    public function buildCheckoutMessage(Member $member, array $checkouts, ?string $recipient = null): NotificationMessage
    {
        $context = [
            'member' => $member,
            'checkouts' => $checkouts,
        ];

        return new NotificationMessage(
            $this->twig->render('notification/checkout_subject.txt.twig', $context),
            $this->twig->render('notification/checkout.txt.twig', $context),
            $recipient !== null ? [$recipient] : [],
            $this->twig->render('notification/slack/checkout.txt.twig', $context)
        );
    }

    public function buildCheckinMessage(Checkout $checkout, ?string $recipient = null): NotificationMessage
    {
        $context = [
            'checkout' => $checkout,
            'member' => $checkout->getMember(),
            'manifestation' => $checkout->getManifestation(),
        ];

        return new NotificationMessage(
            $this->twig->render('notification/checkin_subject.txt.twig', $context),
            $this->twig->render('notification/checkin.txt.twig', $context),
            $recipient !== null ? [$recipient] : [],
            $this->twig->render('notification/slack/checkin.txt.twig', $context)
        );
    }
}
