document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.offcanvas-lg, .offcanvas-xl').forEach(drawer => {
        const triggers = document.querySelectorAll(`[data-bs-toggle="offcanvas"][data-bs-target="#${drawer.id}"]`);
        const expanded = value => triggers.forEach(trigger => trigger.setAttribute('aria-expanded', String(value)));
        drawer.addEventListener('show.bs.offcanvas', () => expanded(true));
        drawer.addEventListener('hidden.bs.offcanvas', () => expanded(false));
        drawer.addEventListener('click', event => {
            if (!drawer.classList.contains('show')) return;
            if (!event.target.closest('a[href]')) return;
            window.bootstrap?.Offcanvas.getInstance(drawer)?.hide();
        }, true);
    });

    const otpInput = document.querySelector('[data-otp-input]');
    otpInput?.addEventListener('input', () => {
        otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6);
    });

    document.querySelectorAll('form').forEach(form => {
        const button = form.querySelector('[data-otp-submit]');
        if (!button) return;
        form.addEventListener('submit', () => {
            if (button.disabled) return;
            button.disabled = true;
            button.textContent = button.formAction.endsWith('/resend') ? 'Sending...' : 'Verifying...';
        });
    });

    const expiryNote = document.querySelector('[data-otp-expires-at]');
    if (expiryNote?.dataset.otpExpiresAt) {
        const expiresAt = Number(expiryNote.dataset.otpExpiresAt) * 1000;
        const updateExpiry = () => {
            const remaining = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
            expiryNote.textContent = remaining
                ? `Your code expires in ${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}.`
                : 'This code has expired. Request a new code to continue.';
        };
        updateExpiry();
        window.setInterval(updateExpiry, 1000);
    }

    const resendButton = document.querySelector('[data-otp-resend-at]');
    if (resendButton?.dataset.otpResendAt) {
        const availableAt = (Number(resendButton.dataset.otpResendAt) + 60) * 1000;
        const label = resendButton.textContent.trim();
        const updateResend = () => {
            const remaining = Math.max(0, Math.ceil((availableAt - Date.now()) / 1000));
            resendButton.disabled = remaining > 0;
            resendButton.textContent = remaining ? `Resend in ${remaining}s` : label;
        };
        updateResend();
        window.setInterval(updateResend, 1000);
    }

    document.querySelectorAll('[data-toggle-password]').forEach(button => {
        const input = document.getElementById(button.dataset.togglePassword);
        if (!input) return;

        button.addEventListener('click', () => {
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.textContent = visible ? 'Hide' : 'Show';
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', `${visible ? 'Hide' : 'Show'} ${input.name.replaceAll('_', ' ')}`);
        });
    });

    document.querySelectorAll('[data-image-input]').forEach(input => {
        const previews = document.getElementById(input.dataset.imageInput);
        const feedback = document.getElementById(input.dataset.imageFeedback);
        let objectUrls = [];
        input.addEventListener('change', () => {
            objectUrls.forEach(url => URL.revokeObjectURL(url));
            objectUrls = [];
            previews.replaceChildren();
            const files = Array.from(input.files);
            const errors = [];
            if (files.length > 10) errors.push('Choose no more than 10 images at a time.');
            if (files.reduce((total, file) => total + file.size, 0) > 30 * 1024 * 1024) errors.push('The total upload size must be 30 MB or less.');
            files.forEach(file => {
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) errors.push(`${file.name}: choose a JPG, PNG, or WebP image.`);
                if (file.size > 5 * 1024 * 1024) errors.push(`${file.name}: the maximum image size is 5 MB.`);
                const figure = document.createElement('figure');
                figure.className = 'mb-0';
                const img = document.createElement('img');
                const caption = document.createElement('figcaption');
                const url = URL.createObjectURL(file);
                objectUrls.push(url);
                img.src = url;
                img.alt = `Preview of ${file.name}`;
                caption.textContent = file.name;
                figure.append(img, caption);
                previews.append(figure);
            });
            feedback.textContent = errors.join(' ');
            feedback.classList.toggle('d-none', !errors.length);
            input.setCustomValidity(errors.length ? errors[0] : '');
        });
    });
    document.querySelectorAll('[data-gallery-target]').forEach(button => {
        button.addEventListener('click', () => {
            const main = document.getElementById(button.dataset.galleryTarget);
            if (!main) return;
            main.src = button.dataset.gallerySrc;
            main.alt = button.dataset.galleryAlt;
            button.closest('[data-gallery]').querySelectorAll('[data-gallery-target]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
        });
    });

    document.querySelectorAll('[data-home-carousel]').forEach(carousel => {
        const slides = [...carousel.querySelectorAll('[data-carousel-slide]')];
        const indicators = [...carousel.querySelectorAll('[data-carousel-indicator]')];
        const pause = carousel.querySelector('[data-carousel-pause]');
        const announcement = carousel.querySelector('[data-carousel-announcement]');
        const motion = matchMedia('(prefers-reduced-motion: reduce)');
        let active = Math.max(0, slides.findIndex(slide => slide.classList.contains('is-active')));
        let paused = motion.matches, focused = false, start = null, swiped = false;
        slides.forEach(slide => {
            const photo = slide.querySelector('[data-carousel-photo]');
            const recover = () => {
                photo.hidden = true;
                slide.querySelector('[data-carousel-fallback]').hidden = false;
                slide.querySelector('[data-carousel-image-label]').textContent = 'Illustrative image | Available listing';
            };
            photo?.addEventListener('error', recover, { once: true });
            if (photo?.complete && !photo.naturalWidth) recover();
        });
        if (slides.length < 2) return;
        const updatePauseButton = () => {
            pause.textContent = paused ? 'Resume slides' : 'Pause slides';
            pause.setAttribute('aria-pressed', String(paused));
        };
        const render = (index, announce = false) => {
            active = (index + slides.length) % slides.length;
            slides.forEach((slide, position) => {
                const selected = position === active;
                slide.hidden = !selected;
                slide.tabIndex = selected ? 0 : -1;
                slide.setAttribute('aria-hidden', String(!selected));
                slide.classList.toggle('is-active', selected);
                if (selected) slide.querySelectorAll('img').forEach(image => { image.loading = 'eager'; });
            });
            indicators.forEach((button, index) => button.setAttribute('aria-current', String(index === active)));
            if (announce && announcement) announcement.textContent = `Featured listing ${active + 1} of ${slides.length}.`;
        };
        carousel.querySelector('[data-carousel-previous]')?.addEventListener('click', () => render(active - 1, true));
        carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => render(active + 1, true));
        indicators.forEach((button, index) => button.addEventListener('click', () => render(index, true)));
        pause?.addEventListener('click', () => { paused = !paused; updatePauseButton(); });
        carousel.addEventListener('focusin', () => { focused = true; });
        carousel.addEventListener('focusout', event => { focused = carousel.contains(event.relatedTarget); });
        carousel.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const onSlide = event.target.matches('[data-carousel-slide]');
            render(event.key === 'Home' ? 0 : event.key === 'End' ? slides.length - 1 : active + (event.key === 'ArrowLeft' ? -1 : 1), true);
            if (onSlide) slides[active].focus();
        });
        carousel.addEventListener('touchstart', event => {
            if (event.touches.length !== 1 || event.target.closest('button')) { start = null; return; }
            start = { x: event.touches[0].clientX, y: event.touches[0].clientY };
            swiped = false;
        }, { passive: true });
        carousel.addEventListener('touchend', event => {
            if (!start || !event.changedTouches.length) return;
            const dx = event.changedTouches[0].clientX - start.x, dy = event.changedTouches[0].clientY - start.y;
            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) { render(active + (dx < 0 ? 1 : -1), true); swiped = true; setTimeout(() => { swiped = false; }, 400); }
            start = null;
        }, { passive: true });
        carousel.addEventListener('touchcancel', () => { start = null; });
        carousel.addEventListener('click', event => { if (swiped) { event.preventDefault(); swiped = false; } }, true);
        motion.addEventListener('change', () => { paused = motion.matches; updatePauseButton(); });
        updatePauseButton();
        render(active);
        window.setInterval(() => {
            if (!paused && !focused && !document.hidden) render(active + 1);
        }, 6000);
    });

    const dialog = document.getElementById('confirmation-dialog');
    let pendingForm = null;
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.confirmed === 'true') { delete form.dataset.confirmed; return; }
            if (!dialog || typeof dialog.showModal !== 'function') {
                if (!window.confirm(form.dataset.confirm)) event.preventDefault();
                return;
            }
            event.preventDefault();
            pendingForm = form;
            document.getElementById('confirmation-message').textContent = form.dataset.confirm;
            dialog.showModal();
        });
    });
    dialog?.querySelector('[data-confirm-cancel]').addEventListener('click', () => { pendingForm = null; dialog.close(); });
    dialog?.querySelector('[data-confirm-accept]').addEventListener('click', () => {
        const form = pendingForm;
        pendingForm = null;
        dialog.close();
        if (form) { form.dataset.confirmed = 'true'; form.requestSubmit(); }
    });

    document.querySelectorAll('.sidebar-link.active, .navbar .nav-link.active').forEach(link => {
        link.setAttribute('aria-current', 'page');
    });

    // Run after confirmation listeners so opening a dialog never locks the form.
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', event => {
            if (event.defaultPrevented || !form.checkValidity()) return;
            if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            const submitter = event.submitter;
            if (submitter) {
                submitter.dataset.originalLabel = submitter.textContent;
                submitter.textContent = form.method.toLowerCase() === 'get' ? 'Searching…' : 'Please wait…';
                submitter.setAttribute('aria-disabled', 'true');
            }
        });
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-submitting]').forEach(form => {
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            form.querySelectorAll('[data-original-label]').forEach(button => {
                button.textContent = button.dataset.originalLabel;
                button.removeAttribute('aria-disabled');
                delete button.dataset.originalLabel;
            });
        });
    });
});
