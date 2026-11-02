<?php
    // contact.php is the contact form, messages are saved for staff to answer on staff/messages.php

    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/books.php';
    require_once __DIR__ . '/includes/helpers/customerAuth.php';

    $conn = db();
    $customer = currentCustomer($conn);
    $stores = $conn ? getActiveStores($conn) : [];

    $input = [
        'name'        => $customer ? $customer['firstName'] . ' ' . $customer['lastName'] : '',
        'email'       => $customer['email'] ?? '',
        'phoneNumber' => $customer['phoneNumber'] ?? '',
        'subject'     => is_string($_GET['subject'] ?? null) ? mb_substr($_GET['subject'], 0, 150) : '',
        'message'     => '',
    ];
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = [
            'name'        => postText('name', 200),
            'email'       => strtolower(postText('email', 255)),
            'phoneNumber' => postText('phoneNumber', 30),
            'subject'     => postText('subject', 150),
            'message'     => postText('message', 3000),
        ];

        // hidden field that people never fill in but spam bots do
        $isBot = postText('website') !== '';

        if (!verifyCsrf()) {
            $errors[] = 'Your session expired. Please try again.';
        }
        if ($input['name'] === '') {
            $errors[] = 'Enter your name.';
        }
        if (!isValidEmail($input['email'])) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($input['phoneNumber'] !== '' && !isValidPhone($input['phoneNumber'])) {
            $errors[] = 'Enter a valid phone number or leave it blank.';
        }
        if ($input['subject'] === '' || mb_strlen($input['message']) < 10) {
            $errors[] = 'Enter a subject and a message of at least 10 characters.';
        }
        if (!$conn) {
            $errors[] = 'Messages cannot be sent right now. Please phone a store instead.';
        }

        if (!$errors) {
            if (!$isBot) {
                execute($conn, "INSERT INTO ContactMessage (name, email, phoneNumber, subject, message) VALUES (?, ?, ?, ?, ?)",
                    [$input['name'], $input['email'], $input['phoneNumber'] ?: null, $input['subject'], $input['message']]);
            }
            setFlash('success', 'Thanks, ' . $input['name'] . '. Your message was sent and we will reply within one working day.');
            header('Location: contact.php');
            exit;
        }
    }

    $pageTitle = 'Contact Us';
    $activeNav = 'contact';
?>
<!DOCTYPE html>
<html lang="en">
    <?php include 'includes/components/header.php'; ?>

    <body>
        <?php include 'includes/components/navBar.php'; ?>

        <main id="main">
            <section class="page-hero">
                <div class="container-xl">
                    <h1>Contact us</h1>
                    <p>Questions about an order, a special request or a book you can’t find? Send us a message.</p>
                </div>
            </section>

            <section class="container-xl section contact-grid">
                <form method="post" class="checkout-card needs-validation" novalidate>
                    <?php if ($errors): ?>
                        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                    <?= csrfField() ?>
                    <div class="visually-hidden" aria-hidden="true">
                        <label for="website">Leave this empty</label>
                        <input id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" value="<?= e($input['name']) ?>" required autocomplete="name"><div class="invalid-feedback">Enter your name.</div></div>
                        <div class="col-sm-6"><label class="form-label" for="email">Email</label><input type="email" class="form-control" id="email" name="email" value="<?= e($input['email']) ?>" required autocomplete="email"><div class="invalid-feedback">Enter a valid email.</div></div>
                        <div class="col-sm-6"><label class="form-label" for="phoneNumber">Phone <span class="text-muted">(optional)</span></label><input type="tel" class="form-control" id="phoneNumber" name="phoneNumber" value="<?= e($input['phoneNumber']) ?>" pattern="\+?[0-9 ()\-]{9,20}"></div>
                        <div class="col-sm-6"><label class="form-label" for="subject">Subject</label><input class="form-control" id="subject" name="subject" value="<?= e($input['subject']) ?>" required maxlength="150"><div class="invalid-feedback">Enter a subject.</div></div>
                        <div class="col-12"><label class="form-label" for="message">Message</label><textarea class="form-control" id="message" name="message" rows="5" required minlength="10" maxlength="3000"><?= e($input['message']) ?></textarea><div class="invalid-feedback">Write at least 10 characters.</div></div>
                    </div>
                    <p class="small text-muted mt-3">We only use these details to reply to you. See our <a href="privacy.php">privacy notice</a>.</p>
                    <button class="btn btn-brand btn-lg">Send message</button>
                </form>

                <aside>
                    <h2 class="h5">Or call a store</h2>
                    <ul class="contact-stores">
                        <?php foreach ($stores as $store): ?>
                            <li>
                                <strong><?= e($store['name']) ?></strong>
                                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $store['phoneNumber'])) ?>"><?= e($store['phoneNumber']) ?></a>
                                <small class="text-muted"><?= e($store['openingHours']) ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </aside>
            </section>
        </main>

        <?php include 'includes/components/footer.php'; ?>
    </body>
</html>
