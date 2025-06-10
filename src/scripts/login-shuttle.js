window.addEventListener("load", () => {
    const params = new URLSearchParams(window.location.search);

    if (window.location.search.includes("credentials")) {
        const raw = params.get("credentials");
        if (raw) {
        // Remove the last two characters
        let decoded = raw.slice(0, -2);
        // Move first two characters to the end
        decoded = decoded.slice(2) + decoded.slice(0, 2);
        // Reverse and decode from Base64
        decoded = atob(decoded.split("").reverse().join(""));

        // Extract credentials
        const email = decoded.split("e-mail=")[1]?.split("&")[0] || "";
        const pass = decoded.split("pass=")[1]?.split("&")[0] || "";
        const rememberMe = decoded.split("rememberme=")[1]?.split("&")[0] || "false";

        // Fill form fields
        document.querySelector("input#loginName").value = email;
        document.querySelector("input#password").value = pass;
        if (rememberMe === "true") {
            document.querySelector("input#remember-me").checked = true;
        }

        // Trigger login button click
        document.querySelector("form button.formbox__button").click();
        }
    }
});
