// checkout.js switches the checkout form between delivery and click & collect
// shows only the fields and payment options that apply and keeps the total up to date
// written with jQuery, which footer.php loads on every shop page

$(function () {
    const $form = $('#checkoutForm');
    if (!$form.length) {
        return;
    }

    const $summary = $form.find('.summary-rows');
    const subtotal = parseFloat($summary.data('subtotal')) || 0;
    const deliveryFee = parseFloat($summary.data('delivery-fee')) || 0;

    function rand(amount) {
        return 'R ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function update() {
        const type = $form.find('input[name="fulfilmentType"]:checked').val() || 'delivery';

        // sections for one type only
        $form.find('[data-show-for]').each(function () {
            $(this).toggle($(this).data('show-for') === type);
        });

        // address fields are only required for delivery
        $form.find('[data-required-for]').each(function () {
            $(this).prop('required', $(this).data('required-for') === type);
        });

        // a store must be picked for collection
        $form.find('input[name="collectionStoreID"]').prop('required', type === 'collection');

        // payment options for this type, move the choice if it no longer applies
        $form.find('[data-payment-for]').each(function () {
            const allowed = String($(this).data('payment-for')).split(' ').indexOf(type) !== -1;
            $(this).toggle(allowed).find('input').prop('disabled', !allowed);
        });
        if (!$form.find('input[name="paymentMethod"]:checked:enabled').length) {
            $form.find('input[name="paymentMethod"]:enabled').first().prop('checked', true);
        }

        // totals
        const fee = type === 'delivery' ? deliveryFee : 0;
        $form.find('[data-summary-delivery]').text(fee > 0 ? rand(fee) : 'Free');
        $form.find('[data-summary-total]').text(rand(subtotal + fee));
    }

    $form.on('change', 'input[name="fulfilmentType"]', update);
    update();

    // stop double orders from a second click
    $form.on('submit', function (event) {
        if (event.isDefaultPrevented()) {
            return;
        }
        $form.find('button[type="submit"]').prop('disabled', true).text('Placing your order…');
    });
});
