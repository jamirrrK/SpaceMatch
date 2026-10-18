document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-location-selectors]').forEach(async group => {
        const kinds = ['region', 'province', 'city', 'barangay'];
        const select = kind => group.querySelector(`[data-location-kind="${kind}"]`);
        async function populate(kind, parent, restore = false) {
            const node = select(kind); node.disabled = true;
            const url = new URL(group.dataset.endpoint, location.href);
            url.searchParams.set('kind', kind); if (parent) url.searchParams.set('parent', parent);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Location reference data is unavailable. Use the text location search.');
            const { data } = await response.json();
            node.replaceChildren(new Option(kind === 'province' ? 'No province / independent city' : 'Any / not selected', ''));
            data.forEach(item => node.add(new Option(item.name, item.code)));
            if (restore) node.value = node.dataset.selected;
            node.disabled = false;
        }
        async function cascade(from, restore = false) {
            if (from < 0) await populate('region', null, restore);
            if (from < 1) await populate('province', select('region').value, restore);
            if (from < 2) await populate('city', select('province').value || select('region').value, restore);
            if (from < 3) await populate('barangay', select('city').value, restore);
        }
        try {
            await cascade(-1, true);
            kinds.forEach((kind, index) => select(kind).addEventListener('change', () => {
                cascade(index).catch(error => { group.querySelector('[data-location-status]').textContent = error.message; });
            }));
        } catch (error) { group.querySelector('[data-location-status]').textContent = error.message; }
    });
});
