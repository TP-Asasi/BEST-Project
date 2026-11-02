<?php
    // bookCard.php is one book in a grid, used on the home page, catalogue and related books
    // expects $book with bookID, isbn, title, author, price, coverImageURL and totalStock
    [$cardStockClass, $cardStockLabel] = stockStatus((int) $book['totalStock'], 5);
?>
<article class="book-card">
    <a class="book-card-cover" href="book.php?id=<?= (int) $book['bookID'] ?>" tabindex="-1" aria-hidden="true">
        <img src="<?= e(coverSrc($book)) ?>" alt="" loading="lazy" width="180" height="270" data-cover>
        <!-- drawn when the cover image is missing -->
        <span class="cover-fallback" hidden>
            <span class="cover-fallback-title"><?= e($book['title']) ?></span>
            <span class="cover-fallback-author"><?= e($book['author']) ?></span>
        </span>
    </a>
    <div class="book-card-body">
        <?php if (!empty($book['categoryName'])): ?>
            <p class="book-card-category"><?= e($book['categoryName']) ?></p>
        <?php endif; ?>
        <h3 class="book-card-title"><a href="book.php?id=<?= (int) $book['bookID'] ?>"><?= e($book['title']) ?></a></h3>
        <p class="book-card-author"><?= e($book['author']) ?></p>
        <div class="book-card-footer">
            <span class="book-card-price"><?= money($book['price']) ?></span>
            <span class="stock-pill stock-<?= $cardStockClass ?>"><?= e($cardStockLabel) ?></span>
        </div>
        <?php if ((int) $book['totalStock'] > 0): ?>
            <form class="add-to-cart-form" action="cart-action.php" method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="bookID" value="<?= (int) $book['bookID'] ?>">
                <button type="submit" class="btn btn-brand btn-sm w-100"><i class="bi bi-bag-plus" aria-hidden="true"></i> Add to cart</button>
            </form>
        <?php else: ?>
            <button type="button" class="btn btn-outline-secondary btn-sm w-100" disabled>Out of stock</button>
        <?php endif; ?>
    </div>
</article>
