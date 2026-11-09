import './bootstrap';

window.erpShell = () => ({
    collapsed: false,
    menuOpen: false,
    mobile: matchMedia('(max-width: 1023px)').matches,
    init() {
        try {
            this.collapsed = localStorage.getItem('erp.sidebar.collapsed') === 'true';
        } catch (e) {}
        this.media = matchMedia('(max-width: 1023px)');
        this.listener = (e) => {
            this.mobile = e.matches;
            if (!this.mobile) this.menuOpen = false;
        };
        this.media.addEventListener('change', this.listener);
    },
    destroy() {
        this.media?.removeEventListener('change', this.listener);
    },
    toggleSidebar() {
        this.collapsed = !this.collapsed;
        try {
            localStorage.setItem('erp.sidebar.collapsed', String(this.collapsed));
        } catch (e) {}
    },
    openMenu() {
        this.menuOpen = true;
    },
    closeMenu() {
        this.menuOpen = false;
    }
});
