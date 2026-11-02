<?php
    // staffLogin.php is the login page for the staff and admin portal
    // the role comes from whichever table holds the account (Admin or Staff), customers cannot log in here

    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/database.php';

    $error = '';

    if (isset($_SESSION['accountID'])) {
        header('Location: staff/index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $conn = db();
        $email = strtolower(postText('email', 255));
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        $_SESSION['staffLoginAttempts'] = ($_SESSION['staffLoginAttempts'] ?? 0) + 1;
        if ($_SESSION['staffLoginAttempts'] > 5) {
            sleep(min(5, $_SESSION['staffLoginAttempts'] - 5));
        }

        if (!verifyCsrf()) {
            $error = 'Your session expired. Please try again.';
        } elseif (!$conn) {
            $error = 'Login is unavailable right now. Please try again in a few minutes.';
        } else {
            $account = fetchOne($conn,
                "SELECT u.accountID, u.firstName, u.lastName, u.passwordHash, u.accountStatus,
                        CASE WHEN a.accountID IS NOT NULL THEN 'Admin'
                             WHEN s.accountID IS NOT NULL THEN 'Staff' END AS role
                 FROM UserAccount u
                 LEFT JOIN Admin a ON a.accountID = u.accountID
                 LEFT JOIN Staff s ON s.accountID = u.accountID
                 WHERE u.email = ?",
                [$email]);

            require_once __DIR__ . '/staff/helpers/activityLog.php';

            // the same message for every failure so the form does not reveal which accounts exist
            if ($account && $account['role'] !== null && $account['accountStatus'] === 'active'
                && password_verify($password, $account['passwordHash'])) {
                session_regenerate_id(true);
                unset($_SESSION['staffLoginAttempts']);
                $_SESSION['accountID'] = (int) $account['accountID'];
                $_SESSION['staffName'] = $account['firstName'] . ' ' . $account['lastName'];
                $_SESSION['role'] = $account['role'];

                execute($conn, "UPDATE UserAccount SET lastLogin = NOW() WHERE accountID = ?", [$account['accountID']]);
                logActivity($conn, 'Login', 'login', 'Logged in');

                header('Location: staff/index.php');
                exit;
            }

            $error = 'Incorrect email or password.';
            if ($account && $account['role'] !== null) {
                $reason = $account['accountStatus'] !== 'active' ? 'account is ' . $account['accountStatus'] : 'wrong password';
                logActivity($conn, 'Login', 'failed', "Failed login: $reason", (int) $account['accountID'],
                    ['accountID' => (int) $account['accountID'], 'name' => $account['firstName'] . ' ' . $account['lastName']]);
            }
        }
    }

    $pageTitle = 'Staff Login';
    $hideCookieNotice = true;
?>
<!DOCTYPE html>
<html lang="en">
    <?php include 'includes/components/header.php'; ?>

    <body class="staff-login-body">
        <main id="main" class="auth-page">
            <div class="auth-card">
                <img src="assets/images/logo.svg" alt="" width="56" height="56" class="mb-3">
                <p class="eyebrow mb-1">BEST Staff Portal</p>
                <h1 class="h3">Staff login</h1>
                <p class="text-muted">For authorised BEST employees only. Activity in the portal is recorded.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> <?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" class="needs-validation" novalidate>
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label for="email" class="form-label">Work email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" autocomplete="username" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="password-field">
                            <input type="password" id="password" name="password" class="form-control" autocomplete="current-password" required>
                            <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-brand w-100 btn-lg">Log in</button>
                </form>
                <p class="mt-4 mb-0 small"><a href="index.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to the shop</a></p>
            </div>
        </main>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
        <script src="assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
    </body>
</html>
