/**
 * Singleton di Laravel Echo configurato per Soketi (server Pusher-compatibile).
 *
 * Importa questa istanza solo lato client (usa-la dentro useEffect o
 * in file che non vengono eseguiti lato server Next.js).
 */

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type EchoInstance = any;

let echoInstance: EchoInstance | null = null;

/**
 * Restituisce l'istanza singleton di Laravel Echo.
 * La prima chiamata inizializza la connessione WebSocket a Soketi.
 *
 * @param authToken - Bearer token Sanctum per autenticare i channel privati
 */
export function getEcho(authToken: string): EchoInstance {
  if (echoInstance) {
    return echoInstance;
  }

  // Importazione dinamica necessaria per evitare errori SSR in Next.js
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const Pusher = require('pusher-js');
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const Echo = require('laravel-echo').default ?? require('laravel-echo');

  // Espone Pusher come globale (richiesto da Laravel Echo internamente)
  if (typeof window !== 'undefined') {
    (window as Window & { Pusher?: unknown }).Pusher = Pusher;
  }

  echoInstance = new Echo({
    broadcaster:       'pusher',
    key:               process.env.NEXT_PUBLIC_SOKETI_KEY ?? 'theja-key',
    wsHost:            process.env.NEXT_PUBLIC_SOKETI_HOST ?? '127.0.0.1',
    wsPort:            parseInt(process.env.NEXT_PUBLIC_SOKETI_PORT ?? '6001'),
    wssPort:           parseInt(process.env.NEXT_PUBLIC_SOKETI_PORT ?? '6001'),
    forceTLS:          false,
    enabledTransports: ['ws', 'wss'],
    cluster:           'mt1',
    // Endpoint Laravel per autorizzare i channel privati
    authEndpoint:      `${process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000'}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${authToken}`,
        Accept:        'application/json',
      },
    },
  });

  return echoInstance;
}

/** Disconnette Echo e resetta il singleton (chiamare al logout). */
export function disconnectEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect();
    echoInstance = null;
  }
}
