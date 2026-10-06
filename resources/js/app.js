require('./bootstrap');

document.addEventListener('DOMContentLoaded', function () {
    var openButton = document.querySelector('[data-sidebar-open]');
    var closeButtons = document.querySelectorAll('[data-sidebar-close]');

    function closeSidebar() {
        document.body.classList.remove('sidebar-open');
        if (openButton) {
            openButton.setAttribute('aria-expanded', 'false');
        }
    }

    if (openButton) {
        openButton.addEventListener('click', function () {
            document.body.classList.add('sidebar-open');
            openButton.setAttribute('aria-expanded', 'true');
        });
    }

    Array.prototype.forEach.call(closeButtons, function (button) {
        button.addEventListener('click', closeSidebar);
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-toast-close]'), function (button) {
        button.addEventListener('click', function () {
            var toast = button.closest('[data-toast]');
            if (toast) {
                toast.remove();
            }
        });
    });
});
