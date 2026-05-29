/**
 * View Bookings (admin) — read-only rows refresh in place when landlords approve on Applications.
 */
(function () {
    const root = document.getElementById('admin-bookings-root');
    if (!root) {
        return;
    }

    const dataUrl = root.dataset.bookingsDataUrl;
    const tbody = document.getElementById('admin-bookings-tbody');
    if (!dataUrl || !tbody) {
        return;
    }

    window.__ccSkipFullReload = true;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function renderRow(b) {
        return (
            '<tr data-booking-id="' +
            escapeHtml(b.id) +
            '">' +
            '<td>' +
            escapeHtml(b.id) +
            '</td>' +
            '<td>' +
            escapeHtml(b.tenant) +
            '</td>' +
            '<td>' +
            escapeHtml(b.listing) +
            '</td>' +
            '<td>' +
            escapeHtml(b.landlord) +
            '</td>' +
            '<td><span class="badge">' +
            escapeHtml(b.statusLabel) +
            '</span></td>' +
            '</tr>'
        );
    }

    function patchOrRender(bookings) {
        if (!bookings.length) {
            tbody.innerHTML =
                '<tr><td colspan="5" class="text-center text-muted">No bookings found.</td></tr>';
            return;
        }

        const known = new Set();
        for (const b of bookings) {
            known.add(String(b.id));
            const row = tbody.querySelector('tr[data-booking-id="' + b.id + '"]');
            if (row) {
                const badge = row.querySelector('td:nth-child(5) .badge');
                if (badge) {
                    badge.textContent = b.statusLabel;
                }
            } else {
                tbody.insertAdjacentHTML('beforeend', renderRow(b));
            }
        }

        tbody.querySelectorAll('tr[data-booking-id]').forEach(function (row) {
            const id = row.getAttribute('data-booking-id');
            if (id && !known.has(id)) {
                row.remove();
            }
        });
    }

    function refreshBookingsTable() {
        fetch(dataUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) {
                return r.json();
            })
            .then(function (json) {
                if (!json?.success) {
                    return;
                }
                patchOrRender(json.bookings || []);
            })
            .catch(function () {});
    }

    window.addEventListener('cc:sync-revision', refreshBookingsTable);

    const feedUrl = root.dataset.bookingsFeedUrl;
    if (feedUrl) {
        let lastRevision = null;
        window.setInterval(function () {
            fetch(feedUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) {
                    return r.json();
                })
                .then(function (json) {
                    if (!json?.success) {
                        return;
                    }
                    if (lastRevision !== null && json.revision !== lastRevision) {
                        refreshBookingsTable();
                    }
                    lastRevision = json.revision;
                })
                .catch(function () {});
        }, 5000);
    }
})();
