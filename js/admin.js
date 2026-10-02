document.addEventListener("DOMContentLoaded", () => {

    const toggle = document.getElementById("sidebarToggle");
    const sidebar = document.getElementById("adminSidebar");
    let backdrop = document.querySelector(".admin-sidebar-backdrop");

    if (!backdrop) {
        backdrop = document.createElement("div");
        backdrop.className = "admin-sidebar-backdrop";
        document.body.appendChild(backdrop);
    }

    function setSidebarOpen(open) {
        if (!sidebar) return;
        sidebar.classList.toggle("open", open);
        backdrop.classList.toggle("visible", open);
    }

    if (toggle && sidebar) {
        toggle.addEventListener("click", () => {
            const isOpen = sidebar.classList.contains("open");
            setSidebarOpen(!isOpen);
        });
    }

    if (backdrop) {
        backdrop.addEventListener("click", () => setSidebarOpen(false));
    }

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            setSidebarOpen(false);
        }
    });

});