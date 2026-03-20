<?php

use App\Models\DeviceSession;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels — Theja
|--------------------------------------------------------------------------
|
| I channel privati richiedono autorizzazione: il server verifica che
| l'utente autenticato abbia il diritto di ascoltare il channel richiesto.
|
| IMPORTANTE: il middleware auth:sanctum è registrato qui per forzare
| l'autenticazione token-based su /broadcasting/auth (default: web).
|
*/

// Registra l'endpoint /broadcasting/auth con auth:sanctum per le API
Broadcast::routes(['middleware' => ['auth:sanctum']]);

/**
 * Channel privato per le notifiche di sessione.
 *
 * Accessibile solo dall'utente che è proprietario della DeviceSession.
 * Il frontend si iscrive a "session.{currentSessionId}" dopo il login.
 */
Broadcast::channel('session.{sessionId}', function ($user, string $sessionId) {
    $session = DeviceSession::find($sessionId);

    return $session && (int) $session->user_id === (int) $user->id;
});
