<?php

namespace Elastico\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueryStarted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $query_identifier;

    public $query_name;

    /**
     * Create a new event instance.
     *
     * @param int   $user_id
     * @param mixed $query_name
     * @param mixed $query_identifier
     */
    public function __construct($query_identifier, $query_name)
    {
        $this->query_identifier = $query_identifier;
        $this->query_name = $query_name;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array|Channel
     */
    public function broadcastOn()
    {
        // return new PrivateChannel('channel-name');
    }
}
