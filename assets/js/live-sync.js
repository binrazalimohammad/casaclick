/**
 * Polls /sync/feed while logged in; reloads when revision changes (mobile ↔ web, same DB).
 * Disabled by default — enable via data-live-sync="1" on <body> in base.html.twig live_sync_attributes block.
 */
(function () {
    if (window.__ccLiveSyncActive) {
        return;
    }

    const body = document.body;
    if (!body || body.dataset.liveSync !== '1') {
        return;
    }

    const feedUrl = body.dataset.liveSyncUrl;
    if (!feedUrl) {
        return;
    }

    window.__ccLiveSyncActive = true;

    const intervalMs = Math.max(3000, parseInt(body.dataset.liveSyncInterval || '5000', 10) || 5000);
    let lastRevision = null;
    let inFlight = false;
    let paused = false;

    body.addEventListener(
        'focusin',
        function (e) {
            const t = e.target;
            if (
                t &&
                (t.matches('input, textarea, select') ||
                    t.closest('form[data-live-sync-pause]'))
            ) {
                paused = true;
            }
        },
        true,
    );
    body.addEventListener(
        'focusout',
        function (e) {
            const t = e.target;
            if (t && t.matches('input, textarea, select')) {
                window.setTimeout(function () {
                    if (!body.querySelector('input:focus, textarea:focus, select:focus')) {
                        paused = false;
                    }
                }, 200);
            }
        },
        true,
    );

    function reloadForSync() {
        if (document.visibilityState === 'hidden') {
            window.__ccPendingLiveReload = true;
            return;
        }
        window.location.reload();
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible' && window.__ccPendingLiveReload) {
            window.__ccPendingLiveReload = false;
            window.location.reload();
        }
    });

    function poll() {
        if (inFlight || paused) {
            return;
        }
        inFlight = true;
        fetch(feedUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data || !data.success) {
                    return;
                }
                if (lastRevision !== null && data.revision !== lastRevision) {
                    reloadForSync();
                    return;
                }
                lastRevision = data.revision;
            })
            .catch(function () {})
            .finally(function () {
                inFlight = false;
            });
    }

    poll();
    window.setInterval(poll, intervalMs);
})();
