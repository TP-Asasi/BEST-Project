<?php
    // about.php tells the BEST story and why the online store exists
    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/books.php';

    $pageTitle = 'About BEST';
    $activeNav = 'about';
?>
<!DOCTYPE html>
<html lang="en">
    <?php include 'includes/components/header.php'; ?>

    <body>
        <?php include 'includes/components/navBar.php'; ?>

        <main id="main">
            <section class="page-hero">
                <div class="container-xl">
                    <p class="eyebrow">About us</p>
                    <h1>A bookshop with many doors</h1>
                    <p>Books-Extraordinary-Supply-Town (BEST) is a South African book retailer with stores across Gauteng and the Western Cape. Our online store brings all of those shelves together.</p>
                </div>
            </section>

            <section class="container-xl section about-grid">
                <div>
                    <h2>Why we went online</h2>
                    <p>Readers kept telling us the same story: they would phone one store for a book, hear it was sold out, and later find out another BEST store had three copies. Our stock lived in separate systems that did not talk to each other.</p>
                    <p>This website fixes that. Every store’s stock is held in one central database, so when you open a book you see exactly where it is. When you order, we take the copies from the right store straight away, so the number you see is the number we have.</p>
                </div>
                <ul class="value-list">
                    <li><i class="bi bi-diagram-3" aria-hidden="true"></i><div><h3>One view of every shelf</h3><p>Live stock for each book at each store.</p></div></li>
                    <li><i class="bi bi-truck" aria-hidden="true"></i><div><h3>Delivery or collection</h3><p>Nationwide delivery, or free click &amp; collect.</p></div></li>
                    <li><i class="bi bi-shield-lock" aria-hidden="true"></i><div><h3>Your data, protected</h3><p>We follow POPIA and only keep what we need.</p></div></li>
                    <li><i class="bi bi-people" aria-hidden="true"></i><div><h3>Real booksellers</h3><p>Ask any store for recommendations or special orders.</p></div></li>
                </ul>
            </section>

            <section class="cta-band">
                <div class="container-xl">
                    <h2>Find your next favourite book</h2>
                    <a class="btn btn-accent btn-lg" href="catalogue.php">Browse the catalogue</a>
                </div>
            </section>
        </main>

        <?php include 'includes/components/footer.php'; ?>
    </body>
</html>
