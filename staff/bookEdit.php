<?php
    // bookEdit.php adds a new book or edits an existing one
    // a new book starts with zero stock at every store, stock is added on inventory.php

    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/helpers/books.php';
    require_once __DIR__ . '/helpers/auth.php';
    require_once __DIR__ . '/helpers/bookData.php';

    $conn = db();
    $account = requireStaff($conn);
    requireRole($account, ['Admin', 'Staff']);

    $bookID = intParam($_GET, 'id');
    $existing = $bookID ? getBookForEdit($conn, $bookID) : null;
    if ($bookID && !$existing) {
        setFlash('error', 'That book no longer exists.');
        header('Location: books.php');
        exit;
    }

    $input = $existing ?? [
        'title' => '', 'author' => '', 'isbn' => '', 'categoryID' => null, 'publisher' => '', 'publishYear' => '',
        'pageCount' => '', 'language' => 'English', 'format' => 'Paperback', 'price' => '', 'description' => '',
        'coverImageURL' => '', 'isFeatured' => false, 'isActive' => true,
    ];
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = readBookInput();
        $errors = validateBook($conn, $input, $bookID);

        if (!$errors) {
            if ($existing) {
                $saved = updateBook($conn, $bookID, $input);
                $savedID = $bookID;
            } else {
                $savedID = addBook($conn, $input);
                $saved = $savedID !== null;
            }

            if ($saved) {
                logActivity($conn, 'Book', $existing ? 'updated' : 'created', ($existing ? 'Updated' : 'Added') . " book \"{$input['title']}\"", $savedID);
                setFlash('success', "\"{$input['title']}\" was " . ($existing ? 'saved.' : 'added. Add its stock on the Inventory page.'));
                header('Location: ' . ($existing ? 'books.php' : 'inventory.php?q=' . urlencode($input['isbn'])));
                exit;
            }
            $errors[] = 'The book could not be saved. Please try again.';
        }
    }

    $categories = allCategories($conn);
    $stockRows = $existing ? fetchAll($conn,
        "SELECT s.name, COALESCE(i.quantity, 0) AS quantity FROM Store s LEFT JOIN Inventory i ON i.storeID = s.storeID AND i.bookID = ? ORDER BY s.name",
        [$bookID]) : [];

    $pageTitle = $existing ? 'Edit book' : 'Add a book';
    $activePage = 'books';
    $pageActions = '<a href="books.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to books</a>';
    include __DIR__ . '/components/layout.php';
?>

<form method="post" class="edit-layout needs-validation" novalidate>
    <?= csrfField() ?>
    <section class="panel">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="title">Title</label>
                <input class="form-control" id="title" name="title" value="<?= e($input['title']) ?>" required maxlength="255">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="isbn">ISBN</label>
                <input class="form-control" id="isbn" name="isbn" value="<?= e($input['isbn']) ?>" required pattern="[0-9Xx\- ]{10,17}" inputmode="numeric" data-isbn-input>
                <div class="form-text">10 or 13 digits. Used to find the cover.</div>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="author">Author</label>
                <input class="form-control" id="author" name="author" value="<?= e($input['author']) ?>" required maxlength="255">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="categoryID">Category</label>
                <select class="form-select" id="categoryID" name="categoryID" required>
                    <option value="">Choose…</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['categoryID'] ?>" <?= (int) $input['categoryID'] === (int) $category['categoryID'] ? 'selected' : '' ?>><?= e($category['name']) ?><?= $category['isActive'] ? '' : ' (hidden)' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label" for="price">Price (R)</label>
                <input class="form-control" id="price" name="price" type="number" step="0.01" min="0" value="<?= e((string) $input['price']) ?>" required>
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label" for="format">Format</label>
                <select class="form-select" id="format" name="format">
                    <?php foreach (BOOK_FORMATS as $format): ?><option <?= $input['format'] === $format ? 'selected' : '' ?>><?= $format ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label" for="publishYear">Year</label>
                <input class="form-control" id="publishYear" name="publishYear" type="number" min="1450" max="<?= date('Y') + 1 ?>" value="<?= e((string) $input['publishYear']) ?>">
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label" for="pageCount">Pages</label>
                <input class="form-control" id="pageCount" name="pageCount" type="number" min="1" value="<?= e((string) $input['pageCount']) ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label" for="publisher">Publisher</label>
                <input class="form-control" id="publisher" name="publisher" value="<?= e($input['publisher']) ?>" maxlength="150">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="language">Language</label>
                <input class="form-control" id="language" name="language" value="<?= e($input['language']) ?>" maxlength="40">
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="5" required minlength="20" maxlength="5000"><?= e($input['description']) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="coverImageURL">Cover image address <span class="text-muted">(optional)</span></label>
                <input class="form-control" id="coverImageURL" name="coverImageURL" type="url" value="<?= e($input['coverImageURL']) ?>" placeholder="https://…" data-cover-input>
                <div class="form-text">Leave blank to use the Open Library cover for the ISBN.</div>
            </div>
        </div>
    </section>

    <aside class="edit-side">
        <section class="panel">
            <h2 class="panel-title">Cover preview</h2>
            <div class="cover-preview">
                <img src="<?= e(coverSrc($input, 'M')) ?>" alt="Cover preview" data-cover-preview>
            </div>
        </section>
        <section class="panel">
            <h2 class="panel-title">On the shop</h2>
            <label class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" name="isActive" value="1" <?= $input['isActive'] ? 'checked' : '' ?>>
                <span class="form-check-label">Show this book on the shop</span>
            </label>
            <label class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="isFeatured" value="1" <?= $input['isFeatured'] ? 'checked' : '' ?>>
                <span class="form-check-label">Staff pick (home page)</span>
            </label>
        </section>
        <?php if ($stockRows): ?>
            <section class="panel">
                <h2 class="panel-title">Stock by store</h2>
                <ul class="compact-list">
                    <?php foreach ($stockRows as $row): ?>
                        <li><span><?= e($row['name']) ?></span><span class="qty-chip<?= (int) $row['quantity'] === 0 ? ' is-zero' : '' ?>"><?= (int) $row['quantity'] ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <a href="inventory.php?q=<?= urlencode($input['isbn']) ?>" class="panel-link">Change stock</a>
            </section>
        <?php endif; ?>
        <button class="btn btn-brand btn-lg w-100"><?= $existing ? 'Save changes' : 'Add book' ?></button>
    </aside>
</form>

<?php include __DIR__ . '/components/layoutEnd.php'; ?>
