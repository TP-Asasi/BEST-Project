<?php
    // index.php is the BEST home page
    // contains the hero with search, categories, featured books, the store availability section and new arrivals

    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/books.php';
    require_once __DIR__ . '/includes/helpers/orders.php';

    $conn = db();
    $featured = $conn ? getBookRow($conn, 'featured', 8) : [];
    $newest = $conn ? getBookRow($conn, 'newest', 4) : [];
    $categories = $conn ? getCategories($conn) : [];
    $stores = $conn ? getActiveStores($conn) : [];
    $bookTotal = $conn ? (int) fetchValue($conn, "SELECT COUNT(*) FROM Book WHERE isActive = TRUE") : 0;

    $pageTitle = 'Home';
    $activeNav = 'home';
?>
<!DOCTYPE html>
<html lang="en">
    <?php include 'includes/components/header.php'; ?>

    <body>
        <?php include 'includes/components/navBar.php'; ?>

        <main id="main">
            <!-- hero -->
            <section class="hero">
                <div class="container-xl hero-inner">
                    <div class="hero-copy">
                        <p class="eyebrow">Books-Extraordinary-Supply-Town</p>
                        <h1>Every shelf in every BEST store, in one place.</h1>
                        <p class="hero-lead">Search our full range, see exactly which store has your book, then have it delivered to your door or collect it the same day.</p>

                        <form class="hero-search" action="catalogue.php" method="get" role="search">
                            <label for="heroSearch" class="visually-hidden">Search books</label>
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input id="heroSearch" type="search" name="q" class="form-control" placeholder="Try “Born a Crime” or “Achebe”">
                            <button type="submit" class="btn btn-accent">Search</button>
                        </form>

                        <ul class="hero-stats">
                            <li><strong><?= $bookTotal ?></strong> titles</li>
                            <li><strong><?= count($stores) ?></strong> stores</li>
                            <li><strong>Free</strong> delivery over <?= money(FREE_DELIVERY_FROM) ?></li>
                        </ul>
                    </div>

                    <!-- stack of featured covers -->
                    <div class="hero-covers" aria-hidden="true">
                        <?php foreach (array_slice($featured, 0, 3) as $i => $book): ?>
                            <div class="hero-cover hero-cover-<?= $i + 1 ?>">
                                <img src="<?= e(coverSrc($book, 'L')) ?>" alt="" data-cover>
                                <span class="cover-fallback" hidden>
                                    <span class="cover-fallback-title"><?= e($book['title']) ?></span>
                                    <span class="cover-fallback-author"><?= e($book['author']) ?></span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <?php if (!$conn): ?>
                <div class="container-xl my-5">
                    <div class="alert alert-warning">The catalogue is unavailable right now. Please try again in a few minutes.</div>
                </div>
            <?php endif; ?>

            <!-- categories -->
            <?php if ($categories): ?>
                <section class="section container-xl" aria-labelledby="categoriesHeading">
                    <div class="section-head">
                        <h2 id="categoriesHeading">Browse by category</h2>
                        <a href="catalogue.php" class="section-link">All books <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </div>
                    <div class="category-grid">
                        <?php foreach ($categories as $category): ?>
                            <a class="category-tile" href="catalogue.php?category=<?= (int) $category['categoryID'] ?>">
                                <i class="bi <?= e($category['icon']) ?>" aria-hidden="true"></i>
                                <span class="category-name"><?= e($category['name']) ?></span>
                                <span class="category-count"><?= (int) $category['bookCount'] ?> books</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- featured -->
            <?php if ($featured): ?>
                <section class="section container-xl" aria-labelledby="featuredHeading">
                    <div class="section-head">
                        <h2 id="featuredHeading">Staff picks</h2>
                        <a href="catalogue.php?sort=featured" class="section-link">See more <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </div>
                    <div class="book-grid">
                        <?php foreach ($featured as $book): ?>
                            <?php include 'includes/components/bookCard.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- how it works -->
            <section class="section how-it-works" aria-labelledby="howHeading">
                <div class="container-xl">
                    <h2 id="howHeading" class="text-center">How shopping with BEST works</h2>
                    <ol class="steps">
                        <li>
                            <i class="bi bi-search-heart" aria-hidden="true"></i>
                            <h3>Find your book</h3>
                            <p>Search by title, author or ISBN and filter by category, price or store.</p>
                        </li>
                        <li>
                            <i class="bi bi-shop" aria-hidden="true"></i>
                            <h3>See where it is</h3>
                            <p>Every book page shows live stock at each of our <?= count($stores) ?> stores.</p>
                        </li>
                        <li>
                            <i class="bi bi-truck" aria-hidden="true"></i>
                            <h3>Deliver or collect</h3>
                            <p>We deliver anywhere in South Africa, or collect in store at no charge.</p>
                        </li>
                    </ol>
                </div>
            </section>

            <!-- new arrivals -->
            <?php if ($newest): ?>
                <section class="section container-xl" aria-labelledby="newHeading">
                    <div class="section-head">
                        <h2 id="newHeading">New on the shelves</h2>
                        <a href="catalogue.php?sort=newest" class="section-link">All new arrivals <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </div>
                    <div class="book-grid">
                        <?php foreach ($newest as $book): ?>
                            <?php include 'includes/components/bookCard.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- stores -->
            <?php if ($stores): ?>
                <section class="section container-xl" aria-labelledby="storesHeading">
                    <div class="section-head">
                        <h2 id="storesHeading">Visit a BEST store</h2>
                        <a href="stores.php" class="section-link">Store details <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </div>
                    <div class="store-strip">
                        <?php foreach ($stores as $store): ?>
                            <a class="store-chip" href="catalogue.php?store=<?= (int) $store['storeID'] ?>&amp;inStock=1">
                                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                <span>
                                    <strong><?= e($store['name']) ?></strong>
                                    <small><?= e($store['city']) ?>, <?= e($store['province']) ?></small>
                                </span>
                                <span class="store-chip-link">Shop this store</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>

        <?php include 'includes/components/footer.php'; ?>
    </body>
</html>
