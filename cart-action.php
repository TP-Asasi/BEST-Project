<?php
    // cart-action.php changes the cart: add, update a quantity, remove or empty
    // forms post here and are sent back where they came from,
    // main.js posts with fetch() and gets json back so the page does not reload

    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/cart.php';
    require_once __DIR__ . '/includes/helpers/books.php';

    $wantsJson = ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json';

    function respond(bool $ok, string $message, bool $wantsJson, string $back): never
    {
        if ($wantsJson) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => $ok, 'message' => $message, 'cartCount' => cartCount()]);
            exit;
        }
        setFlash($ok ? 'success' : 'error', $message);
        header('Location: ' . $back);
        exit;
    }

    // send the visitor back to the page they were on, but only within this site
    $back = safeReturnPath(basename(parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?: '') .
        (parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_QUERY) ? '?' . parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY) : ''), 'cart.php');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: cart.php');
        exit;
    }
    if (!verifyCsrf()) {
        respond(false, 'Your session expired. Please try again.', $wantsJson, $back);
    }

    $action = $_POST['action'] ?? '';
    $bookID = intParam($_POST, 'bookID');
    $quantity = intParam($_POST, 'quantity') ?? 1;

    if ($action === 'clear') {
        cartClear();
        respond(true, 'Your cart is now empty.', $wantsJson, 'cart.php');
    }

    $conn = db();
    if (!$conn) {
        respond(false, 'The shop is unavailable right now. Please try again shortly.', $wantsJson, $back);
    }

    $book = $bookID ? getBook($conn, $bookID) : null;
    if (!$book) {
        respond(false, 'That book is no longer available.', $wantsJson, $back);
    }

    $inCart = cartItems()[$bookID] ?? 0;
    $stock = (int) $book['totalStock'];

    switch ($action) {
        case 'add':
            if ($stock <= 0) {
                respond(false, "\"{$book['title']}\" is out of stock at all our stores.", $wantsJson, $back);
            }
            if ($inCart + $quantity > min($stock, CART_MAX_PER_LINE)) {
                cartSet($bookID, min($stock, CART_MAX_PER_LINE));
                respond(true, "You have the most copies of \"{$book['title']}\" we can supply in your cart.", $wantsJson, $back);
            }
            cartAdd($bookID, $quantity);
            respond(true, "\"{$book['title']}\" was added to your cart.", $wantsJson, $back);

        case 'update':
            cartSet($bookID, min($quantity, max($stock, 0)));
            $message = $quantity > $stock && $stock > 0
                ? "Only $stock copies of \"{$book['title']}\" are available, your cart was updated."
                : 'Cart updated.';
            respond(true, $message, $wantsJson, 'cart.php');

        case 'remove':
            cartSet($bookID, 0);
            respond(true, "\"{$book['title']}\" was removed from your cart.", $wantsJson, 'cart.php');
    }

    respond(false, 'Unknown cart action.', $wantsJson, $back);
