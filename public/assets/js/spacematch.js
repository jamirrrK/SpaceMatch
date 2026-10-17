document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.offcanvas-lg, .offcanvas-xl').forEach(drawer => {
        const triggers = document.querySelectorAll(`[data-bs-toggle="offcanvas"][data-bs-target="#${drawer.id}"]`);
        const expanded = value => triggers.forEach(trigger => trigger.setAttribute('aria-expanded', String(value)));
        drawer.addEventListener('show.bs.offcanvas', () => expanded(true));
        drawer.addEventListener('hidden.bs.offcanvas', () => expanded(false));
        drawer.addEventListener('click', event => {
            if (!drawer.classList.contains('show')) return;
            const replay = event.target.closest('[data-tutorial-replay]');
            const link = event.target.closest('a[href]');
            if (!replay && !link) return;
            if (replay) {
                // Open the tutorial after the drawer releases its focus trap.
                event.stopImmediatePropagation();
                drawer.addEventListener('hidden.bs.offcanvas', () => replay.click(), {once:true});
            }
            window.bootstrap?.Offcanvas.getInstance(drawer)?.hide();
        }, true);
    });

    const otpInput = document.querySelector('[data-otp-input]');
    otpInput?.addEventListener('input', () => {
        otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6);
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
