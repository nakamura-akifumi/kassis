<?php

namespace App\Service;

use App\Entity\Checkout;
use App\Entity\Member;
use App\Entity\Reservation;
use App\Notification\MailChannel;
use App\Notification\NotificationChannelInterface;
use App\Notification\NotificationDispatcher;
use App\Notification\NotificationMessage;
use App\Notification\RecipientResolver;
use Psr\Log\LoggerInterface;

final class NotificationService
{
    public function __construct(
        private RecipientResolver $recipientResolver,
        private NotificationDispatcher $dispatcher,
        /** @var iterable<NotificationChannelInterface> */
        private iterable $channels,
        private LoggerInterface $logger
    ) {
    }

    public function notifyReservation(Reservation $reservation): void
    {
        $member = $reservation->getMember();
        if (!$member instanceof Member) {
            $this->logger->warning('Reservation notification skipped: member not found.', [
                'reservationId' => $reservation->getId(),
            ]);
            return;
        }

        try {
            $recipient = $this->recipientResolver->resolveEmail($member);
            if ($recipient === null) {
                $this->logger->info('Reservation notification skipped: no email.', [
                    'reservationId' => $reservation->getId(),
                    'memberId' => $member->getId(),
                ]);
            }
            $message = $this->dispatcher->buildReservationMessage($reservation, $recipient);
            $this->sendToChannels($message);
        } catch (\Throwable $e) {
            $this->logger->error('Reservation notification failed.', [
                'reservationId' => $reservation->getId(),
                'memberId' => $member->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param Checkout[] $checkouts
     */
    public function notifyCheckout(Member $member, array $checkouts): void
    {
        if ($checkouts === []) {
            return;
        }

        try {
            $recipient = $this->recipientResolver->resolveEmail($member);
            if ($recipient === null) {
                $this->logger->info('Checkout notification skipped: no email.', [
                    'memberId' => $member->getId(),
                ]);
            }
            $message = $this->dispatcher->buildCheckoutMessage($member, $checkouts, $recipient);
            $this->sendToChannels($message);
        } catch (\Throwable $e) {
            $this->logger->error('Checkout notification failed.', [
                'memberId' => $member->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyCheckin(Checkout $checkout): void
    {
        $member = $checkout->getMember();
        if (!$member instanceof Member) {
            $this->logger->warning('Checkin notification skipped: member not found.', [
                'checkoutId' => $checkout->getId(),
            ]);
            return;
        }

        try {
            $recipient = $this->recipientResolver->resolveEmail($member);
            if ($recipient === null) {
                $this->logger->info('Checkin notification skipped: no email.', [
                    'checkoutId' => $checkout->getId(),
                    'memberId' => $member->getId(),
                ]);
            }
            $message = $this->dispatcher->buildCheckinMessage($checkout, $recipient);
            $this->sendToChannels($message);
        } catch (\Throwable $e) {
            $this->logger->error('Checkin notification failed.', [
                'checkoutId' => $checkout->getId(),
                'memberId' => $member->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendToChannels(NotificationMessage $message): void
    {
        foreach ($this->channels as $channel) {
            if ($message->getRecipients() === [] && $channel instanceof MailChannel) {
                continue;
            }
            $channel->send($message);
        }
    }
}
