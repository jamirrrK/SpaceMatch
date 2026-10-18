document.addEventListener('DOMContentLoaded', () => {
    const placeholder = document.querySelector('meta[name="neutral-image"]')?.content;
    document.querySelectorAll('[data-safe-image], [data-gallery] img').forEach(image => {
        const recover = () => { if (placeholder && image.src !== placeholder) { image.removeAttribute('srcset'); image.src = placeholder; image.alt = 'Property photo unavailable'; } };
        image.addEventListener('error', recover, { once: true });
        if (image.complete && !image.naturalWidth) recover();
    });
    document.querySelectorAll('[data-image-input]').forEach(input => {
        let pending = false, revision = 0;
        input.closest('form').addEventListener('submit', event => { if (pending) event.preventDefault(); });
        input.addEventListener('change', async () => {
            if (!input.files.length || !input.checkValidity() || !window.createImageBitmap || !window.DataTransfer) return;
            pending = true; const current = ++revision;
            const button = input.closest('form').querySelector('button[type=submit]'); button.disabled = true;
            try {
                const result = new DataTransfer();
                for (const file of input.files) {
                    const image = await createImageBitmap(file);
                    const ratio = Math.min(1, 2000 / Math.max(image.width, image.height));
                    const canvas = document.createElement('canvas'); canvas.width = Math.max(1, Math.round(image.width * ratio)); canvas.height = Math.max(1, Math.round(image.height * ratio));
                    canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height); image.close();
                    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/webp', .84));
                    // Preserve originals when conversion is unavailable or not smaller.
                    result.items.add(blob && blob.type === 'image/webp' && blob.size < file.size ? new File([blob], `${file.name.replace(/\.[^.]+$/, '')}.webp`, { type: 'image/webp' }) : file);
                }
                if (current === revision) input.files = result.files;
            } catch { /* Server validation remains mandatory; unsupported conversion preserves original files. */ }
            finally { if (current === revision) { pending = false; button.disabled = false; } }
        });
    });
});
