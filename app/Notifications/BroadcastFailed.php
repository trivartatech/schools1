<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class BroadcastFailed extends Notification
{
    protected $title;
    protected $error;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $title, string $error)
    {
        $this->title = $title;
        $this->error = $error;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title'   => "Broadcast Failed: {$this->title}",
            'message' => $this->error,
            'type'    => 'error',
            'action'  => '/school/utility/announcements',
        ];
    }
}
