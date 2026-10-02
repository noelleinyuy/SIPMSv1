document.addEventListener("DOMContentLoaded", () => {

    /* ============================================================
       MOBILE MENU
    ============================================================ */

    const menuToggle = document.getElementById("menuToggle");
    const menuClose = document.getElementById("menuClose");
    const mobileMenu = document.getElementById("mobileMenu");
    const menuOverlay = document.getElementById("menuOverlay");

    function openMenu() {
        mobileMenu.classList.add("open");
        menuOverlay.classList.add("open");
    }

    function closeMenu() {
        mobileMenu.classList.remove("open");
        menuOverlay.classList.remove("open");
    }

    if (menuToggle) menuToggle.addEventListener("click", openMenu);
    if (menuClose) menuClose.addEventListener("click", closeMenu);
    if (menuOverlay) menuOverlay.addEventListener("click", closeMenu);

    document.querySelectorAll(".mobile-nav-link, .mobile-role-link").forEach((link) => {
        link.addEventListener("click", closeMenu);
    });

    /* ============================================================
       BACK TO TOP
    ============================================================ */

    const backToTop = document.getElementById("backToTop");

    if (backToTop) {
        window.addEventListener("scroll", () => {
            if (window.scrollY > 400) {
                backToTop.classList.add("show");
            } else {
                backToTop.classList.remove("show");
            }
        });

        backToTop.addEventListener("click", () => {
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
    }

    /* ============================================================
       SCROLL-SPY NAV HIGHLIGHTING
    ============================================================ */

    const navLinks = document.querySelectorAll(".nav-link");
    const sections = Array.from(navLinks)
        .map((link) => document.querySelector(link.getAttribute("href")))
        .filter(Boolean);

    function updateActiveLink() {
        let currentId = sections[0] ? sections[0].id : null;

        sections.forEach((section) => {
            const top = section.offsetTop - 140;
            if (window.scrollY >= top) {
                currentId = section.id;
            }
        });

        navLinks.forEach((link) => {
            link.classList.toggle("active", link.getAttribute("href") === "#" + currentId);
        });
    }

    window.addEventListener("scroll", updateActiveLink);
    updateActiveLink();

});