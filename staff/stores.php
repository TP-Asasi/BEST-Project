<?php
    // stores.php lets admins add stores, edit their details and close or reopen them
    // a closed store is hidden from the shop and its stock no longer counts as available
    // a new store gets a zero stock row for every book

    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/helpers/auth.php';

    $conn = db();
    $account = requireStaff($conn);
    requireRole($account, 'Admin');
    $errors = [];
    $editID = intParam($_GET, 'edit');

    $blank = ['name' => '', 'addressLine' => '', 'city' => '', 'province' => 'Gauteng', 'postalCode' => '', 'phoneNumber' => '', 'email' => '', 'openingHours' => 'Mon–Sat 09:00–18:00', 'isActive' => 1];
    $input = $editID ? (fetchOne($conn, "SELECT * FROM Store WHERE storeID = ?", [$editID]) ?? $blank) : $blank;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $storeID = intParam($_POST, 'storeID');

        if ($action === 'toggle' && $storeID) {
            $store = fetchOne($conn, "SELECT name, isActive FROM Store WHERE storeID = ?", [$storeID]);
            $openOrders = (int) fetchValue($conn, "SELECT COUNT(*) FROM CustomerOrder WHERE collectionStoreID = ? AND status IN ('pending', 'processing', 'ready')", [$storeID]);
            if ($store && $store['isActive'] && $openOrders) {
                setFlash('error', "{$store['name']} has $openOrders open collection order(s). Finish or cancel them first.");
            } elseif ($store) {
                execute($conn, "UPDATE Store SET isActive = NOT isActive WHERE storeID = ?", [$storeID]);
                logActivity($conn, 'Store', $store['isActive'] ? 'closed' : 'reopened', ($store['isActive'] ? 'Closed' : 'Reopened') . " {$store['name']}", $storeID);
                setFlash('success', $store['name'] . ($store['isActive'] ? ' closed. It no longer shows on the shop.' : ' reopened.'));
            }
            header('Location: stores.php');
            exit;
        }

        if ($action === 'save') {
            $input = [
                'name'         => postText('name', 120),
                'addressLine'  => postText('addressLine', 255),
                'city'         => postText('city', 100),
                'province'     => postText('province', 60),
                'postalCode'   => postText('postalCode', 10),
                'phoneNumber'  => postText('phoneNumber', 30),
                'email'        => strtolower(postText('email', 255)),
                'openingHours' => postText('openingHours', 255),
            ];

            if (in_array('', [$input['name'], $input['addressLine'], $input['city'], $input['openingHours']], true)) {
                $errors[] = 'Name, address, city and opening hours are required.';
            }
            if (!in_array($input['province'], PROVINCES, true)) {
                $errors[] = 'Choose a province.';
            }
            if (!isValidPostalCode($input['postalCode'])) {
                $errors[] = 'Enter a 4 digit postal code.';
            }
            if (!isValidPhone($input['phoneNumber'])) {
                $errors[] = 'Enter a valid phone number.';
            }
            if (!isValidEmail($input['email'])) {
                $errors[] = 'Enter a valid email address.';
            }
            if (fetchValue($conn, "SELECT storeID FROM Store WHERE name = ? AND storeID <> ?", [$input['name'], $storeID ?? 0])) {
                $errors[] = 'Another store already has that name.';
            }

            if (!$errors) {
                $values = array_values($input);
                if ($storeID) {
                    $values[] = $storeID;
                    execute($conn, "UPDATE Store SET name = ?, addressLine = ?, city = ?, province = ?, postalCode = ?, phoneNumber = ?, email = ?, openingHours = ? WHERE storeID = ?", $values);
                    logActivity($conn, 'Store', 'updated', "Updated store {$input['name']}", $storeID);
                } else {
                    $conn->begin_transaction();
                    execute($conn, "INSERT INTO Store (name, addressLine, city, province, postalCode, phoneNumber, email, openingHours) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", $values);
                    $storeID = (int) $conn->insert_id;
                    execute($conn, "INSERT INTO Inventory (bookID, storeID, quantity) SELECT bookID, ?, 0 FROM Book", [$storeID]);
                    $conn->commit();
                    logActivity($conn, 'Store', 'created', "Added store {$input['name']}", $storeID);
                }
                setFlash('success', "{$input['name']} saved.");
                header('Location: stores.php');
                exit;
            }
            $editID = $storeID;
        }
    }

    $stores = fetchAll($conn,
        "SELECT s.*,
                (SELECT COALESCE(SUM(quantity), 0) FROM Inventory i WHERE i.storeID = s.storeID) AS units,
                (SELECT COUNT(*) FROM Inventory i WHERE i.storeID = s.storeID AND i.quantity > 0) AS titles,
                (SELECT COUNT(*) FROM Staff st WHERE st.storeID = s.storeID) AS staffCount
         FROM Store s ORDER BY s.isActive DESC, s.name");

    $pageTitle = 'Stores';
    $activePage = 'stores';
    include __DIR__ . '/components/layout.php';
?>

<div class="panel-grid panel-grid-wide">
    <section class="panel">
        <div class="table-wrap">
            <table class="portal-table responsive-table">
                <thead><tr><th>Store</th><th>Contact</th><th>Stock</th><th>Staff</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($stores as $store): ?>
                        <tr class="<?= $store['isActive'] ? '' : 'is-muted' ?>">
                            <td data-label="Store"><strong><?= e($store['name']) ?></strong><small class="d-block text-muted"><?= e($store['addressLine']) ?>, <?= e($store['city']) ?>, <?= e($store['province']) ?></small></td>
                            <td data-label="Contact"><?= e($store['phoneNumber']) ?><small class="d-block"><?= e($store['email']) ?></small></td>
                            <td data-label="Stock"><?= (int) $store['units'] ?> units<small class="d-block text-muted"><?= (int) $store['titles'] ?> titles</small></td>
                            <td data-label="Staff"><?= (int) $store['staffCount'] ?></td>
                            <td data-label="Status"><?= $store['isActive'] ? '<span class="pill pill-success">Open</span>' : '<span class="pill pill-danger">Closed</span>' ?></td>
                            <td data-label="Actions" class="text-end">
                                <div class="row-actions">
                                    <a class="icon-btn" href="stores.php?edit=<?= (int) $store['storeID'] ?>" aria-label="Edit <?= e($store['name']) ?>" title="Edit"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                    <a class="icon-btn" href="inventory.php?store=<?= (int) $store['storeID'] ?>&amp;low=1" aria-label="Low stock at <?= e($store['name']) ?>" title="Low stock"><i class="bi bi-boxes" aria-hidden="true"></i></a>
                                    <form method="post" data-confirm="<?= $store['isActive'] ? 'Close this store? It will be hidden from the shop and its stock will not be sold online.' : 'Reopen this store?' ?>">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="storeID" value="<?= (int) $store['storeID'] ?>">
                                        <button class="icon-btn" aria-label="<?= $store['isActive'] ? 'Close' : 'Reopen' ?> <?= e($store['name']) ?>" title="<?= $store['isActive'] ? 'Close store' : 'Reopen store' ?>"><i class="bi bi-<?= $store['isActive'] ? 'door-closed' : 'door-open' ?>" aria-hidden="true"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" id="storeForm">
        <h2 class="panel-title"><?= $editID ? 'Edit ' . e($input['name']) : 'Add a store' ?></h2>
        <form method="post" class="needs-validation" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <?php if ($editID): ?><input type="hidden" name="storeID" value="<?= (int) $editID ?>"><?php endif; ?>
            <div class="mb-2"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" value="<?= e($input['name']) ?>" required placeholder="BEST Durban"></div>
            <div class="mb-2"><label class="form-label" for="addressLine">Address</label><input class="form-control" id="addressLine" name="addressLine" value="<?= e($input['addressLine']) ?>" required></div>
            <div class="row g-2 mb-2">
                <div class="col-7"><label class="form-label" for="city">City</label><input class="form-control" id="city" name="city" value="<?= e($input['city']) ?>" required></div>
                <div class="col-5"><label class="form-label" for="postalCode">Postal code</label><input class="form-control" id="postalCode" name="postalCode" value="<?= e($input['postalCode']) ?>" required pattern="[0-9]{4}"></div>
            </div>
            <div class="mb-2"><label class="form-label" for="province">Province</label>
                <select class="form-select" id="province" name="province"><?php foreach (PROVINCES as $province): ?><option <?= $input['province'] === $province ? 'selected' : '' ?>><?= $province ?></option><?php endforeach; ?></select></div>
            <div class="mb-2"><label class="form-label" for="phoneNumber">Phone</label><input class="form-control" id="phoneNumber" name="phoneNumber" type="tel" value="<?= e($input['phoneNumber']) ?>" required></div>
            <div class="mb-2"><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" value="<?= e($input['email']) ?>" required></div>
            <div class="mb-3"><label class="form-label" for="openingHours">Opening hours</label><input class="form-control" id="openingHours" name="openingHours" value="<?= e($input['openingHours']) ?>" required></div>
            <button class="btn btn-brand w-100"><?= $editID ? 'Save store' : 'Add store' ?></button>
            <?php if ($editID): ?><a href="stores.php" class="btn btn-link w-100">Cancel</a><?php endif; ?>
        </form>
    </section>
</div>

<?php include __DIR__ . '/components/layoutEnd.php'; ?>
