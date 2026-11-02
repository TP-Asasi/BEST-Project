<?php
    // login.php signs customers in, then sends them back to where they were going

    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/customerAuth.php';

    $return = safeReturnPath($_GET['return'] ?? ($_POST['return'] ?? null), 'account.php');
    $conn = db();

    if (currentCustomer($conn)) {
        header('Location: ' . $return);
        exit;
    }

    $error = '';
    $email = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = strtolower(postText('email', 255));
        $password = $_POST['password'] ?? '';

        // slow down repeated guesses from the same session
        $_SESSION['loginAttempts'] = ($_SESSION['loginAttempts'] ?? 0) + 1;
        if ($_SESSION['loginAttempts'] > 5) {
            sleep(min(5, $_SESSION['loginAttempts'] - 5));
        }

        if (!verifyCsrf()) {
            $error = 'Your session expired. Please try again.';
        } elseif (!$conn) {
            $error = 'Sign in is unavailable right now. Please try again in a few minutes.';
        } elseif (attemptCustomerLogin($conn, $email, is_string($password) ? $password : '')) {
            unset($_SESSION['loginAttempts']);
            setFlash('success', 'Welcome back!');
            header('Location: ' . $return);
            exit;
        } else {
            $error = 'Incorrect email or password.';
        }
    }

    $pageTitle = 'Sign In';
?>
<!DOCTYPE html>
<html lang="en">
    <?php include 'includes/components/header.php'; ?>

    <body>
        <?php include 'includes/components/navBar.php'; ?>

        <main id="main" class="auth-page">
            <div class="auth-card">
                <img src="assets/images/logo.svg" alt="" width="56" height="56" class="mb-3">
                <h1 class="h3">Sign in</h1>
                <p class="text-muted">Sign in to check out and follow your orders.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> <?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" class="needs-validation" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="return" value="<?= e($return) ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" autocomplete="email" required autofocus>
                        <div class="invalid-feedback">Enter your email address.</div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="password-field">
                            <input type="password" id="password" name="password" class="form-control" autocomplete="current-password" required>
                            <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                        </div>
                        <div class="invalid-feedback">Enter your password.</div>
                    </div>
                    <button type="submit" class="btn btn-brand w-100 btn-lg">Sign in</button>
                </form>

                <p class="mt-4 mb-0">New to BEST? <a href="register.php?return=<?= urlencode($return) ?>">Create an account</a></p>
            </div>
        </main>

        <?php include 'includes/components/footer.php'; ?>
    </body>
</html>
