const pageShell = document.getElementById('pageShell');
const menuToggle = document.getElementById('menuToggle');
const sidebarBackdrop = document.getElementById('sidebarBackdrop');

if (pageShell && menuToggle) {
    const mobileQuery = window.matchMedia('(max-width: 760px)');

    const setExpandedState = (isExpanded) => {
        menuToggle.setAttribute('aria-expanded', String(isExpanded));
        menuToggle.setAttribute(
            'aria-label',
            isExpanded ? 'Tutup menu navigasi' : 'Buka menu navigasi',
        );
    };

    const closeMobileSidebar = () => {
        pageShell.classList.remove('sidebar-open');
        setExpandedState(false);
    };

    setExpandedState(!mobileQuery.matches);

    menuToggle.addEventListener('click', () => {
        if (mobileQuery.matches) {
            const isOpen = pageShell.classList.toggle('sidebar-open');
            setExpandedState(isOpen);

            return;
        }

        const isCollapsed = pageShell.classList.toggle('sidebar-collapsed');
        setExpandedState(!isCollapsed);
    });

    sidebarBackdrop?.addEventListener('click', closeMobileSidebar);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && pageShell.classList.contains('sidebar-open')) {
            closeMobileSidebar();
            menuToggle.focus();
        }
    });

    mobileQuery.addEventListener('change', () => {
        pageShell.classList.remove('sidebar-open', 'sidebar-collapsed');
        setExpandedState(!mobileQuery.matches);
    });
}
