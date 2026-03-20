<?php

namespace App\Events;

use App\Models\DeviceSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento broadcast che notifica il client quando la propria sessione
 * viene invalidata (logout remoto o limite sessioni superato).
 *
 * Channel: private session.{session_id}
 * Solo l'utente proprietario della sessione può ascoltarlo (vedi channels.php).
 */
class SessionInvalidated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $sessionId;
    public string $reason;

    public function __construct(DeviceSession $session, string $reason = 'logged_out_remotely')
    {
        $this->sessionId = $session->id;
        $this->reason    = $reason;
    }

    /**
     * Il channel privato è specifico per la sessione invalidata.
     * Il frontend si iscrive a session.{currentSessionId}.
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel("session.{$this->sessionId}");
    }

    /**
     * Il nome dell'evento inviato al frontend via WebSocket.
     */
    public function broadcastAs(): string
    {
        return 'SessionInvalidated';
    }

    /**
     * Payload dell'evento.
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'reason'     => $this->reason,
        ];
    }
}
