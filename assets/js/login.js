document.addEventListener('DOMContentLoaded', function () {

    // Add loading state on form submit
    const form = document.querySelector('form');
    const btn  = document.querySelector('.btn-login');

    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.textContent = 'Logging in…';
            btn.disabled = true;
        });
    }

    // Input line color animation on focus
    const inputs = document.querySelectorAll('.input-wrap input');
    inputs.forEach(function (input) {
        input.addEventListener('focus', function () {
            this.closest('.input-wrap').classList.add('focused');
        });
        input.addEventListener('blur', function () {
            this.closest('.input-wrap').classList.remove('focused');
        });
    });

});
