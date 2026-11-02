<?php
    // profile.php lets a staff member update their contact details and change their password
    // accounts with a temporary password are sent here first and cannot leave until it is changed

    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/helpers/auth.php';

    $conn = db();
    $account = requireStaff($conn);
    $me = (int) $account['accountID'];
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'details') {
            $firstName = postText('firstName', 100);
            $lastName = postText('lastName', 100);
            $phone = postText('phoneNumber', 30);
            if ($firstName === '' || $lastName === '') {
                $errors[] = 'Enter your first and last name.';
            } elseif ($phone !== '' && !isValidPhone($phone)) {
                $errors[] = 'Enter a valid phone number.';
            } else {
                execute($conn, "UPDATE UserAccount SET firstName = ?, lastName = ?, phoneNumber = ? WHERE accountID = ?", [$firstName, $lastName, $phone ?: null, $me]);
                logActivity($conn, 'Profile', 'updated', 'Updated own profile details', $me);
                setFlash('success', 'Your details were saved.');
                header('Location: profile.php');
                exit;
            }
        } elseif ($action === 'password') {
            $current = is_string($_POST['currentPassword'] ?? null) ? $_POST['currentPassword'] : '';
            $new = is_string($_POST['newPassword'] ?? null) ? $_POST['newPassword'] : '';
            $hash = (string) fetchValue($conn, "SELECT passwordHash FROM UserAccount WHERE accountID = ?", [$me]);

            if (!password_verify($current, $hash)) {
                $errors[] = 'Your current password is incorrect.';
            } elseif ($problem = passwordProblems($new)) {
                $errors[] = $problem;
            } elseif ($new !== ($_POST['confirmPassword'] ?? '')) {
                $errors[] = 'The new passwords do not match.';
            } elseif (password_verify($new, $hash)) {
                $errors[] = 'Choose a password you have not used here before.';
            } else {
                execute($conn, "UPDATE UserAccount SET passwordHash = ?, mustChangePassword = FALSE WHERE accountID = ?", [password_hash($new, PASSWORD_DEFAULT), $me]);
                session_regenerate_id(true);
                logActivity($conn, 'Profile', 'password', 'Changed own password', $me);
                setFlash('success', 'Your password was changed.');
                header('Location: ' . ($account['mustChangePassword'] ? 'dashboard.php' : 'profile.php'));
                exit;
            }
        }
    }

    $pageTitle = 'My profile';
    $activePage = '';
    include __DIR__ . '/components/layout.php';
?>

<?php if ($account['mustChangePassword']): ?>
    <div class="portal-alert alert-info"><i class="bi bi-key" aria-hidden="true"></i> <span>You are using a temporary password. Choose your own password to continue.</span></div>
<?php endif; ?>

<div class="panel-grid">
    <section class="panel">
        <h2 class="panel-title">Your details</h2>
        <form method="post" class="needs-validation" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="details">
            <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="firstName">First name</label><input class="form-control" id="firstName" name="firstName" value="<?= e($account['firstName']) ?>" required></div>
                <div class="col-6"><label class="form-label" for="lastName">Last name</label><input class="form-control" id="lastName" name="lastName" value="<?= e($account['lastName']) ?>" required></div>
            </div>
            <div class="mb-2"><label class="form-label" for="emailRO">Email</label><input class="form-control" id="emailRO" value="<?= e($account['email']) ?>" disabled><div class="form-text">Ask an admin to change your email.</div></div>
            <div class="mb-3"><label class="form-label" for="phoneNumber">Phone</label><input class="form-control" id="phoneNumber" name="phoneNumber" type="tel" value="<?= e($account['phoneNumber']) ?>"></div>
            <dl class="profile-facts">
                <div><dt>Role</dt><dd><?= e($account['role']) ?></dd></div>
                <div><dt>Store</dt><dd><?= e($account['storeName'] ?? 'All stores') ?></dd></div>
                <div><dt>Job title</dt><dd><?= e($account['jobTitle'] ?? 'Administrator') ?></dd></div>
            </dl>
            <button class="btn btn-brand" <?= $account['mustChangePassword'] ? 'disabled' : '' ?>>Save details</button>
        </form>
    </section>

    <section class="panel">
        <h2 class="panel-title">Change password</h2>
        <form method="post" class="needs-validation" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="password">
            <div class="mb-2"><label class="form-label" for="currentPassword">Current password</label><input class="form-control" type="password" id="currentPassword" name="currentPassword" autocomplete="current-password" required></div>
            <div class="mb-2"><label class="form-label" for="newPassword">New password</label><input class="form-control" type="password" id="newPassword" name="newPassword" autocomplete="new-password" required minlength="8" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,}"><div class="form-text">At least 8 characters with a letter and a number.</div></div>
            <div class="mb-3"><label class="form-label" for="confirmPassword">Confirm new password</label><input class="form-control" type="password" id="confirmPassword" name="confirmPassword" autocomplete="new-password" required data-match="newPassword"><div class="invalid-feedback">The passwords must match.</div></div>
            <button class="btn btn-brand">Change password</button>
        </form>
    </section>
</div>

<?php include __DIR__ . '/components/layoutEnd.php'; ?>
