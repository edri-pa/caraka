<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PingTest implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $pesan) {}

    public function broadcastOn(): array
    {
        return [new Channel('ping-test')];
    }

    public function broadcastAs(): string
    {
        return 'ping.test';
    }
}