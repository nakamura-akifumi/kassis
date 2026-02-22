<?php

namespace App\Notification;

interface NotificationChannelInterface
{
    public function send(NotificationMessage $message): void;
}
