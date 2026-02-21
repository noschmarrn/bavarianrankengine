(function () {
    'use strict';

    // -- Nav scroll behaviour
    const nav = document.getElementById('nav');
    if (nav) {
        const onScroll = () => {
            nav.classList.toggle('scrolled', window.scrollY > 50);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    // -- Mobile nav toggle
    const toggle   = document.getElementById('navToggle');
    const navLinks = document.getElementById('navLinks');
    if (toggle && navLinks) {
        toggle.addEventListener('click', () => {
            navLinks.classList.toggle('open');
            toggle.setAttribute(
                'aria-expanded',
                navLinks.classList.contains('open') ? 'true' : 'false'
            );
        });

        // Close on link click
        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

})();
