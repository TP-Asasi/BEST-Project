<?php
    // catalogue.php is the product catalogue with search, filtering, sorting and pages
    // every filter is in the url so a search can be bookmarked or shared

    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/books.php';

    $conn = db();
    $filters = readCatalogueFilters($_GET);
    [$books, $total] = $conn ? searchBooks($conn, $filters) : [[], 0];
    $categories = $conn ? getCategories($conn) : [];
    $stores = $conn ? getActiveStores($conn) : [];
    $pages = max(1, (int) ceil($total / BOOKS_PER_PAGE));

    // the heading reflects the main filter
    $categoryName = null;
    foreach ($categories as $category) {
        if ((int) $category['categoryID'] === $filters['category']) {
            $categoryName = $category['name'];
        }
    }
    $heading = $filters['q'] !== '' ? 'Results for “' . $filters['q'] . '”' : ($categoryName ?? 'All books');

    // link to another page keeping the current filters
    function pageLink(int $page): string
    {
        return 'catalogue.php?' . http_build_query(array_merge($_GET, ['page' => $page]));
    }

    $activeFilterCount = count(array_filter([$filters['category'], $filters['minPrice'] !== null, $filters['maxPrice'] !== null, $filters['store'], $filters['inStock']]));

    $pageTitle = $heading;
    $activeNav = 'catalogue';
?>
<!DOCTYPE html>
<html lang="en">
    <?php include 'includes/components/header.php'; ?>

    <body>
        <?php include 'includes/components/navBar.php'; ?>

        <main id="main" class="container-xl page-pad">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Shop</li>
                </ol>
            </nav>

            <div class="catalogue-layout">
                <!-- filters, a slide out panel on small screens -->
                <aside class="filters offcanvas-lg offcanvas-start" tabindex="-1" id="filterPanel" aria-labelledby="filterHeading">
                    <div class="offcanvas-header">
                        <h2 class="h5 mb-0" id="filterHeading">Filter books</h2>
                        <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#filterPanel" aria-label="Close"></button>
                    </div>
                    <div class="offcanvas-body">
                        <form method="get" action="catalogue.php" class="filter-form" id="filterForm">
                            <div class="mb-3">
                                <label for="filterQ" class="form-label">Keyword</label>
                                <input type="search" id="filterQ" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Title, author or ISBN">
                            </div>

                            <fieldset class="mb-3">
                                <legend class="form-label">Category</legend>
                                <div class="filter-options">
                                    <label class="form-check">
                                        <input class="form-check-input" type="radio" name="category" value="" <?= !$filters['category'] ? 'checked' : '' ?>>
                                        <span class="form-check-label">All categories</span>
                                    </label>
                                    <?php foreach ($categories as $category): ?>
                                        <label class="form-check">
                                            <input class="form-check-input" type="radio" name="category" value="<?= (int) $category['categoryID'] ?>" <?= $filters['category'] === (int) $category['categoryID'] ? 'checked' : '' ?>>
                                            <span class="form-check-label"><?= e($category['name']) ?> <span class="text-muted">(<?= (int) $category['bookCount'] ?>)</span></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>

                            <fieldset class="mb-3">
                                <legend class="form-label">Price (R)</legend>
                                <div class="d-flex gap-2 align-items-center">
                                    <label class="visually-hidden" for="minPrice">Minimum price</label>
                                    <input type="number" min="0" step="1" id="minPrice" name="minPrice" class="form-control" placeholder="Min" value="<?= $filters['minPrice'] !== null ? e((string) $filters['minPrice']) : '' ?>">
                                    <span aria-hidden="true">–</span>
                                    <label class="visually-hidden" for="maxPrice">Maximum price</label>
                                    <input type="number" min="0" step="1" id="maxPrice" name="maxPrice" class="form-control" placeholder="Max" value="<?= $filters['maxPrice'] !== null ? e((string) $filters['maxPrice']) : '' ?>">
                                </div>
                            </fieldset>

                            <div class="mb-3">
                                <label for="filterStore" class="form-label">Available at store</label>
                                <select id="filterStore" name="store" class="form-select">
                                    <option value="">Any store</option>
                                    <?php foreach ($stores as $store): ?>
                                        <option value="<?= (int) $store['storeID'] ?>" <?= $filters['store'] === (int) $store['storeID'] ? 'selected' : '' ?>><?= e($store['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <label class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" role="switch" name="inStock" value="1" <?= $filters['inStock'] ? 'checked' : '' ?>>
                                <span class="form-check-label">In stock only</span>
                            </label>

                            <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
                            <button type="submit" class="btn btn-brand w-100">Apply filters</button>
                            <a href="catalogue.php" class="btn btn-link w-100 mt-1">Clear all</a>
                        </form>
                    </div>
                </aside>

                <!-- results -->
                <section class="catalogue-results" aria-labelledby="resultsHeading">
                    <div class="results-head">
                        <div>
                            <h1 id="resultsHeading" class="page-title"><?= e($heading) ?></h1>
                            <p class="text-muted mb-0"><?= $total ?> book<?= $total === 1 ? '' : 's' ?> found</p>
                        </div>
                        <div class="results-tools">
                            <button class="btn btn-outline-brand d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#filterPanel" aria-controls="filterPanel">
                                <i class="bi bi-funnel" aria-hidden="true"></i> Filters<?= $activeFilterCount ? " ($activeFilterCount)" : '' ?>
                            </button>
                            <form method="get" action="catalogue.php" class="sort-form">
                                <?php foreach ($_GET as $key => $value): ?>
                                    <?php if ($key !== 'sort' && $key !== 'page' && is_string($value)): ?>
                                        <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <label for="sort" class="visually-hidden">Sort by</label>
                                <select id="sort" name="sort" class="form-select" data-submit-on-change>
                                    <?php foreach (BOOK_SORTS as $key => [$label]): ?>
                                        <option value="<?= $key ?>" <?= $filters['sort'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <noscript><button class="btn btn-sm btn-brand" type="submit">Sort</button></noscript>
                            </form>
                        </div>
                    </div>

                    <?php if ($books): ?>
                        <div class="book-grid book-grid-catalogue">
                            <?php foreach ($books as $book): ?>
                                <?php include 'includes/components/bookCard.php'; ?>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($pages > 1): ?>
                            <nav aria-label="Catalogue pages" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?= $filters['page'] <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= e(pageLink($filters['page'] - 1)) ?>">Previous</a>
                                    </li>
                                    <?php for ($p = 1; $p <= $pages; $p++): ?>
                                        <li class="page-item <?= $p === $filters['page'] ? 'active' : '' ?>">
                                            <a class="page-link" href="<?= e(pageLink($p)) ?>" <?= $p === $filters['page'] ? 'aria-current="page"' : '' ?>><?= $p ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $filters['page'] >= $pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= e(pageLink($filters['page'] + 1)) ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-journal-x" aria-hidden="true"></i>
                            <h2>No books match your search</h2>
                            <p>Try a different keyword or remove some filters.</p>
                            <a href="catalogue.php" class="btn btn-brand">Show all books</a>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>

        <?php include 'includes/components/footer.php'; ?>
    </body>
</html>
