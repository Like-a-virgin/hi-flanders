window.addEventListener("load", () => {
    const params = new URLSearchParams(window.location.search);

    if (window.location.search.includes("credentials")) {
        const raw = params.get("credentials");
        if (raw) {
        // Decode credentials
        let decoded = raw.slice(0, -2); // Remove last two characters
        decoded = decoded.slice(2) + decoded.slice(0, 2); // Move first 2 to end
        decoded = atob(decoded.split("").reverse().join("")); // Reverse and base64-decode

        // Extract individual values
        const getParam = (key) => decoded.split(`${key}=`)[1]?.split("&")[0] || "";

        const email = getParam("e-mail");
        const pass = getParam("pass");
        const rememberMe = getParam("rememberme") === "true";

        // Fill login form
        document.querySelector("input#loginName").value = email;
        document.querySelector("input#password").value = pass;
        if (rememberMe) {
            document.querySelector("input#remember-me").checked = true;
        }

        // Handle login attempt throttling
        const lastAttempt = Cookies.get("last-login-attempt");
        const now = Math.floor(Date.now() / 1000);

        if (!lastAttempt) {
            Cookies.set("last-login-attempt", now, {
            expires: new Date(Date.now() + 6000), // expire in 6 seconds
            });
            document.querySelector("form button.formbox__button").click();
        } else {
            const diff = now - parseInt(lastAttempt);
            if (diff < 1) {
            document.querySelector("form button.formbox__button").click();
            }
        }
        }
    }
});
