document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toggle-password').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-target'));
            if (!input) return;
            var visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            button.textContent = visible ? 'Show' : 'Hide';
        });
    });

    var password = document.getElementById('register-password');
    var bar = document.querySelector('[data-meter-bar]');
    var text = document.querySelector('[data-meter-text]');
    if (password && bar && text) {
        password.addEventListener('input', function () {
            var value = password.value;
            var score = 0;
            if (value.length >= 8) score++;
            if (/[A-Z]/.test(value)) score++;
            if (/[0-9]/.test(value)) score++;
            if (/[^A-Za-z0-9]/.test(value)) score++;
            bar.style.width = (score * 25) + '%';
            text.textContent = score < 2 ? 'Add numbers, symbols and 8+ characters' : score < 4 ? 'Good password — add another character type' : 'Strong password';
        });
    }
});
