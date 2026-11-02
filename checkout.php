<?php
    // checkout.php takes the delivery or collection details and places the order
    // customers must be signed in. The stock is checked again and taken in one transaction in placeOrder()

    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/books.php';
    require_once __DIR__ . '/includes/helpers/cart.php';
    require_once __DIR__ . '/includes/helpers/orders.php';
    require_once __DIR__ . '/includes/helpers/customerAuth.php';

    $conn = db();
    if (!$conn) {
        setFlash('error', 'Checkout is unavailable right now. Please try again shortly.');
        header('Location: cart.php');
        exit;
    }

    $customer = requireCustomer($conn, 'checkout.php');
    $cart = cartDetails($conn);
    if (!$cart['lines']) {
        header('Location: cart.php');
        exit;
    }

    $stores = getActiveStores($conn);

    // for click and collect, can each store supply the whole cart?
    $bookIDs = array_map('intval', array_column($cart['lines'], 'bookID'));
    $stockRows = fetchAll($conn,
        "SELECT bookID, storeID, quantity FROM Inventory WHERE bookID IN (" . placeholders($bookIDs) . ")", $bookIDs);
    $stockAt = [];
    foreach ($stockRows as $row) {
        $stockAt[$row['storeID']][$row['bookID']] = (int) $row['quantity'];
    }
    foreach ($stores as &$store) {
        $missing = 0;
        foreach ($cart['lines'] as $line) {
            if (($stockAt[$store['storeID']][$line['bookID']] ?? 0) < $line['quantity']) {
                $missing++;
            }
        }
        $store['missing'] = $missing;
    }
    unset($store);

    // the form starts with the customer's saved details
    $input = [
        'fulfilmentType'     => 'delivery',
        'collectionStoreID'  => null,
        'recipientName'      => trim($customer['firstName'] . ' ' . $customer['lastName']),
        'contactPhone'       => $customer['phoneNumber'] ?? '',
        'deliveryAddress'    => $customer['addressLine'] ?? '',
        'deliverySuburb'     => $customer['suburb'] ?? '',
        'deliveryCity'       => $customer['city'] ?? '',
        'deliveryProvince'   => $customer['province'] ?? '',
        'deliveryPostalCode' => $customer['postalCode'] ?? '',
        'paymentMethod'      => 'eft',
        'customerNote'       => '',
    ];
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $input = [
                'fulfilmentType'     => postText('fulfilmentType', 20),
                'collectionStoreID'  => intParam($_POST, 'collectionStoreID'),
                'recipientName'      => postText('recipientName', 200),
                'contactPhone'       => postText('contactPhone', 30),
                'deliveryAddress'    => postText('deliveryAddress', 255),
                'deliverySuburb'     => postText('deliverySuburb', 100),
                'deliveryCity'       => postText('deliveryCity', 100),
                'deliveryProvince'   => postText('deliveryProvince', 60),
                'deliveryPostalCode' => postText('deliveryPostalCode', 10),
                'paymentMethod'      => postText('paymentMethod', 30),
                'customerNote'       => postText('customerNote', 500),
            ];

            $errors = validateCheckout($input, $stores);
            if (!$errors) {
                $result = placeOrder($conn, (int) $customer['accountID'], $cart, $input);
                if (isset($result['orderNumber'])) {
                    cartClear();
                    header('Location: order-confirmation.php?order=' . urlencode($result['orderNumber']));
                    exit;
                }
                $errors = $result['errors'];
            }
        }
    }

    $deliveryFee = deliveryFeeFor($cart['subtotal'], 'delivery');
    $pageTitle = 'Checkout';
?>
<!DOCTYPE html>
<html lang="en">
    <?php include 'includes/components/header.php'; ?>

    <body>
        <?php include 'includes/components/navBar.php'; ?>

        <main id="main" class="container-xl page-pad">
            <ol class="checkout-steps" aria-label="Checkout progress">
                <li class="done"><a href="cart.php">Cart</a></li>
                <li class="current" aria-current="step">Details</li>
                <li>Confirmation</li>
            </ol>
            <h1 class="page-title mb-4">Checkout</h1>

            <?php if ($errors): ?>
                <div class="alert alert-danger" role="alert">
                    <p class="fw-semibold mb-1">Please fix the following:</p>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="checkout-layout needs-validation" id="checkoutForm" novalidate>
                <?= csrfField() ?>
                <div class="checkout-main">
                    <!-- delivery or collection -->
                    <fieldset class="checkout-card">
                        <legend class="h5">1. How would you like to get your books?</legend>
                        <div class="choice-grid">
                            <label class="choice">
                                <input type="radio" name="fulfilmentType" value="delivery" <?= $input['fulfilmentType'] === 'delivery' ? 'checked' : '' ?>>
                                <span class="choice-body">
                                    <i class="bi bi-truck" aria-hidden="true"></i>
                                    <strong>Delivery</strong>
                                    <small><?= $deliveryFee > 0 ? money(DELIVERY_FEE) . ' · free over ' . money(FREE_DELIVERY_FROM) : 'Free for this order' ?>. 2–5 working days.</small>
                                </span>
                            </label>
                            <label class="choice">
                                <input type="radio" name="fulfilmentType" value="collection" <?= $input['fulfilmentType'] === 'collection' ? 'checked' : '' ?>>
                                <span class="choice-body">
                                    <i class="bi bi-shop" aria-hidden="true"></i>
                                    <strong>Click &amp; collect</strong>
                                    <small>Free. We’ll let you know when it’s ready.</small>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <!-- collection store -->
                    <fieldset class="checkout-card" data-show-for="collection">
                        <legend class="h5">2. Choose a store</legend>
                        <div class="store-options">
                            <?php foreach ($stores as $store): ?>
                                <label class="store-option<?= $store['missing'] ? ' is-unavailable' : '' ?>">
                                    <input type="radio" name="collectionStoreID" value="<?= (int) $store['storeID'] ?>"
                                        <?= $input['collectionStoreID'] === (int) $store['storeID'] ? 'checked' : '' ?>
                                        <?= $store['missing'] ? 'disabled' : '' ?>>
                                    <span>
                                        <strong><?= e($store['name']) ?></strong>
                                        <small><?= e($store['addressLine']) ?>, <?= e($store['city']) ?></small>
                                        <small class="<?= $store['missing'] ? 'text-danger' : 'text-success' ?>">
                                            <?= $store['missing'] ? $store['missing'] . ' of your books not available here' : 'Everything in your cart is here' ?>
                                        </small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <!-- contact and delivery details -->
                    <fieldset class="checkout-card">
                        <legend class="h5"><span data-show-for="delivery">2.</span><span data-show-for="collection">3.</span> Your details</legend>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="recipientName" class="form-label">Full name</label>
                                <input id="recipientName" name="recipientName" class="form-control" value="<?= e($input['recipientName']) ?>" autocomplete="name" required maxlength="200">
                                <div class="invalid-feedback">Enter a name.</div>
                            </div>
                            <div class="col-sm-6">
                                <label for="contactPhone" class="form-label">Phone number</label>
                                <input id="contactPhone" name="contactPhone" type="tel" class="form-control" value="<?= e($input['contactPhone']) ?>" autocomplete="tel" required pattern="\+?[0-9 ()\-]{9,20}">
                                <div class="invalid-feedback">Enter a valid phone number.</div>
                            </div>
                        </div>

                        <div class="row g-3 mt-0" data-show-for="delivery">
                            <div class="col-12">
                                <label for="deliveryAddress" class="form-label">Street address</label>
                                <input id="deliveryAddress" name="deliveryAddress" class="form-control" value="<?= e($input['deliveryAddress']) ?>" autocomplete="address-line1" data-required-for="delivery" maxlength="255">
                                <div class="invalid-feedback">Enter your street address.</div>
                            </div>
                            <div class="col-sm-6">
                                <label for="deliverySuburb" class="form-label">Suburb <span class="text-muted">(optional)</span></label>
                                <input id="deliverySuburb" name="deliverySuburb" class="form-control" value="<?= e($input['deliverySuburb']) ?>" autocomplete="address-line2" maxlength="100">
                            </div>
                            <div class="col-sm-6">
                                <label for="deliveryCity" class="form-label">City / town</label>
                                <input id="deliveryCity" name="deliveryCity" class="form-control" value="<?= e($input['deliveryCity']) ?>" autocomplete="address-level2" data-required-for="delivery" maxlength="100">
                                <div class="invalid-feedback">Enter your city.</div>
                            </div>
                            <div class="col-sm-6">
                                <label for="deliveryProvince" class="form-label">Province</label>
                                <select id="deliveryProvince" name="deliveryProvince" class="form-select" data-required-for="delivery">
                                    <option value="">Choose…</option>
                                    <?php foreach (PROVINCES as $province): ?>
                                        <option <?= $input['deliveryProvince'] === $province ? 'selected' : '' ?>><?= $province ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Choose a province.</div>
                            </div>
                            <div class="col-sm-6">
                                <label for="deliveryPostalCode" class="form-label">Postal code</label>
                                <input id="deliveryPostalCode" name="deliveryPostalCode" class="form-control" value="<?= e($input['deliveryPostalCode']) ?>" autocomplete="postal-code" inputmode="numeric" pattern="[0-9]{4}" data-required-for="delivery">
                                <div class="invalid-feedback">Enter a 4 digit postal code.</div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- payment -->
                    <fieldset class="checkout-card">
                        <legend class="h5"><span data-show-for="delivery">3.</span><span data-show-for="collection">4.</span> Payment</legend>
                        <div class="payment-options">
                            <?php foreach (PAYMENT_METHODS as $key => $label): ?>
                                <?php $for = implode(' ', array_keys(array_filter(PAYMENT_METHODS_FOR, fn ($methods) => in_array($key, $methods, true)))); ?>
                                <label class="form-check payment-option" data-payment-for="<?= $for ?>">
                                    <input class="form-check-input" type="radio" name="paymentMethod" value="<?= $key ?>" <?= $input['paymentMethod'] === $key ? 'checked' : '' ?>>
                                    <span class="form-check-label"><?= $label ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="small text-muted mt-2 mb-0">For EFT, our banking details and your order number are shown on the next page. Orders are processed once payment reflects.</p>

                        <label for="customerNote" class="form-label mt-3">Note for the store <span class="text-muted">(optional)</span></label>
                        <textarea id="customerNote" name="customerNote" class="form-control" rows="2" maxlength="500"><?= e($input['customerNote']) ?></textarea>
                    </fieldset>
                </div>

                <!-- summary -->
                <aside class="summary-card" aria-labelledby="summaryHeading">
                    <h2 id="summaryHeading" class="h5">Your order</h2>
                    <ul class="summary-lines">
                        <?php foreach ($cart['lines'] as $line): ?>
                            <li><span><?= (int) $line['quantity'] ?> × <?= e($line['title']) ?></span><span><?= money($line['lineTotal']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <dl class="summary-rows" data-subtotal="<?= e((string) $cart['subtotal']) ?>" data-delivery-fee="<?= e((string) $deliveryFee) ?>">
                        <div><dt>Subtotal</dt><dd><?= money($cart['subtotal']) ?></dd></div>
                        <div><dt>Delivery</dt><dd data-summary-delivery><?= $deliveryFee > 0 ? money($deliveryFee) : 'Free' ?></dd></div>
                        <div class="summary-total"><dt>Total</dt><dd data-summary-total><?= money($cart['subtotal'] + $deliveryFee) ?></dd></div>
                    </dl>
                    <button type="submit" class="btn btn-accent btn-lg w-100">Place order</button>
                    <p class="small text-muted mt-3 mb-0">By placing your order you agree that BEST may use your details to process and deliver it, as set out in our <a href="privacy.php">privacy notice</a>.</p>
                </aside>
            </form>
        </main>

        <?php include 'includes/components/footer.php'; ?>
        <script src="assets/js/checkout.js?v=<?= filemtime(__DIR__ . '/assets/js/checkout.js') ?>"></script>
    </body>
</html>
