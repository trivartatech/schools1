<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class CommunicationPortalNotification extends Notification
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     * 
     * @param array $data Expected keys: 'title', 'message', 'type', 'created_at'
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => $this->data['type'] ?? 'general_update',
            'title' => $this->data['title'] ?? 'New Notification',
            'message' => $this->data['message'] ?? '',
            'created_at' => now()->toIso8601String(),
        ];
    }
}
