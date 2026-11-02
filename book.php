<?php
    // book.php is the product details page
    // shows the cover, description, details, stock at every store and related books

    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/books.php';
    require_once __DIR__ . '/includes/helpers/cart.php';

    $conn = db();
    $bookID = intParam($_GET, 'id');
    $book = ($conn && $bookID) ? getBook($conn, $bookID) : null;

    if (!$book) {
        http_response_code(404);
        include __DIR__ . '/404.php';
        exit;
    }

    $availability = getBookAvailability($conn, $bookID);
    $related = getRelatedBooks($conn, $book);
    $totalStock = (int) $book['totalStock'];
    [$stockClass, $stockLabel] = stockStatus($totalStock, 5);
    $inCart = cartItems()[$bookID] ?? 0;
    $maxAdd = max(0, min($totalStock, CART_MAX_PER_LINE) - $inCart);

    $pageTitle = $book['title'];
    $pageDescription = mb_substr($book['description'], 0, 155);
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
                    <li class="breadcrumb-item"><a href="catalogue.php">Shop</a></li>
                    <li class="breadcrumb-item"><a href="catalogue.php?category=<?= (int) $book['categoryID'] ?>"><?= e($book['categoryName']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($book['title']) ?></li>
                </ol>
            </nav>

            <article class="book-detail">
                <div class="book-detail-cover">
                    <img src="<?= e(coverSrc($book, 'L')) ?>" alt="Cover of <?= e($book['title']) ?>" width="320" height="480" data-cover>
                    <span class="cover-fallback cover-fallback-lg" hidden>
                        <span class="cover-fallback-title"><?= e($book['title']) ?></span>
                        <span class="cover-fallback-author"><?= e($book['author']) ?></span>
                    </span>
                </div>

                <div class="book-detail-info">
                    <p class="book-card-category"><?= e($book['categoryName']) ?> · <?= e($book['format']) ?></p>
                    <h1 class="book-detail-title"><?= e($book['title']) ?></h1>
                    <p class="book-detail-author">by <a href="catalogue.php?q=<?= urlencode($book['author']) ?>"><?= e($book['author']) ?></a></p>

                    <div class="book-detail-buy">
                        <p class="book-detail-price"><?= money($book['price']) ?></p>
                        <p class="stock-pill stock-<?= $stockClass ?>"><?= $totalStock > 0 ? e($stockLabel) . ' · ' . $totalStock . ' across our stores' : 'Out of stock at all stores' ?></p>

                        <?php if ($maxAdd > 0): ?>
                            <form class="add-to-cart-form buy-form" action="cart-action.php" method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="bookID" value="<?= (int) $book['bookID'] ?>">
                                <div class="qty-stepper" data-qty-stepper>
                                    <button type="button" class="qty-btn" data-step="-1" aria-label="One fewer"><i class="bi bi-dash" aria-hidden="true"></i></button>
                                    <label for="qty" class="visually-hidden">Quantity</label>
                                    <input type="number" id="qty" name="quantity" value="1" min="1" max="<?= $maxAdd ?>" inputmode="numeric">
                                    <button type="button" class="qty-btn" data-step="1" aria-label="One more"><i class="bi bi-plus" aria-hidden="true"></i></button>
                                </div>
                                <button type="submit" class="btn btn-accent btn-lg flex-grow-1"><i class="bi bi-bag-plus" aria-hidden="true"></i> Add to cart</button>
                            </form>
                            <?php if ($inCart): ?>
                                <p class="small text-muted mt-2">You already have <?= $inCart ?> in your <a href="cart.php">cart</a>.</p>
                            <?php endif; ?>
                        <?php elseif ($totalStock > 0): ?>
                            <p class="small">You have the most copies we can supply in your <a href="cart.php">cart</a>.</p>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary btn-lg" disabled>Out of stock</button>
                            <p class="small text-muted mt-2">Ask any store to order it in, or <a href="contact.php?subject=<?= urlencode('Stock request: ' . $book['title']) ?>">send us a stock request</a>.</p>
                        <?php endif; ?>
                    </div>

                    <h2 class="h5 mt-4">About this book</h2>
                    <p class="book-detail-description"><?= nl2br(e($book['description'])) ?></p>

                    <dl class="book-facts">
                        <div><dt>ISBN</dt><dd><?= e($book['isbn']) ?></dd></div>
                        <?php if ($book['publisher']): ?><div><dt>Publisher</dt><dd><?= e($book['publisher']) ?></dd></div><?php endif; ?>
                        <?php if ($book['publishYear']): ?><div><dt>Published</dt><dd><?= (int) $book['publishYear'] ?></dd></div><?php endif; ?>
                        <?php if ($book['pageCount']): ?><div><dt>Pages</dt><dd><?= (int) $book['pageCount'] ?></dd></div><?php endif; ?>
                        <div><dt>Format</dt><dd><?= e($book['format']) ?></dd></div>
                        <div><dt>Language</dt><dd><?= e($book['language']) ?></dd></div>
                    </dl>
                </div>

                <!-- stock at each store -->
                <section class="availability" aria-labelledby="availabilityHeading">
                    <h2 id="availabilityHeading" class="h5"><i class="bi bi-shop" aria-hidden="true"></i> Availability by store</h2>
                    <ul class="availability-list">
                        <?php foreach ($availability as $row): ?>
                            <?php [$rowClass, $rowLabel] = stockStatus((int) $row['quantity'], (int) $row['reorderLevel']); ?>
                            <li>
                                <span class="availability-store">
                                    <strong><?= e($row['name']) ?></strong>
                                    <small><?= e($row['city']) ?></small>
                                </span>
                                <span class="stock-pill stock-<?= $rowClass ?>"><?= $rowClass === 'in' ? (int) $row['quantity'] . ' in stock' : e($rowLabel) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="small text-muted mb-0">Choose <strong>click &amp; collect</strong> at checkout to pick up from a store that has it. Delivery orders are sent from whichever store has stock.</p>
                </section>
            </article>

            <?php if ($related): ?>
                <section class="section" aria-labelledby="relatedHeading">
                    <div class="section-head">
                        <h2 id="relatedHeading">More in <?= e($book['categoryName']) ?></h2>
                        <a class="section-link" href="catalogue.php?category=<?= (int) $book['categoryID'] ?>">View all <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </div>
                    <div class="book-grid">
                        <?php foreach ($related as $book): ?>
                            <?php include 'includes/components/bookCard.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>

        <?php include 'includes/components/footer.php'; ?>
    </body>
</html>
