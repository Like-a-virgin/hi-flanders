document.addEventListener("DOMContentLoaded", function () {
    // Select all links with the class "action-link"
    const actionLinks = document.querySelectorAll(".action-link");

    actionLinks.forEach((link) => {
        const action = link.getAttribute("data-action");

        // Check if the action was already clicked
        if (localStorage.getItem(action) === "clicked") {
            link.style.pointerEvents = "none"; // Disable clicking
            link.style.opacity = "0.5"; // Reduce opacity to indicate it's disabled
            link.textContent += " (Already Clicked)"; // Update text
        }

        // Add click event listener
        link.addEventListener("click", function (event) {
            event.preventDefault(); // Prevent immediate navigation

            if (!localStorage.getItem(action)) {
                localStorage.setItem(action, "clicked"); // Mark as clicked

                // Redirect to the actual link after setting localStorage
                window.location.href = link.href;
            }
        });
    });
});