const eye = document.querySelector('.formbox__see-pass-icon');
console.log('eye' + eye)

if (eye) {
    document.addEventListener('DOMContentLoaded', () => {
        const passwordInput = document.getElementById('password');
        console.log('passInp' + passwordInput)
        const togglePasswordButton = document.getElementById('togglePassword');
        console.log('toggleBtn' + togglePasswordButton)

        if (!passwordInput || !togglePasswordButton) return; // Prevent errors if elements are missing

        togglePasswordButton.addEventListener('click', () => {
            console.log('clicked')
            const isPasswordVisible = passwordInput.type === 'text';
            console.log('ispassvis' + isPasswordVisible)
            passwordInput.type = isPasswordVisible ? 'password' : 'text';

            // Toggle the eye icon class
            eye.classList.toggle('show-eye');
        });
    });
}