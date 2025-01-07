document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.getElementById('password');
    const togglePasswordButton = document.getElementById('togglePassword');
    const eye = document.querySelector('.formbox__see-pass-icon');

    togglePasswordButton.addEventListener('click', () => {
        // Toggle the input type between 'password' and 'text'
        const isPasswordVisible = passwordInput.type === 'text';
        passwordInput.type = isPasswordVisible ? 'password' : 'text';

        if (eye.classList.contains('show-eye')) {
            eye.classList.remove('show-eye');
        } else {
            eye.classList.add('show-eye')
        }

    });
});