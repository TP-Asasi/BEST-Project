<!-- footer.php is the footer for the shop pages
 contains the store list, page links, the POPIA notice link and the scripts every page needs
-->
<?php
    require_once __DIR__ . '/../helpers/orders.php';
    $footerStores = db() ? fetchAll(db(), "SELECT name FROM Store WHERE isActive = TRUE ORDER BY name LIMIT 6") : [];
?>
<footer class="site-footer">
    <div class="container-xl">
        <div class="footer-grid">
            <div class="footer-about">
                <a class="brand footer-brand" href="index.php">
                    <img src="assets/images/logo.svg" alt="" width="40" height="40">
                    <span class="brand-text">
                        <span class="brand-name">BEST</span>
                        <span class="brand-tagline">Books-Extraordinary-Supply-Town</span>
                    </span>
                </a>
                <p>Your neighbourhood bookshop, now online. Browse every BEST store's shelves from home, then have your books delivered or collect them in store.</p>
            </div>

            <div>
                <p class="footer-heading">Shop</p>
                <ul>
                    <li><a href="catalogue.php">All books</a></li>
                    <li><a href="catalogue.php?sort=newest">New arrivals</a></li>
                    <li><a href="catalogue.php?inStock=1">In stock now</a></li>
                    <li><a href="cart.php">Your cart</a></li>
                </ul>
            </div>

            <div>
                <p class="footer-heading">Our stores</p>
                <ul>
                    <?php foreach ($footerStores as $store): ?>
                        <li><a href="stores.php"><?= e($store['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div>
                <p class="footer-heading">Help</p>
                <ul>
                    <li><a href="account.php">My account &amp; orders</a></li>
                    <li><a href="contact.php">Contact us</a></li>
                    <li><a href="privacy.php">Privacy (POPIA)</a></li>
                    <li><a href="staffLogin.php">Staff portal</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Books-Extraordinary-Supply-Town (BEST). All rights reserved.</p>
            <p>Prices include VAT. Free delivery on orders over <?= money(FREE_DELIVERY_FROM) ?>.</p>
        </div>
    </div>
</footer>

<!-- cookie notice, staff pages set $hideCookieNotice -->
<?php if (empty($hideCookieNotice)): ?>
    <div class="cookie-notice" id="cookieNotice" hidden role="region" aria-label="Cookie notice">
        <p>We use one essential cookie to keep you signed in and remember your cart. No tracking or advertising cookies. <a href="privacy.php">Read our privacy notice</a>.</p>
        <button type="button" class="btn btn-brand btn-sm" id="cookieAccept">Got it</button>
    </div>
<?php endif; ?>

<!-- bootstrap, jquery and the site script -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js?v=<?= filemtime(__DIR__ . '/../../assets/js/main.js') ?>"></script>
