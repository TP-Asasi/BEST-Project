<?php
    // books.php lists every book for staff with search and filters
    // quick actions show or hide a book on the shop and mark it as a staff pick, the full form is bookEdit.php

    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/helpers/books.php';
    require_once __DIR__ . '/helpers/auth.php';
    require_once __DIR__ . '/helpers/bookData.php';

    $conn = db();
    $account = requireStaff($conn);
    requireRole($account, ['Admin', 'Staff']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $bookID = intParam($_POST, 'bookID');
        $book = $bookID ? getBookForEdit($conn, $bookID) : null;
        $action = $_POST['action'] ?? '';

        if ($book && in_array($action, ['toggleActive', 'toggleFeatured'], true)) {
            $column = $action === 'toggleActive' ? 'isActive' : 'isFeatured';
            execute($conn, "UPDATE Book SET $column = NOT $column WHERE bookID = ?", [$bookID]);
            $now = !$book[$column];
            $label = $action === 'toggleActive' ? ($now ? 'shown on the shop' : 'hidden from the shop') : ($now ? 'marked as a staff pick' : 'removed from staff picks');
            logActivity($conn, 'Book', $action === 'toggleActive' ? ($now ? 'shown' : 'hidden') : 'featured', "\"{$book['title']}\" $label", $bookID);
            setFlash('success', "\"{$book['title']}\" $label.");
        } else {
            setFlash('error', 'That book could not be updated.');
        }
        header('Location: books.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'category', 'status']))));
        exit;
    }

    $filters = [
        'q'        => is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '',
        'category' => intParam($_GET, 'category'),
        'status'   => in_array($_GET['status'] ?? '', ['active', 'hidden'], true) ? $_GET['status'] : '',
    ];
    $books = listBooks($conn, $filters);
    $categories = allCategories($conn);

    $pageTitle = 'Books';
    $activePage = 'books';
    $pageActions = '<a href="bookEdit.php" class="btn btn-brand"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add book</a>';
    include __DIR__ . '/components/layout.php';
?>

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
        <label for="status" class="form-label">Shown</label>
        <select id="status" name="status" class="form-select" data-submit-on-change>
            <option value="">All</option>
            <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>On the shop</option>
            <option value="hidden" <?= $filters['status'] === 'hidden' ? 'selected' : '' ?>>Hidden</option>
        </select>
    </div>
    <button class="btn btn-outline-brand">Filter</button>
</form>

<section class="panel">
    <p class="panel-count"><?= count($books) ?> book<?= count($books) === 1 ? '' : 's' ?></p>
    <div class="table-wrap">
        <table class="portal-table responsive-table">
            <thead>
                <tr><th>Book</th><th>ISBN</th><th>Category</th><th class="text-end">Price</th><th class="text-end">Stock</th><th>Shop</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($books as $book): ?>
                    <tr class="<?= $book['isActive'] ? '' : 'is-muted' ?>">
                        <td data-label="Book">
                            <div class="table-book">
                                <img src="<?= e(coverSrc($book, 'S')) ?>" alt="" width="36" height="54" loading="lazy" data-cover>
                                <span><a href="bookEdit.php?id=<?= (int) $book['bookID'] ?>" class="fw-semibold"><?= e($book['title']) ?></a><small><?= e($book['author']) ?></small></span>
                            </div>
                        </td>
                        <td data-label="ISBN"><code><?= e($book['isbn']) ?></code></td>
                        <td data-label="Category"><?= e($book['categoryName']) ?></td>
                        <td data-label="Price" class="text-end"><?= money($book['price']) ?></td>
                        <td data-label="Stock" class="text-end"><a href="inventory.php?q=<?= urlencode($book['isbn']) ?>" class="qty-chip<?= (int) $book['totalStock'] === 0 ? ' is-zero' : '' ?>"><?= (int) $book['totalStock'] ?></a></td>
                        <td data-label="Shop">
                            <?= $book['isActive'] ? '<span class="pill pill-success">Shown</span>' : '<span class="pill">Hidden</span>' ?>
                            <?= $book['isFeatured'] ? '<span class="pill pill-accent">Pick</span>' : '' ?>
                        </td>
                        <td data-label="Actions" class="text-end">
                            <div class="row-actions">
                                <a href="bookEdit.php?id=<?= (int) $book['bookID'] ?>" class="icon-btn" title="Edit" aria-label="Edit <?= e($book['title']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="bookID" value="<?= (int) $book['bookID'] ?>">
                                    <input type="hidden" name="action" value="toggleFeatured">
                                    <button class="icon-btn" title="<?= $book['isFeatured'] ? 'Remove from staff picks' : 'Make a staff pick' ?>" aria-label="Toggle staff pick"><i class="bi bi-star<?= $book['isFeatured'] ? '-fill' : '' ?>" aria-hidden="true"></i></button>
                                </form>
                                <form method="post" <?= $book['isActive'] ? 'data-confirm="Hide this book from the shop? Existing orders are not affected."' : '' ?>>
                                    <?= csrfField() ?>
                                    <input type="hidden" name="bookID" value="<?= (int) $book['bookID'] ?>">
                                    <input type="hidden" name="action" value="toggleActive">
                                    <button class="icon-btn" title="<?= $book['isActive'] ? 'Hide from shop' : 'Show on shop' ?>" aria-label="Toggle shown on shop"><i class="bi bi-eye<?= $book['isActive'] ? '-slash' : '' ?>" aria-hidden="true"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$books): ?><p class="empty-note">No books match those filters.</p><?php endif; ?>
</section>

<?php include __DIR__ . '/components/layoutEnd.php'; ?>
