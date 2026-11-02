<?php
// books.php reads the catalogue for the shop pages
// stock is always worked out from the Inventory table across the active stores

require_once __DIR__ . '/database.php';

const BOOKS_PER_PAGE = 12;

// sort options shown on the catalogue, key => [label, order by]
const BOOK_SORTS = [
    'featured'   => ['Featured',            'b.isFeatured DESC, b.dateAdded DESC'],
    'newest'     => ['Newest',              'b.dateAdded DESC, b.bookID DESC'],
    'price-asc'  => ['Price: low to high',  'b.price ASC'],
    'price-desc' => ['Price: high to low',  'b.price DESC'],
    'title'      => ['Title A–Z',           'b.title ASC'],
];

// the stock total per book across every active store, joined into the catalogue queries
const STOCK_JOIN = "LEFT JOIN (
        SELECT i.bookID, SUM(i.quantity) AS totalStock, SUM(i.quantity > 0) AS storesWithStock
        FROM Inventory i JOIN Store s ON s.storeID = i.storeID AND s.isActive = TRUE
        GROUP BY i.bookID
    ) st ON st.bookID = b.bookID";

// the cover to show, a staff supplied address or the Open Library cover for the isbn
// ?default=false makes Open Library return 404 for unknown covers so the page can draw a placeholder
function coverSrc(array $book, string $size = 'M'): string
{
    if (!empty($book['coverImageURL'])) {
        return $book['coverImageURL'];
    }
    return 'https://covers.openlibrary.org/b/isbn/' . rawurlencode($book['isbn'] ?? '') . '-' . $size . '.jpg?default=false';
}

// in stock, low stock or out of stock for a quantity
function stockStatus(int $quantity, int $lowLevel = 3): array
{
    if ($quantity <= 0) {
        return ['out', 'Out of stock'];
    }
    if ($quantity <= $lowLevel) {
        return ['low', 'Only ' . $quantity . ' left'];
    }
    return ['in', 'In stock'];
}

// active categories with how many visible books each has
function getCategories(mysqli $conn): array
{
    return fetchAll($conn,
        "SELECT c.categoryID, c.name, c.description, c.icon,
                COUNT(b.bookID) AS bookCount
         FROM Category c
         LEFT JOIN Book b ON b.categoryID = c.categoryID AND b.isActive = TRUE
         WHERE c.isActive = TRUE
         GROUP BY c.categoryID, c.name, c.description, c.icon, c.sortOrder
         ORDER BY c.sortOrder, c.name");
}

// read and clean the catalogue filters from the url, anything invalid is ignored
function readCatalogueFilters(array $query): array
{
    $price = fn ($value) => is_string($value) && is_numeric($value) && $value >= 0 ? (float) $value : null;

    return [
        'q'        => is_string($query['q'] ?? null) ? mb_substr(trim($query['q']), 0, 100) : '',
        'category' => intParam($query, 'category'),
        'minPrice' => $price($query['minPrice'] ?? null),
        'maxPrice' => $price($query['maxPrice'] ?? null),
        'store'    => intParam($query, 'store'),
        'inStock'  => !empty($query['inStock']),
        'sort'     => array_key_exists($query['sort'] ?? '', BOOK_SORTS) ? $query['sort'] : 'featured',
        'page'     => max(1, intParam($query, 'page') ?? 1),
    ];
}

// search the catalogue, returns the page of books and the total number that matched
function searchBooks(mysqli $conn, array $filters, int $perPage = BOOKS_PER_PAGE): array
{
    $where = ['b.isActive = TRUE', 'c.isActive = TRUE'];
    $params = [];

    // one box searches title, author and isbn
    if ($filters['q'] !== '') {
        $where[] = '(b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)';
        $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
        array_push($params, $like, $like, $like);
    }
    if ($filters['category']) {
        $where[] = 'b.categoryID = ?';
        $params[] = $filters['category'];
    }
    if ($filters['minPrice'] !== null) {
        $where[] = 'b.price >= ?';
        $params[] = $filters['minPrice'];
    }
    if ($filters['maxPrice'] !== null) {
        $where[] = 'b.price <= ?';
        $params[] = $filters['maxPrice'];
    }
    // available at one particular store
    if ($filters['store']) {
        $where[] = 'EXISTS (SELECT 1 FROM Inventory si WHERE si.bookID = b.bookID AND si.storeID = ? AND si.quantity > 0)';
        $params[] = $filters['store'];
    }
    if ($filters['inStock']) {
        $where[] = 'COALESCE(st.totalStock, 0) > 0';
    }

    $from = "FROM Book b JOIN Category c ON c.categoryID = b.categoryID " . STOCK_JOIN . " WHERE " . implode(' AND ', $where);

    $total = (int) fetchValue($conn, "SELECT COUNT(*) $from", $params);

    $orderBy = BOOK_SORTS[$filters['sort']][1];
    $offset = ($filters['page'] - 1) * $perPage;

    $books = fetchAll($conn,
        "SELECT b.bookID, b.isbn, b.title, b.author, b.price, b.coverImageURL, b.isFeatured, b.format,
                c.name AS categoryName, COALESCE(st.totalStock, 0) AS totalStock
         $from
         ORDER BY $orderBy, b.bookID
         LIMIT " . (int) $perPage . " OFFSET " . (int) $offset,
        $params);

    return [$books, $total];
}

// books for a home page row
function getBookRow(mysqli $conn, string $type, int $limit = 8): array
{
    $orderBy = match ($type) {
        'featured' => 'b.isFeatured DESC, b.dateAdded DESC',
        default    => 'b.dateAdded DESC, b.bookID DESC',
    };
    $extra = $type === 'featured' ? 'AND b.isFeatured = TRUE' : '';

    return fetchAll($conn,
        "SELECT b.bookID, b.isbn, b.title, b.author, b.price, b.coverImageURL, b.format,
                c.name AS categoryName, COALESCE(st.totalStock, 0) AS totalStock
         FROM Book b JOIN Category c ON c.categoryID = b.categoryID " . STOCK_JOIN . "
         WHERE b.isActive = TRUE AND c.isActive = TRUE $extra
         ORDER BY $orderBy
         LIMIT " . (int) $limit);
}

// one visible book with its category
function getBook(mysqli $conn, int $bookID): ?array
{
    return fetchOne($conn,
        "SELECT b.*, c.name AS categoryName, COALESCE(st.totalStock, 0) AS totalStock
         FROM Book b JOIN Category c ON c.categoryID = b.categoryID " . STOCK_JOIN . "
         WHERE b.bookID = ? AND b.isActive = TRUE AND c.isActive = TRUE",
        [$bookID]);
}

// how many copies each active store holds of a book
function getBookAvailability(mysqli $conn, int $bookID): array
{
    return fetchAll($conn,
        "SELECT s.storeID, s.name, s.city, s.province, COALESCE(i.quantity, 0) AS quantity,
                COALESCE(i.reorderLevel, 3) AS reorderLevel
         FROM Store s
         LEFT JOIN Inventory i ON i.storeID = s.storeID AND i.bookID = ?
         WHERE s.isActive = TRUE
         ORDER BY s.name",
        [$bookID]);
}

// other books in the same category
function getRelatedBooks(mysqli $conn, array $book, int $limit = 4): array
{
    return fetchAll($conn,
        "SELECT b.bookID, b.isbn, b.title, b.author, b.price, b.coverImageURL, b.format,
                c.name AS categoryName, COALESCE(st.totalStock, 0) AS totalStock
         FROM Book b JOIN Category c ON c.categoryID = b.categoryID " . STOCK_JOIN . "
         WHERE b.isActive = TRUE AND b.categoryID = ? AND b.bookID <> ?
         ORDER BY COALESCE(st.totalStock, 0) > 0 DESC, b.isFeatured DESC, b.title
         LIMIT " . (int) $limit,
        [$book['categoryID'], $book['bookID']]);
}

// active stores for the stores page, checkout and filters
function getActiveStores(mysqli $conn): array
{
    return fetchAll($conn, "SELECT * FROM Store WHERE isActive = TRUE ORDER BY name");
}
