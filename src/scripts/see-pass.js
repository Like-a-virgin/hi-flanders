document.addEventListener('DOMContentLoaded', () => {
    const eye = document.querySelector('.formbox__see-pass-icon');
    const passwordInput = document.getElementById('password');
    const togglePasswordButton = document.getElementById('togglePassword');

    // Guard clause to avoid errors if elements are missing
    if (!eye || !passwordInput || !togglePasswordButton) return;

    togglePasswordButton.addEventListener('click', () => {
        const isPasswordVisible = passwordInput.type === 'text';
        passwordInput.type = isPasswordVisible ? 'password' : 'text';

        eye.classList.toggle('show-eye');
    });
});