import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.store('theme', {
    dark: localStorage.getItem('pos-theme') === 'dark',

    init() {
        this.apply();
    },

    toggle() {
        this.dark = ! this.dark;
        localStorage.setItem('pos-theme', this.dark ? 'dark' : 'light');
        this.apply();
    },

    apply() {
        document.documentElement.classList.toggle('dark', this.dark);
    },
});

Alpine.data('asyncLookup', (config) => ({
    endpoint: config.endpoint,
    value: config.initialValue || '',
    query: config.initialLabel || '',
    selectedLabel: config.initialLabel || '',
    selectedMeta: config.initialMeta || '',
    results: [],
    open: false,
    loading: false,
    activeIndex: -1,
    abortController: null,

    focus() {
        this.open = true;

        if (!this.value && this.query.trim().length >= 2) {
            this.search();
        }
    },

    handleInput() {
        if (this.query !== this.selectedLabel) {
            this.value = '';
            this.selectedLabel = '';
            this.selectedMeta = '';
        }

        this.search();
    },

    async search() {
        const term = this.query.trim();

        if (term.length < 2) {
            this.results = [];
            this.activeIndex = -1;
            this.open = true;
            this.loading = false;
            this.abortController?.abort();
            return;
        }

        this.abortController?.abort();
        this.abortController = new AbortController();
        this.loading = true;
        this.open = true;

        try {
            const url = new URL(this.endpoint, window.location.origin);
            url.searchParams.set('q', term);

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: this.abortController.signal,
            });

            if (!response.ok) {
                throw new Error('Lookup request failed.');
            }

            const payload = await response.json();
            this.results = Array.isArray(payload.data) ? payload.data : [];
            this.activeIndex = this.results.length ? 0 : -1;
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.results = [];
                this.activeIndex = -1;
            }
        } finally {
            this.loading = false;
        }
    },

    select(item) {
        this.value = String(item.id);
        this.query = item.label;
        this.selectedLabel = item.label;
        this.selectedMeta = item.meta || '';
        this.results = [];
        this.activeIndex = -1;
        this.open = false;
    },

    clear() {
        this.value = '';
        this.query = '';
        this.selectedLabel = '';
        this.selectedMeta = '';
        this.results = [];
        this.activeIndex = -1;
        this.open = false;
        this.abortController?.abort();
    },

    move(direction) {
        if (!this.open || this.results.length === 0) return;

        this.activeIndex = (this.activeIndex + direction + this.results.length) % this.results.length;
    },

    chooseActive() {
        if (this.open && this.activeIndex >= 0 && this.results[this.activeIndex]) {
            this.select(this.results[this.activeIndex]);
        }
    },

    close() {
        this.open = false;
        this.activeIndex = -1;
    },
}));

Alpine.data('inventoryTargetPicker', (endpoint, initialMode) => ({
    endpoint,
    mode: initialMode,
    query: '',
    results: [],
    rows: [],
    loading: false,
    open: false,
    abortController: null,

    setMode(mode) {
        this.mode = mode;
        this.query = '';
        this.results = [];
        this.rows = [];
        this.open = false;
        this.abortController?.abort();
    },

    async search() {
        const term = this.query.trim();

        if (term.length < 2) {
            this.results = [];
            this.open = term.length > 0;
            this.loading = false;
            this.abortController?.abort();
            return;
        }

        this.abortController?.abort();
        this.abortController = new AbortController();
        this.loading = true;
        this.open = true;

        try {
            const url = new URL(this.endpoint, window.location.origin);
            url.searchParams.set('q', term);
            url.searchParams.set('mode', this.mode);

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: this.abortController.signal,
            });

            if (!response.ok) {
                throw new Error('Inventory lookup request failed.');
            }

            const payload = await response.json();
            this.results = Array.isArray(payload.data) ? payload.data : [];
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.results = [];
            }
        } finally {
            this.loading = false;
        }
    },

    add(item) {
        if (!this.rows.some(row => row.value === item.value)) {
            this.rows.push({ ...item, quantity: '' });
        }

        this.query = '';
        this.results = [];
        this.open = false;
    },

    remove(index) {
        this.rows.splice(index, 1);
    },
}));

Alpine.start();
