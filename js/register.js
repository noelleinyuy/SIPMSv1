document.addEventListener("DOMContentLoaded", () => {

    const userType = document.getElementById("userType");
    const roleFields = document.querySelectorAll(".role-field");

    function updateVisibleFields() {

        const selected = userType.value;

        roleFields.forEach((field) => {

            const appliesTo = (field.dataset.for || "").split(",").map((r) => r.trim());
            const shouldShow = selected !== "" && appliesTo.includes(selected);
            const input = field.querySelector("input");

            field.style.display = shouldShow ? "" : "none";

            if (input) {
                input.required = shouldShow;
                if (!shouldShow) {
                    input.value = input.value; // keep old value in case they switch back
                }
            }

        });

    }

    userType.addEventListener("change", updateVisibleFields);

    // Run once on load so a repopulated ("old") selection after a failed
    // submit shows the right fields immediately, and hide everything by
    // default when nothing is selected yet.
    updateVisibleFields();

});