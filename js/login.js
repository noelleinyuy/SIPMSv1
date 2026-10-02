document.addEventListener("DOMContentLoaded", () => {

    const cards = document.querySelectorAll(".portal-card");
    const roleInput = document.getElementById("role");

    function selectCard(card) {
        cards.forEach((c) => c.classList.remove("selected"));
        card.classList.add("selected");
        roleInput.value = card.dataset.role;
    }

    cards.forEach((card) => {
        card.addEventListener("click", () => selectCard(card));
    });

    // Pre-select whichever portal the server rendered into the hidden
    // input (from ?role=... on arrival, or preserved after a failed login).
    if (roleInput.value) {
        const preselected = document.querySelector(`.portal-card[data-role="${roleInput.value}"]`);
        if (preselected) {
            selectCard(preselected);
        }
    }

});