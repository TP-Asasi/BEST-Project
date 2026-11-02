<?php
    // stores.php lists every BEST store with its address, hours, contact details and how many titles it has in stock

    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/books.php';

    $conn = db();
    $stores = $conn ? fetchAll($conn,
        "SELECT s.*,
                (SELECT COUNT(*) FROM Inventory i JOIN Book b ON b.bookID = i.bookID AND b.isActive = TRUE
                 WHERE i.storeID = s.storeID AND i.quantity > 0) AS titlesInStock
         FROM Store s WHERE s.isActive = TRUE ORDER BY s.province, s.name") : [];

    $pageTitle = 'Our Stores';
    $activeNav = 'stores';
?>
<!DOCTYPE html>
<html lang="en">
    <?php include 'includes/components/header.php'; ?>

    <body>
        <?php include 'includes/components/navBar.php'; ?>

        <main id="main">
            <section class="page-hero">
                <div class="container-xl">
                    <h1>Our stores</h1>
                    <p>Pop in to browse, or order online and collect from any of our <?= count($stores) ?> stores. Every book page shows what each store has on the shelf right now.</p>
                </div>
            </section>

            <section class="container-xl section">
                <div class="store-grid">
                    <?php foreach ($stores as $store): ?>
                        <article class="store-card">
                            <div class="store-card-head">
                                <i class="bi bi-shop-window" aria-hidden="true"></i>
                                <div>
                                    <h2 class="h5 mb-0"><?= e($store['name']) ?></h2>
                                    <p class="text-muted small mb-0"><?= e($store['city']) ?>, <?= e($store['province']) ?></p>
                                </div>
                            </div>
                            <ul class="store-details">
                                <li><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= e($store['addressLine']) ?>, <?= e($store['city']) ?>, <?= e($store['postalCode']) ?></li>
                                <li><i class="bi bi-clock" aria-hidden="true"></i> <?= e($store['openingHours']) ?></li>
                                <li><i class="bi bi-telephone" aria-hidden="true"></i> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $store['phoneNumber'])) ?>"><?= e($store['phoneNumber']) ?></a></li>
                                <li><i class="bi bi-envelope" aria-hidden="true"></i> <a href="mailto:<?= e($store['email']) ?>"><?= e($store['email']) ?></a></li>
                            </ul>
                            <div class="store-card-foot">
                                <span class="stock-pill stock-in"><?= (int) $store['titlesInStock'] ?> titles in stock</span>
                                <a class="btn btn-outline-brand btn-sm" href="catalogue.php?store=<?= (int) $store['storeID'] ?>&amp;inStock=1">Shop this store</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>

        <?php include 'includes/components/footer.php'; ?>
    </body>
</html>
