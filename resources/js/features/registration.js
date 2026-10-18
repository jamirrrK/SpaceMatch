document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.querySelector('#registration-chooser');
    let trigger;
    document.querySelectorAll('a[data-registration-choice], a[href]').forEach(link => {
        const url = new URL(link.href, location.href);
        if (!dialog || url.origin !== location.origin || !url.pathname.endsWith('/register')) return;
        link.addEventListener('click', event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault(); trigger = link; dialog.showModal();
        });
    });
    dialog?.querySelector('[data-registration-close]').addEventListener('click', () => dialog.close());
    dialog?.addEventListener('close', () => trigger?.focus());
});
