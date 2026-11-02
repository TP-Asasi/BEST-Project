// dashboard.js is loaded on every staff page by components/layoutEnd.php
// confirm prompts, save as you change, form validation, cover previews,
// the inventory save bar, role/store fields and printing. Uses jQuery.

$(function () {
    'use strict';

    // ---- ask before sending forms with data-confirm ----
    $(document).on('submit', 'form[data-confirm]', function (event) {
        if (!window.confirm($(this).data('confirm'))) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    });

    // ---- an extra check before an order is cancelled ----
    $(document).on('submit', 'form[data-confirm-cancel]', function (event) {
        if ($(this).find('input[name="status"]:checked').val() === 'cancelled'
            && !window.confirm('Cancel this order? Every copy will be returned to stock.')) {
            event.preventDefault();
        }
    });

    // ---- send the form as soon as the field changes ----
    $(document).on('change', '[data-submit-on-change]', function () {
        this.form.submit();
    });

    // ---- browser validation with bootstrap styles, the server checks everything again ----
    $('form.needs-validation').on('submit', function (event) {
        const form = this;
        $(form).find('[data-match]').each(function () {
            const other = document.getElementById(this.dataset.match);
            this.setCustomValidity(other && other.value !== this.value ? 'The passwords must match.' : '');
        });
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopImmediatePropagation();
            $(form).find(':invalid').first().trigger('focus');
        }
        $(form).addClass('was-validated');
    });

    // ---- missing covers become a plain placeholder ----
    $('img[data-cover]').each(function () {
        const img = this;
        const hide = function () {
            if (img.naturalWidth <= 1) {
                $(img).addClass('cover-missing');
            }
        };
        $(img).on('error', function () { $(img).addClass('cover-missing'); }).on('load', hide);
        if (img.complete) {
            hide();
        }
    });

    // ---- live cover preview on the book form ----
    const $preview = $('[data-cover-preview]');
    if ($preview.length) {
        const refresh = function () {
            const url = $.trim($('[data-cover-input]').val());
            const isbn = $('[data-isbn-input]').val().replace(/[^0-9Xx]/g, '');
            $preview.removeClass('cover-missing');
            $preview.attr('src', url || (isbn ? 'https://covers.openlibrary.org/b/isbn/' + isbn + '-M.jpg?default=false' : ''));
        };
        $preview.on('error', function () { $preview.addClass('cover-missing'); });
        $preview.on('load', function () {
            if (this.naturalWidth <= 1) {
                $preview.addClass('cover-missing');
            }
        });
        $('[data-cover-input], [data-isbn-input]').on('change', refresh);
    }

    // ---- inventory grid: highlight changed cells and count them ----
    const $inventory = $('#inventoryForm');
    if ($inventory.length) {
        const update = function () {
            const changed = $inventory.find('.stock-input').filter(function () {
                return String(this.value) !== String($(this).data('original'));
            });
            $inventory.find('.stock-input').removeClass('is-changed');
            changed.addClass('is-changed');
            const n = changed.length;
            $inventory.find('[data-change-count]').text(n ? n + ' unsaved change' + (n === 1 ? '' : 's') : 'No changes');
            $inventory.find('[data-save-button]').prop('disabled', n === 0);
            $inventory.find('[data-save-bar]').toggleClass('has-changes', n > 0);
        };
        $inventory.on('input change', '.stock-input', update);

        // warn before leaving with unsaved stock changes
        let saving = false;
        $inventory.on('submit', function () { saving = true; });
        $(window).on('beforeunload', function () {
            if (!saving && $inventory.find('.stock-input.is-changed').length) {
                return 'You have unsaved stock changes.';
            }
        });
    }

    // ---- admins do not need a store ----
    $('[data-role-select]').each(function () {
        const $role = $(this);
        const $store = $role.closest('form').find('[data-store-select]');
        const sync = function () {
            const isAdmin = $role.val() === 'Admin';
            $store.prop('disabled', isAdmin).prop('required', !isAdmin && !$role.prop('disabled'));
        };
        $role.on('change', sync);
        sync();
    });

    // ---- print the order page ----
    $('[data-print]').on('click', function () {
        window.print();
    });
});
