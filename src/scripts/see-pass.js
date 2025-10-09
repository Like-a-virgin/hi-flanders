const eye = document.querySelector('.formbox__see-pass-icon');

if (eye) {
    document.addEventListener('DOMContentLoaded', () => {
        const passwordInput = document.getElementById('password');
        const togglePasswordButton = document.getElementById('togglePassword');

        if (!passwordInput || !togglePasswordButton) return; // Prevent errors if elements are missing

        togglePasswordButton.addEventListener('click', () => {
            const isPasswordVisible = passwordInput.type === 'text';
            passwordInput.type = isPasswordVisible ? 'password' : 'text';

            // Toggle the eye icon class
            eye.classList.toggle('show-eye');
        });
    });
}