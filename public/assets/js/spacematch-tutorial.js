document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.querySelector('[data-tutorial-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const welcome = dialog.querySelector('[data-tutorial-welcome]');
    const tour = dialog.querySelector('[data-tutorial-tour]');
    const panels = Array.from(dialog.querySelectorAll('[data-tutorial-panel]'));
    const chapterButtons = Array.from(dialog.querySelectorAll('[data-tutorial-select]'));
    const progress = dialog.querySelector('[data-tutorial-progress]');
    const previous = dialog.querySelector('[data-tutorial-previous]');
    const next = dialog.querySelector('[data-tutorial-next]');
    const finish = dialog.querySelector('[data-tutorial-finish]');
    const welcomeTitle = dialog.querySelector('#tutorial-welcome-title');
    const tourTitle = dialog.querySelector('#tutorial-tour-title');
    const permanentKey = 'spacematch.tutorial.dismissed.v1';
    const sessionKey = 'spacematch.tutorial.skipped.v1';
    let activeChapter = 0;
    let returnFocus = null;

    const hasCookie = key => {
        try {
            return document.cookie.split(';').some(cookie => cookie.trim() === `${encodeURIComponent(key)}=1`);
        } catch {
            return false;
        }
    };

    const readFlag = (storageName, key) => {
        try {
            if (window[storageName].getItem(key) === '1') return true;
        } catch {
            return hasCookie(key);
        }

        return hasCookie(key);
    };

    const writeCookie = (key, persistent) => {
        const maxAge = persistent ? '; Max-Age=31536000' : '';
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        try {
            document.cookie = `${encodeURIComponent(key)}=1; Path=/; SameSite=Lax${maxAge}${secure}`;
        } catch {
            // The dialog remains usable when browser storage is disabled.
        }
    };

    const writeFlag = (storageName, key, persistent = false) => {
        try {
            window[storageName].setItem(key, '1');
            if (storageName === 'sessionStorage') writeCookie(key, false);
            return;
        } catch {
            writeCookie(key, persistent);
        }
    };

    const showChapter = index => {
        activeChapter = Math.max(0, Math.min(index, panels.length - 1));
        panels.forEach((panel, panelIndex) => { panel.hidden = panelIndex !== activeChapter; });
        chapterButtons.forEach((button, buttonIndex) => {
            const isActive = buttonIndex === activeChapter;
            button.classList.toggle('is-active', isActive);
            if (isActive) button.setAttribute('aria-current', 'step');
            else button.removeAttribute('aria-current');
        });
        progress.textContent = `Chapter ${activeChapter + 1} of ${panels.length}`;
        previous.disabled = activeChapter === 0;
        next.hidden = activeChapter === panels.length - 1;
        finish.hidden = activeChapter !== panels.length - 1;
    };

    const openDialog = startInTour => {
        welcome.hidden = startInTour;
        tour.hidden = !startInTour;
        showChapter(0);
        if (!dialog.open) {
            returnFocus = document.activeElement?.matches('[data-tutorial-replay]') ? document.activeElement : document.getElementById('main-content');
            dialog.showModal();
        }
        window.requestAnimationFrame(() => {
            (startInTour ? tourTitle : dialog.querySelector('[data-tutorial-start]'))?.focus();
        });
    };

    const dismissForSession = () => {
        writeFlag('sessionStorage', sessionKey);
        if (dialog.open) dialog.close();
    };

    dialog.querySelector('[data-tutorial-start]')?.addEventListener('click', () => openDialog(true));

    document.querySelectorAll('[data-tutorial-replay]').forEach(button => {
        button.addEventListener('click', () => openDialog(true));
    });

    dialog.querySelectorAll('[data-tutorial-skip], [data-tutorial-close]').forEach(button => {
        button.addEventListener('click', dismissForSession);
    });

    dialog.querySelectorAll('a[href]').forEach(link => {
        link.addEventListener('click', () => writeFlag('sessionStorage', sessionKey));
    });

    dialog.querySelectorAll('[data-tutorial-never]').forEach(button => {
        button.addEventListener('click', () => {
            writeFlag('localStorage', permanentKey, true);
            dismissForSession();
        });
    });

    chapterButtons.forEach(button => {
        button.addEventListener('click', () => showChapter(Number(button.dataset.tutorialSelect)));
    });
    previous?.addEventListener('click', () => showChapter(activeChapter - 1));
    next?.addEventListener('click', () => showChapter(activeChapter + 1));
    finish?.addEventListener('click', dismissForSession);

    dialog.addEventListener('cancel', () => writeFlag('sessionStorage', sessionKey));
    dialog.addEventListener('close', () => {
        welcome.hidden = false;
        tour.hidden = true;
        window.requestAnimationFrame(() => returnFocus?.focus({ preventScroll: true }));
    });

    if (!readFlag('localStorage', permanentKey) && !readFlag('sessionStorage', sessionKey)) {
        window.requestAnimationFrame(() => openDialog(false));
    }
});
