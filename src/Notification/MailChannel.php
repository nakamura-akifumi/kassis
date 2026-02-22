<?php

namespace App\Notification;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class MailChannel implements NotificationChannelInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private ParameterBagInterface $params
    ) {
    }

    public function send(NotificationMessage $message): void
    {
        $fromEmail = (string) $this->params->get('app.notification.from_email');
        $fromName = $this->params->has('app.notification.from_name')
            ? (string) $this->params->get('app.notification.from_name')
            : '';

        $email = (new Email())
            ->from($fromName !== '' ? sprintf('%s <%s>', $fromName, $fromEmail) : $fromEmail)
            ->subject(trim($message->getSubject()))
            ->text(rtrim($message->getBody()) . "\n");

        foreach ($message->getRecipients() as $recipient) {
            $email->addTo($recipient);
        }

        $this->mailer->send($email);
    }
}
