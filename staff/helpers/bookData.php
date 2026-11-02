<?php
// bookData.php is the database work for the books and categories pages

require_once __DIR__ . '/../../includes/helpers/database.php';

const BOOK_FORMATS = ['Paperback', 'Hardcover', 'Board book'];

// every book with its category and stock total, for the staff list
function listBooks(mysqli $conn, array $filters): array
{
    $where = ['1 = 1'];
    $params = [];

    if ($filters['q'] !== '') {
        $where[] = '(b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)';
        $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
        array_push($params, $like, $like, $like);
    }
    if ($filters['category']) {
        $where[] = 'b.categoryID = ?';
        $params[] = $filters['category'];
    }
    if ($filters['status'] === 'active') {
        $where[] = 'b.isActive = TRUE';
    } elseif ($filters['status'] === 'hidden') {
        $where[] = 'b.isActive = FALSE';
    }

    return fetchAll($conn,
        "SELECT b.bookID, b.isbn, b.title, b.author, b.price, b.isActive, b.isFeatured, b.coverImageURL, b.dateUpdated,
                c.name AS categoryName,
                COALESCE((SELECT SUM(i.quantity) FROM Inventory i WHERE i.bookID = b.bookID), 0) AS totalStock
         FROM Book b JOIN Category c ON c.categoryID = b.categoryID
         WHERE " . implode(' AND ', $where) . "
         ORDER BY b.title",
        $params);
}

function getBookForEdit(mysqli $conn, int $bookID): ?array
{
    return fetchOne($conn, "SELECT * FROM Book WHERE bookID = ?", [$bookID]);
}

function allCategories(mysqli $conn): array
{
    return fetchAll($conn,
        "SELECT c.*, (SELECT COUNT(*) FROM Book b WHERE b.categoryID = c.categoryID) AS bookCount
         FROM Category c ORDER BY c.sortOrder, c.name");
}

// read the book form
function readBookInput(): array
{
    return [
        'title'         => postText('title', 255),
        'author'        => postText('author', 255),
        'isbn'          => preg_replace('/[^0-9X]/', '', strtoupper(postText('isbn', 20))),
        'categoryID'    => intParam($_POST, 'categoryID'),
        'publisher'     => postText('publisher', 150),
        'publishYear'   => postText('publishYear', 4),
        'pageCount'     => postText('pageCount', 6),
        'language'      => postText('language', 40) ?: 'English',
        'format'        => postText('format', 20),
        'price'         => postText('price', 12),
        'description'   => postText('description', 5000),
        'coverImageURL' => postText('coverImageURL', 500),
        'isFeatured'    => !empty($_POST['isFeatured']),
        'isActive'      => !empty($_POST['isActive']),
    ];
}

// check the book form, returns a list of problems
function validateBook(mysqli $conn, array $input, ?int $bookID): array
{
    $errors = [];

    if ($input['title'] === '' || $input['author'] === '') {
        $errors[] = 'Title and author are required.';
    }
    if (!preg_match('/^(\d{9}[\dX]|\d{13})$/', $input['isbn'])) {
        $errors[] = 'ISBN must be 10 or 13 digits.';
    } elseif (fetchValue($conn, "SELECT bookID FROM Book WHERE isbn = ? AND bookID <> ?", [$input['isbn'], $bookID ?? 0])) {
        $errors[] = 'Another book already uses that ISBN.';
    }
    if (!$input['categoryID'] || !fetchValue($conn, "SELECT categoryID FROM Category WHERE categoryID = ?", [$input['categoryID']])) {
        $errors[] = 'Choose a category.';
    }
    if (!is_numeric($input['price']) || (float) $input['price'] < 0 || (float) $input['price'] > 100000) {
        $errors[] = 'Enter a price between R0 and R100 000.';
    }
    if ($input['publishYear'] !== '' && (!ctype_digit($input['publishYear']) || (int) $input['publishYear'] < 1450 || (int) $input['publishYear'] > (int) date('Y') + 1)) {
        $errors[] = 'Enter a valid publication year.';
    }
    if ($input['pageCount'] !== '' && (!ctype_digit($input['pageCount']) || (int) $input['pageCount'] < 1)) {
        $errors[] = 'Page count must be a positive number.';
    }
    if (!in_array($input['format'], BOOK_FORMATS, true)) {
        $errors[] = 'Choose a format.';
    }
    if (mb_strlen($input['description']) < 20) {
        $errors[] = 'Write a description of at least 20 characters.';
    }
    if ($input['coverImageURL'] !== '' && (!filter_var($input['coverImageURL'], FILTER_VALIDATE_URL) || !str_starts_with($input['coverImageURL'], 'https://'))) {
        $errors[] = 'The cover image must be an https:// address, or leave it blank to use the ISBN cover.';
    }

    return $errors;
}

// add a new book with a zero stock row at every store, returns the new id
function addBook(mysqli $conn, array $input): ?int
{
    $conn->begin_transaction();
    try {
        $ok = execute($conn,
            "INSERT INTO Book (categoryID, isbn, title, author, publisher, publishYear, pageCount, language, format, description, price, coverImageURL, isFeatured, isActive)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            bookParams($input));
        if (!$ok) {
            throw new RuntimeException('insert failed');
        }
        $bookID = (int) $conn->insert_id;
        execute($conn, "INSERT INTO Inventory (bookID, storeID, quantity) SELECT ?, storeID, 0 FROM Store", [$bookID]);
        $conn->commit();
        return $bookID;
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('addBook failed: ' . $error->getMessage());
        return null;
    }
}

function updateBook(mysqli $conn, int $bookID, array $input): bool
{
    $params = bookParams($input);
    $params[] = $bookID;
    return execute($conn,
        "UPDATE Book SET categoryID = ?, isbn = ?, title = ?, author = ?, publisher = ?, publishYear = ?, pageCount = ?, language = ?,
                         format = ?, description = ?, price = ?, coverImageURL = ?, isFeatured = ?, isActive = ?
         WHERE bookID = ?",
        $params) !== false;
}

function bookParams(array $input): array
{
    return [
        $input['categoryID'], $input['isbn'], $input['title'], $input['author'], $input['publisher'] ?: null,
        $input['publishYear'] !== '' ? (int) $input['publishYear'] : null,
        $input['pageCount'] !== '' ? (int) $input['pageCount'] : null,
        $input['language'], $input['format'], $input['description'], round((float) $input['price'], 2),
        $input['coverImageURL'] ?: null, $input['isFeatured'] ? 1 : 0, $input['isActive'] ? 1 : 0,
    ];
}
