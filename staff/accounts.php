<?php
    // accounts.php lets admins manage staff access: create accounts, set the role and store,
    // suspend or restore, and reset passwords
    // new and reset accounts get a temporary password that must be changed at the next login
    // an admin cannot suspend or demote themselves, so there is always at least one admin

    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/helpers/auth.php';

    $conn = db();
    $account = requireStaff($conn);
    requireRole($account, 'Admin');
    $errors = [];
    $me = (int) $account['accountID'];

    // readable temporary password, no look-alike characters
    function temporaryPassword(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $password = '';
        for ($i = 0; $i < 10; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password . random_int(2, 9);
    }

    // put an account in exactly one of the Admin or Staff tables
    function setRole(mysqli $conn, int $accountID, string $role, ?int $storeID, string $jobTitle): void
    {
        if ($role === 'Admin') {
            execute($conn, "DELETE FROM Staff WHERE accountID = ?", [$accountID]);
            execute($conn, "INSERT IGNORE INTO Admin (accountID) VALUES (?)", [$accountID]);
        } else {
            execute($conn, "DELETE FROM Admin WHERE accountID = ?", [$accountID]);
            execute($conn, "INSERT INTO Staff (accountID, storeID, jobTitle) VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE storeID = VALUES(storeID), jobTitle = VALUES(jobTitle)",
                [$accountID, $storeID, $jobTitle]);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $targetID = intParam($_POST, 'accountID');
        $target = $targetID ? fetchOne($conn,
            "SELECT u.accountID, u.firstName, u.lastName, u.email, u.accountStatus,
                    CASE WHEN a.accountID IS NOT NULL THEN 'Admin' WHEN s.accountID IS NOT NULL THEN 'Staff' END AS role
             FROM UserAccount u LEFT JOIN Admin a ON a.accountID = u.accountID LEFT JOIN Staff s ON s.accountID = u.accountID
             WHERE u.accountID = ?", [$targetID]) : null;
        if ($target && $target['role'] === null) {
            $target = null; // customers are managed on customers.php
        }

        $role = ($_POST['role'] ?? '') === 'Admin' ? 'Admin' : 'Staff';
        $storeID = intParam($_POST, 'storeID');
        $jobTitle = postText('jobTitle', 100) ?: ($role === 'Admin' ? 'Administrator' : 'Store Assistant');
        if ($storeID && !fetchValue($conn, "SELECT storeID FROM Store WHERE storeID = ?", [$storeID])) {
            $storeID = null;
        }

        if ($action === 'create') {
            $new = [
                'firstName'   => postText('firstName', 100),
                'lastName'    => postText('lastName', 100),
                'email'       => strtolower(postText('email', 255)),
                'phoneNumber' => postText('phoneNumber', 30),
            ];
            if ($new['firstName'] === '' || $new['lastName'] === '') {
                $errors[] = 'Enter a first and last name.';
            }
            if (!isValidEmail($new['email'])) {
                $errors[] = 'Enter a valid email address.';
            } elseif (fetchValue($conn, "SELECT accountID FROM UserAccount WHERE email = ?", [$new['email']])) {
                $errors[] = 'An account already uses that email.';
            }
            if ($new['phoneNumber'] !== '' && !isValidPhone($new['phoneNumber'])) {
                $errors[] = 'Enter a valid phone number.';
            }
            if ($role === 'Staff' && !$storeID) {
                $errors[] = 'Choose the store this staff member works at.';
            }

            if (!$errors) {
                $password = temporaryPassword();
                $conn->begin_transaction();
                execute($conn, "INSERT INTO UserAccount (firstName, lastName, email, phoneNumber, passwordHash, mustChangePassword, createdBy) VALUES (?, ?, ?, ?, ?, TRUE, ?)",
                    [$new['firstName'], $new['lastName'], $new['email'], $new['phoneNumber'] ?: null, password_hash($password, PASSWORD_DEFAULT), $me]);
                $newID = (int) $conn->insert_id;
                setRole($conn, $newID, $role, $storeID, $jobTitle);
                $conn->commit();

                logActivity($conn, 'Account', 'created', "Created $role account for {$new['firstName']} {$new['lastName']}", $newID);
                setFlash('success', "Account created for {$new['firstName']}. Temporary password: $password — share it privately, it must be changed at first login.");
                header('Location: accounts.php');
                exit;
            }
        } elseif ($target && $action === 'update') {
            if ($targetID === $me && $role !== 'Admin') {
                setFlash('error', 'You cannot remove your own admin role.');
            } elseif ($role === 'Staff' && !$storeID) {
                setFlash('error', 'Staff members need a store.');
            } else {
                setRole($conn, $targetID, $role, $storeID, $jobTitle);
                logActivity($conn, 'Account', 'role', "Set {$target['firstName']} {$target['lastName']} to $role", $targetID);
                setFlash('success', "{$target['firstName']} {$target['lastName']} updated.");
            }
            header('Location: accounts.php');
            exit;
        } elseif ($target && $action === 'toggleStatus') {
            if ($targetID === $me) {
                setFlash('error', 'You cannot suspend your own account.');
            } else {
                $newStatus = $target['accountStatus'] === 'active' ? 'suspended' : 'active';
                execute($conn, "UPDATE UserAccount SET accountStatus = ? WHERE accountID = ?", [$newStatus, $targetID]);
                logActivity($conn, 'Account', $newStatus, ucfirst($newStatus === 'active' ? 'restored' : 'suspended') . " {$target['firstName']} {$target['lastName']}", $targetID);
                setFlash('success', "{$target['firstName']} {$target['lastName']} is now $newStatus.");
            }
            header('Location: accounts.php');
            exit;
        } elseif ($target && $action === 'resetPassword') {
            $password = temporaryPassword();
            execute($conn, "UPDATE UserAccount SET passwordHash = ?, mustChangePassword = TRUE WHERE accountID = ?", [password_hash($password, PASSWORD_DEFAULT), $targetID]);
            logActivity($conn, 'Account', 'password reset', "Reset the password for {$target['firstName']} {$target['lastName']}", $targetID);
            setFlash('success', "New temporary password for {$target['firstName']}: $password");
            header('Location: accounts.php');
            exit;
        }
    }

    $accounts = fetchAll($conn,
        "SELECT u.accountID, u.firstName, u.lastName, u.email, u.phoneNumber, u.accountStatus, u.lastLogin, u.mustChangePassword,
                CASE WHEN a.accountID IS NOT NULL THEN 'Admin' ELSE 'Staff' END AS role,
                s.storeID, s.jobTitle, st.name AS storeName
         FROM UserAccount u
         LEFT JOIN Admin a ON a.accountID = u.accountID
         LEFT JOIN Staff s ON s.accountID = u.accountID
         LEFT JOIN Store st ON st.storeID = s.storeID
         WHERE a.accountID IS NOT NULL OR s.accountID IS NOT NULL
         ORDER BY role, u.firstName");
    $stores = fetchAll($conn, "SELECT storeID, name FROM Store WHERE isActive = TRUE ORDER BY name");

    $pageTitle = 'Staff & Roles';
    $activePage = 'accounts';
    include __DIR__ . '/components/layout.php';
?>

<div class="role-legend">
    <div><span class="pill pill-accent">Admin</span> Everything, plus stores, staff accounts and the activity log.</div>
    <div><span class="pill pill-success">Staff</span> Orders, books, categories, customers and messages. Stock at their own store only.</div>
</div>

<div class="panel-grid panel-grid-wide">
    <section class="panel">
        <div class="table-wrap">
            <table class="portal-table responsive-table">
                <thead><tr><th>Person</th><th>Role &amp; store</th><th>Last login</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($accounts as $row): ?>
                        <?php $rowForm = 'acc' . (int) $row['accountID']; ?>
                        <tr class="<?= $row['accountStatus'] === 'active' ? '' : 'is-muted' ?>">
                            <td data-label="Person">
                                <strong><?= e($row['firstName'] . ' ' . $row['lastName']) ?></strong><?= (int) $row['accountID'] === $me ? ' <span class="pill">You</span>' : '' ?>
                                <small class="d-block"><?= e($row['email']) ?></small>
                                <?php if ($row['mustChangePassword']): ?><small class="d-block text-warning-emphasis">Must change password</small><?php endif; ?>
                            </td>
                            <td data-label="Role & store">
                                <form method="post" id="<?= $rowForm ?>" class="role-form">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="accountID" value="<?= (int) $row['accountID'] ?>">
                                    <select name="role" class="form-select form-select-sm" aria-label="Role" data-role-select <?= (int) $row['accountID'] === $me ? 'disabled' : '' ?>>
                                        <option <?= $row['role'] === 'Staff' ? 'selected' : '' ?>>Staff</option>
                                        <option <?= $row['role'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                    <?php if ((int) $row['accountID'] === $me): ?><input type="hidden" name="role" value="Admin"><?php endif; ?>
                                    <select name="storeID" class="form-select form-select-sm" aria-label="Store" data-store-select>
                                        <option value="">No store</option>
                                        <?php foreach ($stores as $store): ?>
                                            <option value="<?= (int) $store['storeID'] ?>" <?= (int) $row['storeID'] === (int) $store['storeID'] ? 'selected' : '' ?>><?= e($store['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input name="jobTitle" class="form-control form-control-sm" value="<?= e($row['jobTitle'] ?? 'Administrator') ?>" aria-label="Job title">
                                    <button class="btn btn-sm btn-outline-brand">Save</button>
                                </form>
                            </td>
                            <td data-label="Last login"><?= $row['lastLogin'] ? date('j M Y H:i', strtotime($row['lastLogin'])) : 'Never' ?></td>
                            <td data-label="Status"><span class="pill <?= $row['accountStatus'] === 'active' ? 'pill-success' : 'pill-danger' ?>"><?= ucfirst(e($row['accountStatus'])) ?></span></td>
                            <td data-label="Actions" class="text-end">
                                <?php if ((int) $row['accountID'] !== $me): ?>
                                    <div class="row-actions">
                                        <form method="post" data-confirm="Create a new temporary password for <?= e($row['firstName']) ?>?">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="resetPassword">
                                            <input type="hidden" name="accountID" value="<?= (int) $row['accountID'] ?>">
                                            <button class="icon-btn" title="Reset password" aria-label="Reset password for <?= e($row['firstName']) ?>"><i class="bi bi-key" aria-hidden="true"></i></button>
                                        </form>
                                        <form method="post" data-confirm="<?= $row['accountStatus'] === 'active' ? 'Suspend this account? They will be logged out straight away.' : 'Restore this account?' ?>">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="toggleStatus">
                                            <input type="hidden" name="accountID" value="<?= (int) $row['accountID'] ?>">
                                            <button class="icon-btn" title="<?= $row['accountStatus'] === 'active' ? 'Suspend' : 'Restore' ?>" aria-label="<?= $row['accountStatus'] === 'active' ? 'Suspend' : 'Restore' ?> <?= e($row['firstName']) ?>"><i class="bi bi-<?= $row['accountStatus'] === 'active' ? 'person-slash' : 'person-check' ?>" aria-hidden="true"></i></button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Add a staff member</h2>
        <form method="post" class="needs-validation" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label" for="firstName">First name</label><input class="form-control" id="firstName" name="firstName" value="<?= e($_POST['firstName'] ?? '') ?>" required></div>
                <div class="col-6"><label class="form-label" for="lastName">Last name</label><input class="form-control" id="lastName" name="lastName" value="<?= e($_POST['lastName'] ?? '') ?>" required></div>
            </div>
            <div class="mb-2"><label class="form-label" for="email">Work email</label><input class="form-control" id="email" name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" required></div>
            <div class="mb-2"><label class="form-label" for="phoneNumber">Phone <span class="text-muted">(optional)</span></label><input class="form-control" id="phoneNumber" name="phoneNumber" type="tel" value="<?= e($_POST['phoneNumber'] ?? '') ?>"></div>
            <div class="row g-2 mb-2">
                <div class="col-5"><label class="form-label" for="role">Role</label>
                    <select class="form-select" id="role" name="role" data-role-select><option>Staff</option><option <?= ($_POST['role'] ?? '') === 'Admin' ? 'selected' : '' ?>>Admin</option></select></div>
                <div class="col-7"><label class="form-label" for="storeID">Store</label>
                    <select class="form-select" id="storeID" name="storeID" data-store-select>
                        <option value="">Choose…</option>
                        <?php foreach ($stores as $store): ?><option value="<?= (int) $store['storeID'] ?>"><?= e($store['name']) ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <div class="mb-3"><label class="form-label" for="jobTitle">Job title</label><input class="form-control" id="jobTitle" name="jobTitle" placeholder="Store Assistant"></div>
            <p class="small text-muted">A temporary password is created and shown once. They must change it when they first log in.</p>
            <button class="btn btn-brand w-100">Create account</button>
        </form>
    </section>
</div>

<?php include __DIR__ . '/components/layoutEnd.php'; ?>
