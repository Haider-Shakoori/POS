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

Alpine.start();
