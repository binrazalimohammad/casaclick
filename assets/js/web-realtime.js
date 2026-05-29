/**
 * Socket.IO for logged-in web pages — instant updates without full page reload.
 * Requires data-realtime-session-url on <body> (see base.html.twig).
 */
import { io } from 'socket.io-client';

(function () {
    if (window.__ccWebRealtimeStarted) {
        return;
    }

    const body = document.body;
    const sessionUrl = body?.dataset?.realtimeSessionUrl;
    if (!sessionUrl) {
        return;
    }

    window.__ccWebRealtimeStarted = true;

    function notifyDataChanged(detail) {
        window.dispatchEvent(
            new CustomEvent('cc:sync-revision', {
                detail: detail || {},
            }),
        );
    }

    fetch(sessionUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) {
            return r.json();
        })
        .then(function (json) {
            if (!json?.success || !json.realtimeOrigin || !json.token) {
                return;
            }

            const httpUrl = json.realtimeOrigin.replace(/\/$/, '');
            const socket = io(httpUrl, {
                path: '/notifications',
                transports: ['websocket'],
                auth: { token: json.token },
                reconnection: true,
                reconnectionDelay: 4000,
            });

            socket.on('auth_ok', function () {
                window.__ccRealtimeActive = true;
                const pill = document.querySelector('.live-sync-pill');
                if (pill) {
                    pill.title = 'Realtime connected — updates without refresh';
                }
            });

            socket.on('disconnect', function () {
                window.__ccRealtimeActive = false;
            });

            socket.on('notification', function () {
                notifyDataChanged({ source: 'socket', type: 'notification' });
            });

            socket.on('order_updated', function (payload) {
                notifyDataChanged({ source: 'socket', type: 'order_updated', payload: payload });
            });

            socket.on('booking_updated', function (payload) {
                notifyDataChanged({ source: 'socket', type: 'booking_updated', payload: payload });
            });
        })
        .catch(function () {
            /* polling fallback via live-sync.js */
        });
})();
