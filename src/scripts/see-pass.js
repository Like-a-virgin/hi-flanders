const eye = document.querySelector('.formbox__see-pass-icon');

if (eye) {
    document.addEventListener('DOMContentLoaded', () => {
        console.log('test')
        const passwordInput = document.getElementById('password');
        const togglePasswordButton = document.getElementById('togglePassword');
    
        togglePasswordButton.addEventListener('click', () => {
            // Toggle the input type between 'password' and 'text'
            console.log('click')
            const isPasswordVisible = passwordInput.type === 'text';
            passwordInput.type = isPasswordVisible ? 'password' : 'text';
    
            if (eye.classList.contains('show-eye')) {
                eye.classList.remove('show-eye');
            } else {
                eye.classList.add('show-eye')
            }
    
        });
    });
}