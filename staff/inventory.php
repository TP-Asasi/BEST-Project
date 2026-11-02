<?php
    // inventory.php shows stock for every book at every store in one grid and lets staff change it
    // staff can only change their own store's column, admins can change any store
    // changed cells are saved together and each change is recorded as a stock movement

    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/helpers/books.php';
    require_once __DIR__ . '/helpers/auth.php';
    require_once __DIR__ . '/helpers/bookData.php';
    require_once __DIR__ . '/helpers/inventoryData.php';

    $conn = db();
    $account = requireStaff($conn);
    requireRole($account, ['Admin', 'Staff']);

    $stores = inventoryStores($conn);
    $storeNames = array_column($stores, 'name', 'storeID');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $reason = ($_POST['reason'] ?? '') === 'restock' ? 'restock' : 'adjustment';
        $note = postText('note', 255);
        $changed = 0;
        $blocked = 0;

        foreach ((array) ($_POST['qty'] ?? []) as $bookID => $cells) {
            foreach ((array) $cells as $storeID => $value) {
                $bookID = (int) $bookID;
                $storeID = (int) $storeID;
                $original = $_POST['original'][$bookID][$storeID] ?? null;

                // only cells the user actually changed, as whole numbers from 0 to 9999
                if (!is_string($value) || $value === $original || !ctype_digit($value) || (int) $value > 9999 || !isset($storeNames[$storeID])) {
                    continue;
                }
                if (!canEditStore($account, $storeID)) {
                    $blocked++;
                    continue;
                }

                $change = setStockLevel($conn, $bookID, $storeID, (int) $value, $reason, $note, (int) $account['accountID']);
                if ($change !== null) {
                    $changed++;
                    $title = fetchValue($conn, "SELECT title FROM Book WHERE bookID = ?", [$bookID]);
                    logActivity($conn, 'Inventory', $reason,
                        sprintf('%s "%s" at %s by %+d (now %d)', $reason === 'restock' ? 'Restocked' : 'Adjusted', $title, $storeNames[$storeID], $change, (int) $value), $bookID);
                }
            }
        }

        if ($changed) {
            setFlash('success', $changed . ' stock level' . ($changed === 1 ? '' : 's') . ' updated.' . ($blocked ? " $blocked change(s) at other stores were ignored." : ''));
        } else {
            setFlash($blocked ? 'error' : 'info', $blocked ? 'You can only change stock at your own store.' : 'No stock levels were changed.');
        }
        header('Location: inventory.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'category', 'low', 'store', 'page']))));
        exit;
    }

    $filters = [
        'q'        => is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '',
        'category' => intParam($_GET, 'category'),
        'low'      => !empty($_GET['low']),
        'store'    => intParam($_GET, 'store') ?? (!isAdmin($account) && $account['storeID'] ? (int) $account['storeID'] : null),
        'page'     => max(1, intParam($_GET, 'page') ?? 1),
    ];
    [$rows, $total] = inventoryMatrix($conn, $filters, $stores);
    $pages = max(1, (int) ceil($total / INVENTORY_PER_PAGE));
    $categories = allCategories($conn);
    $movements = recentMovements($conn, isAdmin($account) ? null : ($account['storeID'] ? (int) $account['storeID'] : null));

    $pageTitle = 'Inventory';
    $activePage = 'inventory';
    include __DIR__ . '/components/layout.php';
?>

<p class="portal-subtitle">
    Stock for each book at each store.
    <?= isAdmin($account) || !$account['storeID'] ? 'You can change any store.' : 'You can change stock at <strong>' . e($account['storeName']) . '</strong>; other stores are read only.' ?>
</p>

<form class="filter-bar" method="get">
    <div class="filter-field grow">
        <label for="q" class="form-label">Search</label>
        <input type="search" id="q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Title, author or ISBN">
    </div>
    <div class="filter-field">
        <label for="category" class="form-label">Category</label>
        <select id="category" name="category" class="form-select" data-submit-on-change>
            <option value="">All</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['categoryID'] ?>" <?= $filters['category'] === (int) $category['categoryID'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="store" class="form-label">Low stock at</label>
        <select id="store" name="store" class="form-select">
            <option value="">Any store</option>
            <?php foreach ($stores as $store): ?>
                <option value="<?= (int) $store['storeID'] ?>" <?= $filters['store'] === (int) $store['storeID'] ? 'selected' : '' ?>><?= e($store['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <label class="form-check form-switch filter-switch">
        <input class="form-check-input" type="checkbox" role="switch" name="low" value="1" <?= $filters['low'] ? 'checked' : '' ?> data-submit-on-change>
        <span class="form-check-label">Low stock only</span>
    </label>
    <button class="btn btn-outline-brand">Filter</button>
</form>

<form method="post" class="panel inventory-panel" id="inventoryForm">
    <?= csrfField() ?>
    <div class="table-wrap">
        <table class="portal-table inventory-table">
            <thead>
                <tr>
                    <th scope="col">Book</th>
                    <?php foreach ($stores as $store): ?>
                        <th scope="col" class="text-center<?= canEditStore($account, (int) $store['storeID']) ? '' : ' is-readonly' ?>">
                            <?= e(str_replace('BEST ', '', $store['name'])) ?><?= $store['isActive'] ? '' : ' <small>(closed)</small>' ?>
                        </th>
                    <?php endforeach; ?>
                    <th scope="col" class="text-center">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <th scope="row">
                            <a href="bookEdit.php?id=<?= (int) $row['bookID'] ?>"><?= e($row['title']) ?></a>
                            <small><?= e($row['author']) ?> · <?= e($row['isbn']) ?></small>
                        </th>
                        <?php foreach ($stores as $store): ?>
                            <?php
                                $cell = $row['stock'][$store['storeID']];
                                $qty = (int) $cell['quantity'];
                                $editable = canEditStore($account, (int) $store['storeID']);
                                $state = $qty === 0 ? 'is-zero' : ($qty <= (int) $cell['reorderLevel'] ? 'is-low' : '');
                            ?>
                            <td class="text-center">
                                <input type="hidden" name="original[<?= (int) $row['bookID'] ?>][<?= (int) $store['storeID'] ?>]" value="<?= $qty ?>">
                                <input type="number" min="0" max="9999" class="stock-input <?= $state ?>" value="<?= $qty ?>"
                                       name="qty[<?= (int) $row['bookID'] ?>][<?= (int) $store['storeID'] ?>]"
                                       data-original="<?= $qty ?>" <?= $editable ? '' : 'readonly tabindex="-1"' ?>
                                       aria-label="<?= e($row['title']) ?> at <?= e($store['name']) ?>">
                            </td>
                        <?php endforeach; ?>
                        <td class="text-center fw-semibold"><?= (int) $row['total'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$rows): ?><p class="empty-note">No books match those filters.</p><?php endif; ?>

    <!-- save bar, shows the number of changed cells -->
    <div class="save-bar" data-save-bar>
        <span class="save-bar-count" data-change-count>No changes</span>
        <div class="save-bar-fields">
            <label class="visually-hidden" for="reason">Reason</label>
            <select id="reason" name="reason" class="form-select form-select-sm">
                <option value="restock">Restock (delivery received)</option>
                <option value="adjustment">Adjustment (count, damage or loss)</option>
            </select>
            <label class="visually-hidden" for="note">Note</label>
            <input id="note" name="note" class="form-control form-control-sm" placeholder="Note, e.g. supplier invoice number" maxlength="255">
            <button class="btn btn-brand btn-sm" data-save-button disabled>Save stock</button>
        </div>
    </div>
</form>

<?php if ($pages > 1): ?>
    <nav aria-label="Inventory pages">
        <ul class="pagination pagination-sm">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <li class="page-item <?= $p === $filters['page'] ? 'active' : '' ?>"><a class="page-link" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $p]))) ?>"><?= $p ?></a></li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<section class="panel mt-4">
    <div class="panel-head"><h2>Recent stock movements</h2></div>
    <div class="table-wrap">
        <table class="portal-table responsive-table">
            <thead><tr><th>When</th><th>Book</th><th>Store</th><th class="text-end">Change</th><th>Reason</th><th>By</th></tr></thead>
            <tbody>
                <?php foreach ($movements as $move): ?>
                    <tr>
                        <td data-label="When"><?= date('j M H:i', strtotime($move['createdAt'])) ?></td>
                        <td data-label="Book"><?= e($move['title']) ?></td>
                        <td data-label="Store"><?= e($move['storeName']) ?></td>
                        <td data-label="Change" class="text-end <?= $move['quantityChange'] > 0 ? 'text-success' : 'text-danger' ?> fw-semibold"><?= sprintf('%+d', $move['quantityChange']) ?></td>
                        <td data-label="Reason"><span class="pill"><?= e(ucfirst($move['reason'])) ?></span> <?= $move['orderNumber'] ? e($move['orderNumber']) : e($move['note']) ?></td>
                        <td data-label="By"><?= e(trim(($move['firstName'] ?? '') . ' ' . ($move['lastName'] ?? '')) ?: 'System') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/components/layoutEnd.php'; ?>
