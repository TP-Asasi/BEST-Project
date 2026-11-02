// main.js is loaded on every shop page
// book cover fallbacks, add to cart without a page reload, quantity buttons,
// form validation, password show/hide, confirm prompts and the cookie notice

(function () {
    'use strict';

    // ---- book covers: draw a placeholder when the image is missing ----
    function showFallback(img) {
        img.hidden = true;
        const fallback = img.parentElement.querySelector('.cover-fallback');
        if (fallback) {
            fallback.hidden = false;
        }
    }

    document.querySelectorAll('img[data-cover]').forEach(function (img) {
        // Open Library sends a 1x1 image for some unknown covers, treat that as missing too
        const check = function () {
            if (img.naturalWidth <= 1) {
                showFallback(img);
            }
        };
        img.addEventListener('error', function () { showFallback(img); });
        img.addEventListener('load', check);
        if (img.complete) {
            check();
        }
    });

    // ---- small message in the corner ----
    function toast(message, isError) {
        let holder = document.querySelector('.toast-holder');
        if (!holder) {
            holder = document.createElement('div');
            holder.className = 'toast-holder';
            holder.setAttribute('aria-live', 'polite');
            document.body.appendChild(holder);
        }
        const note = document.createElement('div');
        note.className = 'site-toast' + (isError ? ' is-error' : '');
        note.innerHTML = '<i class="bi ' + (isError ? 'bi-exclamation-circle' : 'bi-check-circle') + '" aria-hidden="true"></i>';
        const text = document.createElement('span');
        text.textContent = message;
        note.appendChild(text);
        if (!isError) {
            const link = document.createElement('a');
            link.href = 'cart.php';
            link.textContent = 'View cart';
            note.appendChild(link);
        }
        holder.appendChild(note);
        setTimeout(function () { note.classList.add('is-leaving'); }, 3200);
        setTimeout(function () { note.remove(); }, 3600);
    }

    function updateCartBadge(count) {
        document.querySelectorAll('[data-cart-count]').forEach(function (badge) {
            badge.textContent = count;
            badge.hidden = count < 1;
            badge.classList.remove('bump');
            void badge.offsetWidth;
            badge.classList.add('bump');
        });
    }

    // ---- add to cart with fetch, the form still works normally without javascript ----
    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form.matches('.add-to-cart-form')) {
            return;
        }
        event.preventDefault();
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;

        fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: new FormData(form),
            credentials: 'same-origin'
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                updateCartBadge(data.cartCount);
                toast(data.message, !data.ok);
            })
            .catch(function () {
                // fall back to a normal post if the request failed
                form.submit();
            })
            .finally(function () { button.disabled = false; });
    });

    // ---- quantity buttons beside a number box ----
    document.querySelectorAll('[data-qty-stepper]').forEach(function (stepper) {
        const input = stepper.querySelector('input');
        stepper.querySelectorAll('[data-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                const min = parseInt(input.min, 10) || 1;
                const max = parseInt(input.max, 10) || 99;
                const next = (parseInt(input.value, 10) || min) + parseInt(button.dataset.step, 10);
                input.value = Math.min(max, Math.max(min, next));
            });
        });
    });

    // ---- ask before sending forms with data-confirm ----
    document.addEventListener('submit', function (event) {
        const message = event.target.dataset.confirm;
        if (message && !window.confirm(message)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    // ---- send a form as soon as a field with data-submit-on-change changes ----
    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-submit-on-change]')) {
            event.target.form.submit();
        }
    });

    // ---- bootstrap style validation before the server checks again ----
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            // confirm fields must match the field they point at
            form.querySelectorAll('[data-match]').forEach(function (field) {
                const other = form.querySelector('#' + field.dataset.match);
                field.setCustomValidity(other && other.value !== field.value ? 'The passwords must match.' : '');
            });
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopImmediatePropagation();
                const firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
            form.classList.add('was-validated');
        });
    });

    // ---- show or hide a password ----
    document.querySelectorAll('.password-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = button.parentElement.querySelector('input');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            button.querySelector('i').className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
        });
    });

    // ---- cookie notice, remembered in this browser only ----
    const notice = document.getElementById('cookieNotice');
    if (notice) {
        let seen = false;
        try {
            seen = window.localStorage.getItem('bestCookieNotice') === '1';
        } catch (error) {
            seen = false;
        }
        notice.hidden = seen;
        document.getElementById('cookieAccept').addEventListener('click', function () {
            notice.hidden = true;
            try {
                window.localStorage.setItem('bestCookieNotice', '1');
            } catch (error) {
                // storage blocked, the notice will show again next visit
            }
        });
    }
})();
