<?php
    // layout.php is the shared shell for every staff page: head, header, side navigation and the top of the main area
    // each page sets $pageTitle, $activePage and $account, writes its content, then includes layoutEnd.php
    // the links follow the role, and each page still checks the role itself with requireRole()

    $pageTitle = $pageTitle ?? 'Dashboard';
    $activePage = $activePage ?? 'dashboard';
    $role = $account['role'] ?? 'Staff';

    // badges on the navigation
    $navCounts = [
        'orders'    => (int) fetchValue($conn, "SELECT COUNT(*) FROM CustomerOrder WHERE status = 'pending'"),
        'messages'  => (int) fetchValue($conn, "SELECT COUNT(*) FROM ContactMessage WHERE status = 'new'"),
        'inventory' => (int) fetchValue($conn,
            "SELECT COUNT(*) FROM Inventory i JOIN Book b ON b.bookID = i.bookID AND b.isActive = TRUE
             JOIN Store s ON s.storeID = i.storeID AND s.isActive = TRUE WHERE i.quantity <= i.reorderLevel"
            . (!isAdmin($account) && $account['storeID'] ? " AND i.storeID = " . (int) $account['storeID'] : '')),
    ];

    // key, label, page, roles, icon
    $navLinks = [
        ['dashboard',  'Dashboard',   'dashboard.php',   ['Admin', 'Staff'], 'speedometer2'],
        ['orders',     'Orders',      'orders.php',      ['Admin', 'Staff'], 'receipt'],
        ['books',      'Books',       'books.php',       ['Admin', 'Staff'], 'book'],
        ['inventory',  'Inventory',   'inventory.php',   ['Admin', 'Staff'], 'boxes'],
        ['categories', 'Categories',  'categories.php',  ['Admin', 'Staff'], 'tags'],
        ['customers',  'Customers',   'customers.php',   ['Admin', 'Staff'], 'people'],
        ['messages',   'Messages',    'messages.php',    ['Admin', 'Staff'], 'envelope'],
        ['stores',     'Stores',      'stores.php',      ['Admin'],          'shop'],
        ['accounts',   'Staff & Roles', 'accounts.php',  ['Admin'],          'person-gear'],
        ['activity',   'Activity Log', 'activityLog.php', ['Admin'],         'clock-history'],
    ];
    $navLinks = array_values(array_filter($navLinks, fn ($link) => in_array($role, $link[3], true)));
    $flash = takeFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= e($pageTitle) ?> | BEST Staff Portal</title>
    <link rel="icon" href="../assets/images/logo.svg" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- portal styles are kept separate from the shop css -->
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>">
</head>

<body class="<?= $role === 'Admin' ? 'admin-portal' : 'staff-portal' ?>">
    <a class="skip-link" href="#main">Skip to content</a>

    <!-- header -->
    <header class="portal-header">
        <button class="portal-menu-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#portalNav" aria-controls="portalNav" aria-label="Open menu">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <a class="portal-brand" href="dashboard.php">
            <img src="../assets/images/logo.svg" alt="" width="34" height="34">
            <span><strong>BEST</strong> <span class="portal-brand-sub">Staff Portal</span></span>
        </a>

        <div class="portal-header-actions">
            <a href="../index.php" class="portal-header-link d-none d-md-inline-flex" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> View shop</a>
            <div class="dropdown">
                <button class="portal-user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="portal-avatar" aria-hidden="true"><?= e(mb_substr($account['firstName'], 0, 1) . mb_substr($account['lastName'], 0, 1)) ?></span>
                    <span class="portal-user-text d-none d-sm-flex">
                        <span><?= e($account['firstName'] . ' ' . $account['lastName']) ?></span>
                        <small><?= e($role) ?><?= $account['storeName'] ? ' · ' . e($account['storeName']) : '' ?></small>
                    </span>
                    <i class="bi bi-chevron-down small" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle" aria-hidden="true"></i> My profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="logout.php" method="post">
                            <?= csrfField() ?>
                            <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Log out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <div class="portal-body">
        <!-- side navigation, a slide out menu on small screens -->
        <nav class="portal-nav offcanvas-lg offcanvas-start" id="portalNav" tabindex="-1" aria-label="Portal navigation">
            <div class="offcanvas-header d-lg-none">
                <span class="fw-bold">Menu</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#portalNav" aria-label="Close"></button>
            </div>
            <ul class="portal-nav-list">
                <?php foreach ($navLinks as [$key, $label, $href, , $icon]): ?>
                    <li>
                        <a class="portal-nav-link<?= $activePage === $key ? ' active' : '' ?>" href="<?= $href ?>"<?= $activePage === $key ? ' aria-current="page"' : '' ?>>
                            <i class="bi bi-<?= $icon ?>" aria-hidden="true"></i>
                            <span><?= $label ?></span>
                            <?php if (!empty($navCounts[$key])): ?>
                                <span class="portal-nav-badge<?= $key === 'inventory' ? ' is-warning' : '' ?>"><?= $navCounts[$key] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="portal-nav-role">Signed in as <strong><?= e($role) ?></strong></p>
        </nav>

        <main class="portal-main" id="main">
            <div class="portal-heading">
                <h1><?= e($pageTitle) ?></h1>
                <?php if (!empty($pageActions)): ?>
                    <div class="portal-heading-actions"><?= $pageActions ?></div>
                <?php endif; ?>
            </div>

            <?php if ($flash): ?>
                <div class="portal-alert alert-<?= $flash['type'] === 'error' ? 'error' : ($flash['type'] === 'success' ? 'success' : 'info') ?>" role="status">
                    <i class="bi bi-<?= $flash['type'] === 'error' ? 'exclamation-circle' : ($flash['type'] === 'success' ? 'check-circle' : 'info-circle') ?>" aria-hidden="true"></i>
                    <span><?= e($flash['message']) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="portal-alert alert-error" role="alert">
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                    <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
