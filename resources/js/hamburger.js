const toggleOpen = document.getElementById('toggleOpen');
const toggleClose = document.getElementById('toggleClose');
const collapseMenu = document.getElementById('collapseMenu');
const overlay = document.getElementById('overlay');

function openMenu() {
    collapseMenu.classList.remove('-translate-x-full');
    collapseMenu.classList.add('translate-x-0');
    overlay.classList.remove('opacity-0', 'pointer-events-none');
    overlay.classList.add('opacity-50', 'pointer-events-auto');
}

function closeMenu() {
    collapseMenu.classList.remove('translate-x-0');
    collapseMenu.classList.add('-translate-x-full');
    overlay.classList.remove('opacity-50', 'pointer-events-auto');
    overlay.classList.add('opacity-0', 'pointer-events-none');
}

toggleOpen.addEventListener('click', openMenu);
toggleClose.addEventListener('click', closeMenu);
overlay.addEventListener('click', closeMenu);
