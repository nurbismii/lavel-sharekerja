document.querySelectorAll('[data-job-regions]').forEach(async widget => {
    const country = widget.querySelector('[name="country_code"]');
    const province = widget.querySelector('[name="province"]');
    const location = widget.querySelector('[name="location"]');
    const provinceChoice = widget.querySelector('[data-region-province]');
    const cityChoice = widget.querySelector('[data-region-city]');
    const dropdown = widget.querySelector('[data-region-dropdown]');
    const manualFields = widget.querySelector('[data-region-manual]');
    const manualToggle = widget.querySelector('[data-region-manual-toggle]');
    const status = widget.querySelector('[data-region-status]');
    const normalize = value => value.trim().toLocaleLowerCase('id');
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
    status.hidden = false;
    status.textContent = 'Memuat daftar provinsi dan kota…';
    try {
        const response = await fetch(widget.dataset.url, { signal: controller.signal, headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Regions unavailable');
        const result = await response.json();
        if (!Array.isArray(result.data) || !result.data.length) throw new Error('Invalid regions');
        const cities = result.data;
        const provinces = [...new Set(cities.map(city => city.province))].sort((a, b) => a.localeCompare(b, 'id'));
        provinceChoice.replaceChildren(new Option('Pilih provinsi', ''), ...provinces.map(name => new Option(name, name)));

        const fillCities = () => {
            const available = cities.filter(city => city.province === provinceChoice.value).sort((a, b) => a.name.localeCompare(b.name, 'id'));
            cityChoice.replaceChildren(new Option(provinceChoice.value ? 'Pilih kota / kabupaten' : 'Pilih provinsi terlebih dahulu', ''), ...available.map(city => new Option(city.name, city.id)));
        };
        const applySelection = () => {
            // shortcut: Names preserve legacy matching, store region IDs when cities and regencies must be distinguished.
            province.value = provinceChoice.value;
            location.value = cities.find(city => city.id === cityChoice.value)?.value || '';
        };
        const renderMode = () => {
            const international = country.value !== 'ID';
            if (international) manualToggle.checked = true;
            manualToggle.disabled = international;
            const manual = international || manualToggle.checked;
            dropdown.hidden = manual;
            manualFields.hidden = !manual;
            provinceChoice.disabled = manual;
            cityChoice.disabled = manual || !provinceChoice.value;
            provinceChoice.required = !manual;
            cityChoice.required = !manual;
            location.required = manual;
        };

        const matches = cities.filter(city => (normalize(city.value) === normalize(location.value) || normalize(city.name) === normalize(location.value)) && (!province.value || normalize(city.province) === normalize(province.value)));
        const selectedCity = matches.length === 1 ? matches[0] : null;
        const selectedProvince = provinces.find(name => normalize(name) === normalize(province.value));
        provinceChoice.value = selectedCity?.province || selectedProvince || '';
        fillCities();
        cityChoice.value = selectedCity?.id || '';
        manualToggle.checked = country.value !== 'ID' || Boolean(location.value && !selectedCity) || Boolean(province.value && !selectedProvince && !selectedCity);
        widget.querySelector('[data-region-mode]').hidden = false;
        renderMode();
        if (!manualToggle.checked) applySelection();
        status.hidden = true;

        provinceChoice.addEventListener('change', () => {
            fillCities();
            applySelection();
            renderMode();
        });
        cityChoice.addEventListener('change', applySelection);
        manualToggle.addEventListener('change', () => {
            if (!manualToggle.checked && country.value === 'ID') applySelection();
            renderMode();
        });
        country.addEventListener('input', renderMode);
    } catch {
        status.textContent = 'Daftar wilayah belum tersedia. Isi provinsi dan kota secara manual.';
    } finally {
        clearTimeout(timeout);
    }
});
