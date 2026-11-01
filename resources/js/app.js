import { initializeLocationPicker } from './location-picker';

initializeLocationPicker();

const menuButton = document.querySelector('[data-menu-toggle]');
const mobileMenu = document.querySelector('[data-mobile-menu]');

if (menuButton && mobileMenu) {
    const closeMenu = () => {
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.setAttribute('aria-label', 'Buka menu navigasi');
        mobileMenu.hidden = true;
    };

    menuButton.addEventListener('click', () => {
        const isOpen = menuButton.getAttribute('aria-expanded') === 'true';
        menuButton.setAttribute('aria-expanded', String(!isOpen));
        menuButton.setAttribute('aria-label', isOpen ? 'Buka menu navigasi' : 'Tutup menu navigasi');
        mobileMenu.hidden = isOpen;
    });

    mobileMenu.addEventListener('click', (event) => {
        if (event.target.closest('a')) closeMenu();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !mobileMenu.hidden) {
            closeMenu();
            menuButton.focus();
        }
    });

    window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
        if (event.matches) closeMenu();
    });
}

const reportChecklist = document.querySelector('[data-report-checklist]');
if (reportChecklist) {
    const checkboxes = [...reportChecklist.querySelectorAll('input[type="checkbox"]')];
    const progress = document.querySelector('[data-checklist-progress]');
    const progressBar = document.querySelector('[data-checklist-bar]');
    const updateChecklist = () => {
        const completed = checkboxes.filter((checkbox) => checkbox.checked).length;
        progress.textContent = `${completed} dari ${checkboxes.length} persiapan ditandai`;
        progressBar.style.width = `${(completed / checkboxes.length) * 100}%`;
    };
    reportChecklist.addEventListener('change', updateChecklist);
    document.querySelector('[data-checklist-reset]').addEventListener('click', () => {
        checkboxes.forEach((checkbox) => { checkbox.checked = false; });
        updateChecklist();
    });
    updateChecklist();
}
