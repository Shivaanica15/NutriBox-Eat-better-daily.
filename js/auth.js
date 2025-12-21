document.addEventListener('DOMContentLoaded', function () {
    var card = document.querySelector('.auth-card');
    if (!card) {
        return;
    }

    var switchLinks = document.querySelectorAll('[data-auth-switch]');
    var forms = document.querySelectorAll('.auth-form');

    function setMode(mode) {
        card.setAttribute('data-mode', mode);
        forms.forEach(function (form) {
            var target = form.getAttribute('data-form');
            if (target === mode) {
                form.classList.add('is-active');
            } else {
                form.classList.remove('is-active');
            }
        });
    }

    switchLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            var mode = link.getAttribute('data-auth-switch');
            if (mode) {
                event.preventDefault();
                setMode(mode);
            }
        });
    });

    forms.forEach(function (form) {
        form.addEventListener('submit', function () {
            form.classList.add('is-loading');
            var button = form.querySelector('.auth-submit');
            if (button) {
                button.classList.add('is-loading');
            }
        });
    });
});
