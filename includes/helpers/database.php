<?php
// database.php connects to the database the first time a page needs it and reuses that connection
// returns null when the database cannot be reached so pages can show a friendly message

function db(): ?mysqli
{
    static $conn = null;

    if ($conn === null) {
        require __DIR__ . '/../../config/dbConnection.php';
    }

    return $conn instanceof mysqli && !$conn->connect_errno ? $conn : null;
}

// run a prepared select and return every row, an empty list on failure
// every value is sent as a separate parameter so user input never becomes part of the sql
function fetchAll(mysqli $conn, string $sql, array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Query failed to prepare: ' . $conn->error . ' | ' . $sql);
        return [];
    }
    if (!$stmt->execute($params ?: null)) {
        error_log('Query failed: ' . $stmt->error);
        return [];
    }
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// first row or null
function fetchOne(mysqli $conn, string $sql, array $params = []): ?array
{
    return fetchAll($conn, $sql, $params)[0] ?? null;
}

// single value from the first column of the first row
function fetchValue(mysqli $conn, string $sql, array $params = []): mixed
{
    $row = fetchOne($conn, $sql, $params);
    return $row ? array_values($row)[0] : null;
}

// insert, update or delete. Returns the number of rows changed, or false on failure
function execute(mysqli $conn, string $sql, array $params = []): int|false
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Statement failed to prepare: ' . $conn->error . ' | ' . $sql);
        return false;
    }
    if (!$stmt->execute($params ?: null)) {
        error_log('Statement failed: ' . $stmt->error);
        return false;
    }
    return $stmt->affected_rows;
}

// build "?, ?, ?" for an IN (...) list
function placeholders(array $values): string
{
    return implode(', ', array_fill(0, count($values), '?'));
}
