document.addEventListener('DOMContentLoaded', () => {
    const search = document.querySelector('[data-inquiry-search]');
    if (search) {
        const rows = Array.from(document.querySelectorAll('[data-inquiry-search-item]'));
        const empty = document.querySelector('[data-inquiry-search-empty]');
        search.addEventListener('input', () => {
            const term = search.value.trim().toLocaleLowerCase();
            let visible = 0;
            rows.forEach(row => {
                const matches = row.dataset.inquirySearchItem.includes(term);
                row.hidden = !matches;
                visible += matches ? 1 : 0;
            });
            if (empty) empty.hidden = visible > 0;
        });
    }

    const messenger = document.querySelector('[data-inquiry-messenger]');
    if (!messenger) return;

    const list = messenger.querySelector('[data-inquiry-messages]');
    const status = messenger.querySelector('[data-inquiry-live-status]');
    const form = messenger.querySelector('[data-inquiry-reply-form]');
    const field = form?.querySelector('textarea[name="message"]');
    const sendButton = form?.querySelector('button[type="submit"]');
    let afterId = Number(messenger.dataset.afterId || 0);
    let pending = false;

    const scrollToLatest = () => { list.scrollTop = list.scrollHeight; };
    const makeActivity = activity => {
        const item = document.createElement('li');
        item.className = `inquiry-message${Number(activity.user_id) === Number(messenger.dataset.currentUserId) ? ' is-own' : ''}`;
        item.dataset.activityId = String(activity.id);
        const bubble = document.createElement('div');
        bubble.className = 'inquiry-message-bubble';
        if (activity.type === 'status_changed') {
            const text = document.createElement('p');
            text.className = 'mb-1';
            text.textContent = `${activity.user_name} updated the inquiry status to ${activity.to_status}.`;
            bubble.append(text);
        } else {
            const author = document.createElement('div');
            author.className = 'inquiry-message-author';
            author.textContent = activity.user_name;
            const text = document.createElement('p');
            text.className = 'mb-1';
            text.textContent = activity.body || '';
            bubble.append(author, text);
        }
        const time = document.createElement('time');
        time.className = 'small';
        time.dateTime = activity.created_at || '';
        time.textContent = activity.created_at_label || '';
        bubble.append(time);
        item.append(bubble);
        return item;
    };

    const updateCounts = data => {
        document.querySelectorAll('[data-unread-inquiries]').forEach(badge => {
            const count = Number(data.unread_inquiries || 0);
            badge.textContent = String(count);
            badge.hidden = count < 1;
        });
        document.querySelectorAll('[data-unread-notifications]').forEach(badge => {
            const count = Number(data.unread_notifications || 0);
            badge.textContent = String(count);
            badge.hidden = count < 1;
        });
    };

    const poll = async () => {
        if (pending || document.hidden) return;
        pending = true;
        try {
            const url = new URL(messenger.dataset.updatesUrl, window.location.href);
            url.searchParams.set('after_id', String(afterId));
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) throw new Error(`Conversation refresh failed (${response.status}).`);
            const data = await response.json();
            const wasNearBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 100;
            const empty = list.querySelector('[data-inquiry-empty]');
            data.activities.forEach(activity => {
                if (Number(activity.id) <= afterId) return;
                empty?.remove();
                list.append(makeActivity(activity));
                afterId = Number(activity.id);
                if (Number(activity.user_id) !== Number(messenger.dataset.currentUserId)) {
                    status.textContent = `New message from ${activity.user_name}.`;
                }
            });
            if (data.status) {
                const badge = messenger.querySelector('[data-inquiry-status]');
                if (badge) badge.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                if (data.status !== 'open' && form) {
                    field.disabled = true;
                    sendButton.disabled = true;
                    status.textContent = 'This conversation is closed and cannot receive new messages.';
                }
            }
            updateCounts(data);
            if (wasNearBottom && data.activities.length) scrollToLatest();
        } catch (error) {
            status.textContent = 'Live updates are temporarily unavailable. The conversation is still safe; try refreshing the page.';
        } finally {
            pending = false;
        }
    };

    form?.addEventListener('submit', async event => {
        event.preventDefault();
        if (!form.reportValidity()) return;
        const originalLabel = sendButton.textContent;
        sendButton.disabled = true;
        sendButton.textContent = 'Sending…';
        status.textContent = '';
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (response.status === 422) {
                const data = await response.json();
                throw new Error(Object.values(data.errors || {}).flat().join(' ') || 'Please check the message and try again.');
            }
            if (!response.ok) throw new Error('Your message could not be sent. Please try again.');
            const data = await response.json();
            field.value = '';
            status.textContent = data.sent === false
                ? 'This message was already sent; a duplicate was not added.'
                : 'Message sent.';
            await poll();
            field.focus();
        } catch (error) {
            status.textContent = error.message || 'Your message could not be sent. Please try again.';
        } finally {
            sendButton.disabled = false;
            sendButton.textContent = originalLabel;
        }
    });

    scrollToLatest();
    poll();
    window.setInterval(poll, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
});
