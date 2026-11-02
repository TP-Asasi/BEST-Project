<?php
    // register.php creates a customer account
    // POPIA: the customer must accept the privacy notice, marketing is a separate opt in that starts unticked

    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/customerAuth.php';

    $return = safeReturnPath($_GET['return'] ?? ($_POST['return'] ?? null), 'account.php');
    $conn = db();

    if (currentCustomer($conn)) {
        header('Location: ' . $return);
        exit;
    }

    $input = ['firstName' => '', 'lastName' => '', 'email' => '', 'phoneNumber' => '', 'marketingConsent' => false];
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = [
            'firstName'        => postText('firstName', 100),
            'lastName'         => postText('lastName', 100),
            'email'            => strtolower(postText('email', 255)),
            'phoneNumber'      => postText('phoneNumber', 30),
            'password'         => is_string($_POST['password'] ?? null) ? $_POST['password'] : '',
            'marketingConsent' => !empty($_POST['marketingConsent']),
        ];

        if (!verifyCsrf()) {
            $errors[] = 'Your session expired. Please try again.';
        }
        if ($input['firstName'] === '' || $input['lastName'] === '') {
            $errors[] = 'Enter your first and last name.';
        }
        if (!isValidEmail($input['email'])) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($input['phoneNumber'] !== '' && !isValidPhone($input['phoneNumber'])) {
            $errors[] = 'Enter a valid phone number or leave it blank.';
        }
        if ($problem = passwordProblems($input['password'])) {
            $errors[] = $problem;
        }
        if ($input['password'] !== ($_POST['confirmPassword'] ?? '')) {
            $errors[] = 'The passwords do not match.';
        }
        if (empty($_POST['privacyConsent'])) {
            $errors[] = 'Please accept the privacy notice to create an account.';
        }
        if (!$conn) {
            $errors[] = 'Registration is unavailable right now. Please try again shortly.';
        }

        if (!$errors) {
            $result = registerCustomer($conn, $input);
            if (is_int($result)) {
                attemptCustomerLogin($conn, $input['email'], $input['password']);
                setFlash('success', 'Welcome to BEST, ' . $input['firstName'] . '! Your account is ready.');
                header('Location: ' . $return);
                exit;
            }
            $errors[] = $result;
        }
    }

    $pageTitle = 'Create Account';
?>
<!DOCTYPE html>
<html lang="en">
    <?php include 'includes/components/header.php'; ?>

    <body>
        <?php include 'includes/components/navBar.php'; ?>

        <main id="main" class="auth-page">
            <div class="auth-card auth-card-wide">
                <h1 class="h3">Create your account</h1>
                <p class="text-muted">It takes a minute and lets you check out, save your address and track orders.</p>

                <?php if ($errors): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <form method="post" class="needs-validation" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="return" value="<?= e($return) ?>">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="firstName" class="form-label">First name</label>
                            <input id="firstName" name="firstName" class="form-control" value="<?= e($input['firstName']) ?>" autocomplete="given-name" required maxlength="100">
                            <div class="invalid-feedback">Enter your first name.</div>
                        </div>
                        <div class="col-sm-6">
                            <label for="lastName" class="form-label">Last name</label>
                            <input id="lastName" name="lastName" class="form-control" value="<?= e($input['lastName']) ?>" autocomplete="family-name" required maxlength="100">
                            <div class="invalid-feedback">Enter your last name.</div>
                        </div>
                        <div class="col-sm-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?= e($input['email']) ?>" autocomplete="email" required>
                            <div class="invalid-feedback">Enter a valid email address.</div>
                        </div>
                        <div class="col-sm-6">
                            <label for="phoneNumber" class="form-label">Phone <span class="text-muted">(optional)</span></label>
                            <input type="tel" id="phoneNumber" name="phoneNumber" class="form-control" value="<?= e($input['phoneNumber']) ?>" autocomplete="tel" pattern="\+?[0-9 ()\-]{9,20}">
                            <div class="invalid-feedback">Enter a valid phone number.</div>
                        </div>
                        <div class="col-sm-6">
                            <label for="password" class="form-label">Password</label>
                            <div class="password-field">
                                <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" required minlength="8" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,}" aria-describedby="passwordHelp">
                                <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                            </div>
                            <div id="passwordHelp" class="form-text">At least 8 characters with a letter and a number.</div>
                        </div>
                        <div class="col-sm-6">
                            <label for="confirmPassword" class="form-label">Confirm password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" autocomplete="new-password" required data-match="password">
                            <div class="invalid-feedback">The passwords must match.</div>
                        </div>
                    </div>

                    <div class="consent-box mt-4">
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="privacyConsent" value="1" required>
                            <span class="form-check-label">I have read the <a href="privacy.php" target="_blank" rel="noopener">privacy notice</a> and agree that BEST may process my details to run my account and orders.</span>
                            <span class="invalid-feedback">You need to accept this to create an account.</span>
                        </label>
                        <label class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="marketingConsent" value="1" <?= $input['marketingConsent'] ? 'checked' : '' ?>>
                            <span class="form-check-label">Email me about new arrivals and specials (optional, you can opt out at any time).</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-brand btn-lg w-100 mt-4">Create account</button>
                </form>
                <p class="mt-4 mb-0">Already have an account? <a href="login.php?return=<?= urlencode($return) ?>">Sign in</a></p>
            </div>
        </main>

        <?php include 'includes/components/footer.php'; ?>
    </body>
</html>
