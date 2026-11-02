<?php
    // activityLog.php shows admins everything staff have done in the portal, newest first
    // filters for the person, category and dates, with pages of ACTIVITY_PER_PAGE rows

    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/helpers/auth.php';

    $conn = db();
    $account = requireStaff($conn);
    requireRole($account, 'Admin');

    $date = fn ($value) => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    $filters = [
        'category'  => array_key_exists($_GET['category'] ?? '', ACTIVITY_CATEGORIES) ? $_GET['category'] : '',
        'accountID' => intParam($_GET, 'accountID'),
        'from'      => $date($_GET['from'] ?? ''),
        'to'        => $date($_GET['to'] ?? ''),
        'page'      => max(1, intParam($_GET, 'page') ?? 1),
    ];

    $where = ['1 = 1'];
    $params = [];
    if ($filters['category']) { $where[] = 'category = ?'; $params[] = $filters['category']; }
    if ($filters['accountID']) { $where[] = 'accountID = ?'; $params[] = $filters['accountID']; }
    if ($filters['from']) { $where[] = 'createdAt >= ?'; $params[] = $filters['from'] . ' 00:00:00'; }
    if ($filters['to']) { $where[] = 'createdAt <= ?'; $params[] = $filters['to'] . ' 23:59:59'; }
    $whereSql = implode(' AND ', $where);

    $total = (int) fetchValue($conn, "SELECT COUNT(*) FROM ActivityLog WHERE $whereSql", $params);
    $pages = max(1, (int) ceil($total / ACTIVITY_PER_PAGE));
    $rows = fetchAll($conn, "SELECT * FROM ActivityLog WHERE $whereSql ORDER BY createdAt DESC, logID DESC
                             LIMIT " . ACTIVITY_PER_PAGE . " OFFSET " . (($filters['page'] - 1) * ACTIVITY_PER_PAGE), $params);
    $people = fetchAll($conn, "SELECT DISTINCT accountID, accountName FROM ActivityLog WHERE accountID IS NOT NULL ORDER BY accountName");

    $pageTitle = 'Activity Log';
    $activePage = 'activity';
    include __DIR__ . '/components/layout.php';
?>

<form class="filter-bar" method="get">
    <div class="filter-field">
        <label for="accountID" class="form-label">Person</label>
        <select id="accountID" name="accountID" class="form-select">
            <option value="">Everyone</option>
            <?php foreach ($people as $person): ?>
                <option value="<?= (int) $person['accountID'] ?>" <?= $filters['accountID'] === (int) $person['accountID'] ? 'selected' : '' ?>><?= e($person['accountName']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="category" class="form-label">Area</label>
        <select id="category" name="category" class="form-select">
            <option value="">All</option>
            <?php foreach (ACTIVITY_CATEGORIES as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filters['category'] === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field"><label for="from" class="form-label">From</label><input type="date" id="from" name="from" class="form-control" value="<?= e($filters['from']) ?>"></div>
    <div class="filter-field"><label for="to" class="form-label">To</label><input type="date" id="to" name="to" class="form-control" value="<?= e($filters['to']) ?>"></div>
    <button class="btn btn-outline-brand">Filter</button>
</form>

<section class="panel">
    <p class="panel-count"><?= $total ?> entr<?= $total === 1 ? 'y' : 'ies' ?></p>
    <div class="table-wrap">
        <table class="portal-table responsive-table">
            <thead><tr><th>When</th><th>Who</th><th>Area</th><th>What happened</th><th>IP address</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td data-label="When"><?= date('j M Y, H:i:s', strtotime($row['createdAt'])) ?></td>
                        <td data-label="Who"><?= e($row['accountName']) ?></td>
                        <td data-label="Area"><span class="pill<?= $row['action'] === 'failed' ? ' pill-danger' : '' ?>"><?= e(ACTIVITY_CATEGORIES[$row['category']] ?? $row['category']) ?></span></td>
                        <td data-label="What happened"><?= e($row['description']) ?></td>
                        <td data-label="IP address"><code><?= e($row['ipAddress'] ?? '') ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$rows): ?><p class="empty-note">Nothing recorded for those filters.</p><?php endif; ?>
</section>

<?php if ($pages > 1): ?>
    <nav aria-label="Log pages" class="mt-3">
        <ul class="pagination pagination-sm flex-wrap">
            <?php for ($p = max(1, $filters['page'] - 4); $p <= min($pages, $filters['page'] + 4); $p++): ?>
                <li class="page-item <?= $p === $filters['page'] ? 'active' : '' ?>"><a class="page-link" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $p]))) ?>"><?= $p ?></a></li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php include __DIR__ . '/components/layoutEnd.php'; ?>
