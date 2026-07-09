<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

use App\Models\RefillReminder;

class RefillReminderEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $product;
    public $message;
    public $reminder;

    public function __construct(\App\Models\User $user, \App\Models\Product $product, RefillReminder $reminder = null)
    {
        $this->user = $user;
        $this->product = $product;
        $this->reminder = $reminder;
        $this->message = "Reminder: It's time to refill your {$product->name}. Stay healthy!";
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->user->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'refill-reminder';
    }
}
