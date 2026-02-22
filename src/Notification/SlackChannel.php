<?php

namespace App\Notification;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class SlackChannel implements NotificationChannelInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ParameterBagInterface $params,
        private LoggerInterface $logger
    ) {
    }

    public function send(NotificationMessage $message): void
    {
        $enabled = $this->params->has('app.notification.slack.enabled')
            ? (bool) $this->params->get('app.notification.slack.enabled')
            : false;
        if (!$enabled) {
            return;
        }

        $webhookUrl = $this->params->has('app.notification.slack.webhook_url')
            ? (string) $this->params->get('app.notification.slack.webhook_url')
            : '';
        if (trim($webhookUrl) === '') {
            $this->logger->warning('Slack notification enabled but webhook URL is missing.');
            return;
        }

        $slackText = $message->getSlackText();
        if ($slackText !== null && trim($slackText) !== '') {
            $text = trim($slackText);
        } else {
            $text = trim($message->getSubject());
            $body = trim($message->getBody());
            if ($body !== '') {
                $text .= "\n" . $body;
            }
        }

        try {
            $this->httpClient->request('POST', $webhookUrl, [
                'json' => ['text' => $text],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Slack notification failed.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
