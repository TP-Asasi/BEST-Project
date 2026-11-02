<!-- navBar.php is the navigation bar for the shop pages
 contains the logo, the search box, the page links, the account menu and the cart button
-->
<?php
    require_once __DIR__ . '/../helpers/cart.php';
    require_once __DIR__ . '/../helpers/customerAuth.php';

    global $activeNav;
    $navCustomer = currentCustomer(db());
    $navCartCount = cartCount();
    $navLinks = [
        'home'      => ['Home', 'index.php'],
        'catalogue' => ['Shop Books', 'catalogue.php'],
        'stores'    => ['Our Stores', 'stores.php'],
        'about'     => ['About', 'about.php'],
        'contact'   => ['Contact', 'contact.php'],
    ];
?>
<a class="skip-link" href="#main">Skip to content</a>

<nav class="navbar navbar-expand-lg site-navbar sticky-top" aria-label="Main navigation">
    <div class="container-xl">
        <!-- logo -->
        <a class="navbar-brand brand" href="index.php">
            <img src="assets/images/logo.svg" alt="" width="40" height="40">
            <span class="brand-text">
                <span class="brand-name">BEST</span>
                <span class="brand-tagline">Books-Extraordinary-Supply-Town</span>
            </span>
        </a>

        <!-- cart stays visible next to the menu button on small screens -->
        <div class="d-flex align-items-center gap-1 order-lg-last">
            <a class="nav-icon-link" href="<?= $navCustomer ? 'account.php' : 'login.php' ?>" aria-label="<?= $navCustomer ? 'My account' : 'Sign in' ?>">
                <i class="bi bi-person<?= $navCustomer ? '-check' : '' ?>" aria-hidden="true"></i>
                <span class="d-none d-xl-inline nav-icon-label"><?= $navCustomer ? e($navCustomer['firstName']) : 'Sign in' ?></span>
            </a>
            <a class="nav-icon-link cart-link" href="cart.php" aria-label="Cart, <?= $navCartCount ?> items">
                <i class="bi bi-bag" aria-hidden="true"></i>
                <span class="cart-badge" data-cart-count<?= $navCartCount ? '' : ' hidden' ?>><?= $navCartCount ?></span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#siteMenu" aria-controls="siteMenu" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
        </div>

        <div class="collapse navbar-collapse" id="siteMenu">
            <!-- search goes straight to the catalogue -->
            <form class="nav-search ms-lg-4 my-3 my-lg-0" action="catalogue.php" method="get" role="search">
                <label for="navSearch" class="visually-hidden">Search books</label>
                <i class="bi bi-search" aria-hidden="true"></i>
                <input id="navSearch" class="form-control" type="search" name="q" placeholder="Search title, author or ISBN" value="<?= e($_GET['q'] ?? '') ?>">
            </form>

            <ul class="navbar-nav ms-auto me-lg-2">
                <?php foreach ($navLinks as $key => [$label, $href]): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= ($activeNav ?? '') === $key ? ' active' : '' ?>" href="<?= $href ?>"<?= ($activeNav ?? '') === $key ? ' aria-current="page"' : '' ?>><?= $label ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- one time message after a redirect -->
<?php $navFlash = takeFlash(); ?>
<?php if ($navFlash): ?>
    <div class="container-xl mt-3">
        <div class="alert alert-<?= $navFlash['type'] === 'error' ? 'danger' : ($navFlash['type'] === 'success' ? 'success' : 'info') ?> alert-dismissible fade show site-alert" role="status">
            <?= e($navFlash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>
