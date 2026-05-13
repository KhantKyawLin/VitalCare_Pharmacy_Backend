<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PromotionAlert implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $promotion;

    public function __construct($promotion)
    {
        $this->promotion = $promotion;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('admin-alerts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'promotion-created';
    }
}
