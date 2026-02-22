<?php

namespace App\Notification;

final class NotificationMessage
{
    /**
     * @param string[] $recipients
     */
    public function __construct(
        private string $subject,
        private string $body,
        private array $recipients,
        private ?string $slackText = null
    ) {
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return string[]
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function getSlackText(): ?string
    {
        return $this->slackText;
    }
}
