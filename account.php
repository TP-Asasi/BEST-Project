<?php
    // account.php is the customer's account: their orders, profile and address, password and privacy choices

    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/books.php';
    require_once __DIR__ . '/includes/helpers/orders.php';
    require_once __DIR__ . '/includes/helpers/customerAuth.php';

    $conn = db();
    $customer = requireCustomer($conn, 'account.php');
    $customerID = (int) $customer['accountID'];
    $errors = [];
    $tab = in_array($_GET['tab'] ?? '', ['orders', 'profile', 'password', 'privacy'], true) ? $_GET['tab'] : 'orders';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $tab = ['profile' => 'profile', 'password' => 'password', 'privacy' => 'privacy', 'deletion' => 'privacy'][$action] ?? 'orders';

        if (!verifyCsrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } elseif ($action === 'profile') {
            $profile = [
                'firstName'   => postText('firstName', 100),
                'lastName'    => postText('lastName', 100),
                'phoneNumber' => postText('phoneNumber', 30),
                'addressLine' => postText('addressLine', 255),
                'suburb'      => postText('suburb', 100),
                'city'        => postText('city', 100),
                'province'    => postText('province', 60),
                'postalCode'  => postText('postalCode', 10),
            ];
            if ($profile['firstName'] === '' || $profile['lastName'] === '') {
                $errors[] = 'Enter your first and last name.';
            }
            if ($profile['phoneNumber'] !== '' && !isValidPhone($profile['phoneNumber'])) {
                $errors[] = 'Enter a valid phone number.';
            }
            if ($profile['province'] !== '' && !in_array($profile['province'], PROVINCES, true)) {
                $errors[] = 'Choose a province from the list.';
            }
            if ($profile['postalCode'] !== '' && !isValidPostalCode($profile['postalCode'])) {
                $errors[] = 'Enter a 4 digit postal code.';
            }
            if (!$errors) {
                execute($conn, "UPDATE UserAccount SET firstName = ?, lastName = ?, phoneNumber = ? WHERE accountID = ?",
                    [$profile['firstName'], $profile['lastName'], $profile['phoneNumber'] ?: null, $customerID]);
                execute($conn, "UPDATE Customer SET addressLine = ?, suburb = ?, city = ?, province = ?, postalCode = ? WHERE accountID = ?",
                    [$profile['addressLine'] ?: null, $profile['suburb'] ?: null, $profile['city'] ?: null,
                     $profile['province'] ?: null, $profile['postalCode'] ?: null, $customerID]);
                setFlash('success', 'Your details were saved.');
                header('Location: account.php?tab=profile');
                exit;
            }
        } elseif ($action === 'password') {
            $current = $_POST['currentPassword'] ?? '';
            $new = $_POST['newPassword'] ?? '';
            $hash = fetchValue($conn, "SELECT passwordHash FROM UserAccount WHERE accountID = ?", [$customerID]);
            if (!is_string($current) || !password_verify($current, (string) $hash)) {
                $errors[] = 'Your current password is incorrect.';
            } elseif ($problem = passwordProblems(is_string($new) ? $new : '')) {
                $errors[] = $problem;
            } elseif ($new !== ($_POST['confirmPassword'] ?? '')) {
                $errors[] = 'The new passwords do not match.';
            } else {
                execute($conn, "UPDATE UserAccount SET passwordHash = ? WHERE accountID = ?", [password_hash($new, PASSWORD_DEFAULT), $customerID]);
                session_regenerate_id(true);
                setFlash('success', 'Your password was changed.');
                header('Location: account.php?tab=password');
                exit;
            }
        } elseif ($action === 'privacy') {
            execute($conn, "UPDATE Customer SET marketingConsent = ? WHERE accountID = ?", [!empty($_POST['marketingConsent']) ? 1 : 0, $customerID]);
            setFlash('success', 'Your privacy choices were saved.');
            header('Location: account.php?tab=privacy');
            exit;
        } elseif ($action === 'deletion') {
            // POPIA section 24: the customer can ask for their personal information to be deleted
            // staff handle it from the messages page because order records must be kept for tax purposes
            execute($conn, "INSERT INTO ContactMessage (name, email, phoneNumber, subject, message) VALUES (?, ?, ?, 'POPIA: account deletion request', ?)",
                [$customer['firstName'] . ' ' . $customer['lastName'], $customer['email'], $customer['phoneNumber'],
                 "Customer #$customerID asked for their account and personal information to be deleted."]);
            setFlash('success', 'Your request was sent. We will confirm by email within 30 days.');
            header('Location: account.php?tab=privacy');
            exit;
        }
    }

    $customer = fetchOne($conn,
        "SELECT u.*, c.* FROM UserAccount u JOIN Customer c ON c.accountID = u.accountID WHERE u.accountID = ?", [$customerID]);
    $orders = getCustomerOrders($conn, $customerID);

    $pageTitle = 'My Account';
    $tabs = ['orders' => ['bi-receipt', 'My orders'], 'profile' => ['bi-person', 'Details & address'], 'password' => ['bi-key', 'Password'], 'privacy' => ['bi-shield-check', 'Privacy']];
?>
<!DOCTYPE html>
<html lang="en">
    <?php include 'includes/components/header.php'; ?>

    <body>
        <?php include 'includes/components/navBar.php'; ?>

        <main id="main" class="container-xl page-pad">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
                <div>
                    <h1 class="page-title mb-1">Hi, <?= e($customer['firstName']) ?></h1>
                    <p class="text-muted mb-0"><?= e($customer['email']) ?></p>
                </div>
                <form action="logout.php" method="post">
                    <?= csrfField() ?>
                    <button class="btn btn-outline-brand"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sign out</button>
                </form>
            </div>

            <div class="account-layout">
                <nav class="account-nav" aria-label="Account sections">
                    <?php foreach ($tabs as $key => [$icon, $label]): ?>
                        <a href="account.php?tab=<?= $key ?>" class="<?= $tab === $key ? 'active' : '' ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>>
                            <i class="bi <?= $icon ?>" aria-hidden="true"></i> <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <section class="account-panel">
                    <?php if ($errors): ?>
                        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>

                    <?php if ($tab === 'orders'): ?>
                        <h2 class="h4 mb-3">My orders</h2>
                        <?php if (!$orders): ?>
                            <div class="empty-state empty-state-sm">
                                <i class="bi bi-receipt" aria-hidden="true"></i>
                                <p>You haven’t placed any orders yet.</p>
                                <a href="catalogue.php" class="btn btn-brand">Start shopping</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle orders-table">
                                    <thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Type</th><th>Status</th><th class="text-end">Total</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td data-label="Order"><a href="order.php?order=<?= urlencode($order['orderNumber']) ?>" class="fw-semibold"><?= e($order['orderNumber']) ?></a></td>
                                                <td data-label="Date"><?= date('j M Y', strtotime($order['orderDate'])) ?></td>
                                                <td data-label="Items"><?= (int) $order['itemCount'] ?></td>
                                                <td data-label="Type"><?= $order['fulfilmentType'] === 'collection' ? 'Collect' : 'Delivery' ?></td>
                                                <td data-label="Status"><span class="<?= statusBadgeClass($order['status']) ?>"><?= e(ORDER_STATUSES[$order['status']]) ?></span></td>
                                                <td data-label="Total" class="text-end"><?= money($order['total']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($tab === 'profile'): ?>
                        <h2 class="h4 mb-3">Details &amp; address</h2>
                        <form method="post" class="needs-validation" novalidate>
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="profile">
                            <div class="row g-3">
                                <div class="col-sm-6"><label class="form-label" for="firstName">First name</label><input class="form-control" id="firstName" name="firstName" value="<?= e($customer['firstName']) ?>" required></div>
                                <div class="col-sm-6"><label class="form-label" for="lastName">Last name</label><input class="form-control" id="lastName" name="lastName" value="<?= e($customer['lastName']) ?>" required></div>
                                <div class="col-sm-6"><label class="form-label" for="emailRO">Email</label><input class="form-control" id="emailRO" value="<?= e($customer['email']) ?>" disabled><div class="form-text">Contact us to change your email.</div></div>
                                <div class="col-sm-6"><label class="form-label" for="phoneNumber">Phone</label><input class="form-control" type="tel" id="phoneNumber" name="phoneNumber" value="<?= e($customer['phoneNumber']) ?>" pattern="\+?[0-9 ()\-]{9,20}"></div>
                                <div class="col-12"><label class="form-label" for="addressLine">Street address</label><input class="form-control" id="addressLine" name="addressLine" value="<?= e($customer['addressLine']) ?>" autocomplete="address-line1"></div>
                                <div class="col-sm-6"><label class="form-label" for="suburb">Suburb</label><input class="form-control" id="suburb" name="suburb" value="<?= e($customer['suburb']) ?>"></div>
                                <div class="col-sm-6"><label class="form-label" for="city">City</label><input class="form-control" id="city" name="city" value="<?= e($customer['city']) ?>"></div>
                                <div class="col-sm-6"><label class="form-label" for="province">Province</label>
                                    <select class="form-select" id="province" name="province"><option value="">Choose…</option>
                                        <?php foreach (PROVINCES as $province): ?><option <?= $customer['province'] === $province ? 'selected' : '' ?>><?= $province ?></option><?php endforeach; ?>
                                    </select></div>
                                <div class="col-sm-6"><label class="form-label" for="postalCode">Postal code</label><input class="form-control" id="postalCode" name="postalCode" value="<?= e($customer['postalCode']) ?>" pattern="[0-9]{4}" inputmode="numeric"></div>
                            </div>
                            <button class="btn btn-brand mt-4">Save details</button>
                        </form>

                    <?php elseif ($tab === 'password'): ?>
                        <h2 class="h4 mb-3">Change password</h2>
                        <form method="post" class="needs-validation narrow-form" novalidate>
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="password">
                            <div class="mb-3"><label class="form-label" for="currentPassword">Current password</label><input type="password" class="form-control" id="currentPassword" name="currentPassword" autocomplete="current-password" required></div>
                            <div class="mb-3"><label class="form-label" for="newPassword">New password</label><input type="password" class="form-control" id="newPassword" name="newPassword" autocomplete="new-password" required minlength="8" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,}"><div class="form-text">At least 8 characters with a letter and a number.</div></div>
                            <div class="mb-3"><label class="form-label" for="confirmPassword">Confirm new password</label><input type="password" class="form-control" id="confirmPassword" name="confirmPassword" autocomplete="new-password" required data-match="newPassword"><div class="invalid-feedback">The passwords must match.</div></div>
                            <button class="btn btn-brand">Change password</button>
                        </form>

                    <?php else: ?>
                        <h2 class="h4 mb-3">Privacy</h2>
                        <p>You accepted our privacy notice on <?= date('j F Y', strtotime($customer['privacyConsentAt'])) ?>. <a href="privacy.php">Read it again</a>.</p>
                        <form method="post" class="mb-4">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="privacy">
                            <label class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="marketingConsent" value="1" <?= $customer['marketingConsent'] ? 'checked' : '' ?>>
                                <span class="form-check-label">Send me emails about new arrivals and specials</span>
                            </label>
                            <button class="btn btn-brand btn-sm mt-3">Save choice</button>
                        </form>
                        <div class="danger-zone">
                            <h3 class="h6">Delete my account</h3>
                            <p class="small mb-2">Under POPIA you can ask us to delete your personal information. We will remove your account and keep only the order records the law requires.</p>
                            <form method="post" data-confirm="Send a request to delete your account?">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="deletion">
                                <button class="btn btn-outline-danger btn-sm">Request deletion</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>

        <?php include 'includes/components/footer.php'; ?>
    </body>
</html>
