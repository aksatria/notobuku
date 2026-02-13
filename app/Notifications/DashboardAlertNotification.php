<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DashboardAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public array $payload = []
    ) {}

    public function via($notifiable)
    {
        // in-app notification (database)
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'payload' => $this->payload,
        ];
    }
}
