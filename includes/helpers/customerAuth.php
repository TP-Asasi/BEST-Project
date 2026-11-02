<?php
// customerAuth.php handles customer sign in on the shop
// customers and staff share the UserAccount table, a customer is any account in the Customer table
// staff log in separately through staffLogin.php and use different session keys

require_once __DIR__ . '/database.php';

// the logged in customer, reloaded each request so a suspended account is signed out straight away
function currentCustomer(?mysqli $conn): ?array
{
    static $customer = false;

    if ($customer !== false) {
        return $customer;
    }
    if (!isset($_SESSION['customerID']) || !$conn) {
        return $customer = null;
    }

    $customer = fetchOne($conn,
        "SELECT u.accountID, u.firstName, u.lastName, u.email, u.phoneNumber, u.accountStatus,
                c.addressLine, c.suburb, c.city, c.province, c.postalCode, c.marketingConsent, c.privacyConsentAt
         FROM UserAccount u JOIN Customer c ON c.accountID = u.accountID
         WHERE u.accountID = ?",
        [(int) $_SESSION['customerID']]);

    if (!$customer || $customer['accountStatus'] !== 'active') {
        unset($_SESSION['customerID']);
        $customer = null;
    }

    return $customer;
}

// send visitors who are not signed in to the login page, then back here afterwards
function requireCustomer(?mysqli $conn, string $returnTo): array
{
    $customer = currentCustomer($conn);
    if (!$customer) {
        setFlash('info', 'Please sign in to continue.');
        header('Location: login.php?return=' . rawurlencode($returnTo));
        exit;
    }
    return $customer;
}

// check an email and password belong to an active customer
// the same message is used for every failure so the form does not reveal which emails are registered
function attemptCustomerLogin(mysqli $conn, string $email, string $password): ?array
{
    $account = fetchOne($conn,
        "SELECT u.accountID, u.passwordHash, u.accountStatus
         FROM UserAccount u JOIN Customer c ON c.accountID = u.accountID
         WHERE u.email = ?",
        [$email]);

    if (!$account || $account['accountStatus'] !== 'active' || !password_verify($password, $account['passwordHash'])) {
        // spend about the same time when the email is unknown
        if (!$account) {
            password_verify($password, '$2y$10$qKvndm53L0IwnnvzF.nRce4C0sFKwFQisS7vfJMOxicaATAznhAT.');
        }
        return null;
    }

    session_regenerate_id(true);
    $_SESSION['customerID'] = (int) $account['accountID'];
    execute($conn, "UPDATE UserAccount SET lastLogin = NOW() WHERE accountID = ?", [$account['accountID']]);

    // upgrade old hashes when php raises its default cost
    if (password_needs_rehash($account['passwordHash'], PASSWORD_DEFAULT)) {
        execute($conn, "UPDATE UserAccount SET passwordHash = ? WHERE accountID = ?",
            [password_hash($password, PASSWORD_DEFAULT), $account['accountID']]);
    }

    return $account;
}

// create a customer account, returns the new id or an error message
function registerCustomer(mysqli $conn, array $input): int|string
{
    if (fetchValue($conn, "SELECT accountID FROM UserAccount WHERE email = ?", [$input['email']])) {
        return 'An account with that email already exists. Try signing in instead.';
    }

    $conn->begin_transaction();
    try {
        $ok = execute($conn,
            "INSERT INTO UserAccount (firstName, lastName, email, phoneNumber, passwordHash) VALUES (?, ?, ?, ?, ?)",
            [$input['firstName'], $input['lastName'], $input['email'], $input['phoneNumber'] ?: null,
             password_hash($input['password'], PASSWORD_DEFAULT)]);
        $accountID = (int) $conn->insert_id;

        $ok = $ok && execute($conn,
            "INSERT INTO Customer (accountID, privacyConsentAt, marketingConsent) VALUES (?, NOW(), ?)",
            [$accountID, $input['marketingConsent'] ? 1 : 0]);

        if (!$ok) {
            throw new RuntimeException('insert failed');
        }
        $conn->commit();
        return $accountID;
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('registerCustomer failed: ' . $error->getMessage());
        return 'We could not create your account right now. Please try again.';
    }
}
