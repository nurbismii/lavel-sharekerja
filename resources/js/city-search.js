document.querySelectorAll('[data-city-search]').forEach(widget => {
    const input = widget.querySelector('[data-city-input]');
    const list = widget.querySelector('[role="listbox"]');
    const status = widget.querySelector('[role="status"]');
    let timer;
    let controller;
    let active = -1;

    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };
    const message = text => {
        status.textContent = text;
        status.hidden = !text;
    };
    const select = option => {
        input.value = option.dataset.value;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        close();
        message('');
    };
    const search = async () => {
        const query = input.value.trim();
        if (query.length < 2 || document.activeElement !== input) return;
        const request = new AbortController();
        controller = request;
        const timeout = setTimeout(() => request.abort('timeout'), 15000);
        const url = new URL(widget.dataset.url, window.location.href);
        url.searchParams.set('q', query);
        message('Mencari kota…');
        input.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { signal: request.signal, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('City search unavailable');
            const result = await response.json();
            if (!Array.isArray(result.data)) throw new Error('Invalid city response');
            if (input.value.trim() !== query || document.activeElement !== input || request.signal.aborted) return;
            list.replaceChildren();
            result.data.forEach((city, index) => {
                const option = document.createElement('li');
                option.id = `${list.id}-${index}`;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.dataset.value = city.value;
                const name = document.createElement('strong');
                name.textContent = city.name;
                const province = document.createElement('span');
                province.textContent = city.province;
                option.append(name, province);
                list.append(option);
            });
            list.hidden = result.data.length === 0;
            input.setAttribute('aria-expanded', String(!list.hidden));
            message(result.data.length ? '' : 'Kota tidak ditemukan. Anda tetap dapat mengetik lokasi secara manual.');
        } catch {
            if (!request.signal.aborted || request.signal.reason === 'timeout') {
                close();
                message('Saran kota belum tersedia. Ketik lokasi secara manual atau coba lagi.');
            }
        } finally {
            clearTimeout(timeout);
            if (controller === request) input.removeAttribute('aria-busy');
        }
    };
    const schedule = () => {
        clearTimeout(timer);
        controller?.abort();
        close();
        message('');
        input.removeAttribute('aria-busy');
        if (input.value.trim().length >= 2) timer = setTimeout(search, 300);
    };
    input.addEventListener('input', schedule);
    input.addEventListener('focus', schedule);
    input.addEventListener('blur', () => {
        clearTimeout(timer);
        controller?.abort();
        close();
        message('');
        input.removeAttribute('aria-busy');
    });
    list.addEventListener('pointerdown', event => {
        const option = event.target.closest('[role="option"]');
        if (!option) return;
        event.preventDefault();
        select(option);
    });
    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            clearTimeout(timer);
            controller?.abort();
            close();
            message('');
            return;
        }
        if (list.hidden || !list.children.length) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            active = active < 0 ? (direction === 1 ? 0 : list.children.length - 1) : (active + direction + list.children.length) % list.children.length;
            [...list.children].forEach((option, index) => option.setAttribute('aria-selected', String(index === active)));
            input.setAttribute('aria-activedescendant', list.children[active].id);
            list.children[active].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            select(list.children[active]);
        }
    });
});
