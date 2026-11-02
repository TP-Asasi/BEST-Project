<?php
    // categories.php adds, renames, orders and hides book categories
    // a category can only be deleted when no books use it, otherwise it is hidden instead

    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/helpers/auth.php';
    require_once __DIR__ . '/helpers/bookData.php';

    $conn = db();
    $account = requireStaff($conn);
    requireRole($account, ['Admin', 'Staff']);
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $categoryID = intParam($_POST, 'categoryID');
        $input = [
            'name'        => postText('name', 100),
            'description' => postText('description', 255),
            'icon'        => preg_replace('/[^a-z0-9-]/', '', strtolower(postText('icon', 50))) ?: 'bi-book',
            'sortOrder'   => (int) postText('sortOrder', 5),
            'isActive'    => !empty($_POST['isActive']) ? 1 : 0,
        ];
        if (!str_starts_with($input['icon'], 'bi-')) {
            $input['icon'] = 'bi-' . $input['icon'];
        }

        if ($action === 'save') {
            if ($input['name'] === '') {
                $errors[] = 'Enter a category name.';
            } elseif (fetchValue($conn, "SELECT categoryID FROM Category WHERE name = ? AND categoryID <> ?", [$input['name'], $categoryID ?? 0])) {
                $errors[] = 'A category with that name already exists.';
            }

            if (!$errors) {
                if ($categoryID) {
                    execute($conn, "UPDATE Category SET name = ?, description = ?, icon = ?, sortOrder = ?, isActive = ? WHERE categoryID = ?",
                        [$input['name'], $input['description'] ?: null, $input['icon'], $input['sortOrder'], $input['isActive'], $categoryID]);
                } else {
                    execute($conn, "INSERT INTO Category (name, description, icon, sortOrder, isActive) VALUES (?, ?, ?, ?, ?)",
                        [$input['name'], $input['description'] ?: null, $input['icon'], $input['sortOrder'], 1]);
                    $categoryID = (int) $conn->insert_id;
                }
                logActivity($conn, 'Category', $action === 'save' && isset($_POST['categoryID']) ? 'updated' : 'created', "Saved category \"{$input['name']}\"", $categoryID);
                setFlash('success', "Category \"{$input['name']}\" saved.");
                header('Location: categories.php');
                exit;
            }
        } elseif ($action === 'delete' && $categoryID) {
            $category = fetchOne($conn, "SELECT name FROM Category WHERE categoryID = ?", [$categoryID]);
            if ((int) fetchValue($conn, "SELECT COUNT(*) FROM Book WHERE categoryID = ?", [$categoryID]) > 0) {
                setFlash('error', 'That category still has books. Move them or hide the category instead.');
            } elseif ($category && execute($conn, "DELETE FROM Category WHERE categoryID = ?", [$categoryID])) {
                logActivity($conn, 'Category', 'deleted', "Deleted category \"{$category['name']}\"", $categoryID);
                setFlash('success', "Category \"{$category['name']}\" deleted.");
            }
            header('Location: categories.php');
            exit;
        }
    }

    $categories = allCategories($conn);

    $pageTitle = 'Categories';
    $activePage = 'categories';
    include __DIR__ . '/components/layout.php';
?>

<div class="panel-grid panel-grid-wide">
    <section class="panel">
        <div class="table-wrap">
            <table class="portal-table responsive-table">
                <thead><tr><th>Order</th><th>Category</th><th>Icon</th><th>Books</th><th>Shown</th><th class="text-end">Save</th></tr></thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <?php $formID = 'cat' . (int) $category['categoryID']; ?>
                        <tr class="<?= $category['isActive'] ? '' : 'is-muted' ?>">
                            <td data-label="Order"><input form="<?= $formID ?>" class="form-control form-control-sm order-input" type="number" name="sortOrder" value="<?= (int) $category['sortOrder'] ?>" aria-label="Sort order"></td>
                            <td data-label="Category">
                                <input form="<?= $formID ?>" class="form-control form-control-sm mb-1" name="name" value="<?= e($category['name']) ?>" aria-label="Name" required>
                                <input form="<?= $formID ?>" class="form-control form-control-sm" name="description" value="<?= e($category['description']) ?>" aria-label="Description" placeholder="Short description">
                            </td>
                            <td data-label="Icon">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="bi <?= e($category['icon']) ?>" aria-hidden="true"></i></span>
                                    <input form="<?= $formID ?>" class="form-control" name="icon" value="<?= e($category['icon']) ?>" aria-label="Bootstrap icon name">
                                </div>
                            </td>
                            <td data-label="Books"><a href="books.php?category=<?= (int) $category['categoryID'] ?>"><?= (int) $category['bookCount'] ?></a></td>
                            <td data-label="Shown"><input form="<?= $formID ?>" class="form-check-input" type="checkbox" name="isActive" value="1" <?= $category['isActive'] ? 'checked' : '' ?> aria-label="Shown on the shop"></td>
                            <td data-label="Save" class="text-end">
                                <div class="row-actions">
                                    <form method="post" id="<?= $formID ?>">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="save">
                                        <input type="hidden" name="categoryID" value="<?= (int) $category['categoryID'] ?>">
                                        <button class="icon-btn" aria-label="Save <?= e($category['name']) ?>" title="Save"><i class="bi bi-check-lg" aria-hidden="true"></i></button>
                                    </form>
                                    <?php if ((int) $category['bookCount'] === 0): ?>
                                        <form method="post" data-confirm="Delete this category?">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="categoryID" value="<?= (int) $category['categoryID'] ?>">
                                            <button class="icon-btn text-danger" aria-label="Delete <?= e($category['name']) ?>" title="Delete"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Add a category</h2>
        <form method="post" class="needs-validation" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <div class="mb-3"><label class="form-label" for="newName">Name</label><input class="form-control" id="newName" name="name" required maxlength="100"></div>
            <div class="mb-3"><label class="form-label" for="newDescription">Description</label><input class="form-control" id="newDescription" name="description" maxlength="255"></div>
            <div class="row g-2 mb-3">
                <div class="col-8"><label class="form-label" for="newIcon">Icon</label><input class="form-control" id="newIcon" name="icon" value="bi-book"><div class="form-text">Any <a href="https://icons.getbootstrap.com" target="_blank" rel="noopener">Bootstrap icon</a> name.</div></div>
                <div class="col-4"><label class="form-label" for="newOrder">Order</label><input class="form-control" id="newOrder" name="sortOrder" type="number" value="<?= count($categories) + 1 ?>"></div>
            </div>
            <button class="btn btn-brand w-100">Add category</button>
        </form>
    </section>
</div>

<?php include __DIR__ . '/components/layoutEnd.php'; ?>
