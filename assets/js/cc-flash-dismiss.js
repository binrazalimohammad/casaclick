/**
 * Dismissible flash banners: click [data-cc-flash-dismiss] removes [data-cc-flash].
 */
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-cc-flash-dismiss]');
    if (!btn) {
        return;
    }
    const root = btn.closest('[data-cc-flash]');
    if (root) {
        root.remove();
    }
});
