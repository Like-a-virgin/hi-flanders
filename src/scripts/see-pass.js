document.addEventListener('DOMContentLoaded', () => {
    const eye = document.querySelector('.formbox__see-pass-icon');
    const passwordInput = document.getElementById('password');
    const togglePasswordButton = document.getElementById('togglePassword');

    if (!eye || !passwordInput || !togglePasswordButton) return; // Prevent errors if elements are missing

    togglePasswordButton.addEventListener('click', () => {
        const isPasswordVisible = passwordInput.type === 'text';
        passwordInput.type = isPasswordVisible ? 'password' : 'text';

        // Toggle the eye icon class
        eye.classList.toggle('show-eye');
    });
});
