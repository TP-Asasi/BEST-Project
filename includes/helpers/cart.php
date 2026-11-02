<?php
// cart.php keeps the shopping cart in the session as bookID => quantity
// prices and stock are always read fresh from the database, the session only holds ids and quantities

require_once __DIR__ . '/database.php';

const CART_MAX_PER_LINE = 10;

function cartItems(): array
{
    return is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
}

// number of copies in the cart, shown on the navbar badge
function cartCount(): int
{
    return array_sum(cartItems());
}

// add copies of a book, capped per line
function cartAdd(int $bookID, int $quantity = 1): void
{
    $cart = cartItems();
    $cart[$bookID] = min(CART_MAX_PER_LINE, ($cart[$bookID] ?? 0) + max(1, $quantity));
    $_SESSION['cart'] = $cart;
}

// set a line to an exact quantity, zero removes it
function cartSet(int $bookID, int $quantity): void
{
    $cart = cartItems();
    if ($quantity <= 0) {
        unset($cart[$bookID]);
    } else {
        $cart[$bookID] = min(CART_MAX_PER_LINE, $quantity);
    }
    $_SESSION['cart'] = $cart;
}

function cartClear(): void
{
    $_SESSION['cart'] = [];
}

// the cart with each book's current price and stock, books that were hidden are dropped
function cartDetails(mysqli $conn): array
{
    $cart = cartItems();
    $lines = [];
    $subtotal = 0.0;

    if ($cart) {
        $ids = array_map('intval', array_keys($cart));
        $books = fetchAll($conn,
            "SELECT b.bookID, b.isbn, b.title, b.author, b.price, b.coverImageURL, b.format,
                    COALESCE(st.totalStock, 0) AS totalStock
             FROM Book b JOIN Category c ON c.categoryID = b.categoryID " . STOCK_JOIN . "
             WHERE b.isActive = TRUE AND c.isActive = TRUE AND b.bookID IN (" . placeholders($ids) . ")",
            $ids);

        $found = [];
        foreach ($books as $book) {
            $quantity = (int) $cart[$book['bookID']];
            $lineTotal = $quantity * (float) $book['price'];
            $subtotal += $lineTotal;
            $found[$book['bookID']] = true;
            $lines[] = $book + ['quantity' => $quantity, 'lineTotal' => $lineTotal];
        }

        // forget books that are no longer for sale
        foreach ($ids as $id) {
            if (!isset($found[$id])) {
                cartSet($id, 0);
            }
        }
    }

    return ['lines' => $lines, 'subtotal' => $subtotal, 'count' => array_sum(array_column($lines, 'quantity'))];
}
