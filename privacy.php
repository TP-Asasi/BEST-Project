<?php
    // privacy.php is the POPIA privacy notice
    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/books.php';
    $pageTitle = 'Privacy Notice';
?>
<!DOCTYPE html>
<html lang="en">
    <?php include 'includes/components/header.php'; ?>

    <body>
        <?php include 'includes/components/navBar.php'; ?>

        <main id="main" class="container-xl page-pad narrow-page prose">
            <h1 class="page-title">Privacy notice</h1>
            <p class="text-muted">How Books-Extraordinary-Supply-Town (BEST) handles your personal information under the Protection of Personal Information Act 4 of 2013 (POPIA).</p>

            <h2>What we collect</h2>
            <ul>
                <li><strong>Account details:</strong> your name, email address, phone number and a securely hashed password. We never store your password itself.</li>
                <li><strong>Order details:</strong> what you bought, your delivery address or chosen store, and how you chose to pay.</li>
                <li><strong>Messages:</strong> anything you send through the contact form.</li>
            </ul>
            <p>We do not collect or store card numbers. Card payments are taken in person on delivery or at the store.</p>

            <h2>Why we use it</h2>
            <p>Only to run your account, fulfil and deliver your orders, answer your questions and, if you opted in, tell you about new books. We do not sell or share your information with third parties, except a courier who needs your name, phone number and address to deliver an order.</p>

            <h2>Cookies</h2>
            <p>We use one essential session cookie that keeps you signed in and remembers your cart. It is deleted when you close your browser. We use no tracking or advertising cookies.</p>

            <h2>How we protect it</h2>
            <ul>
                <li>All traffic to this site and to our database is encrypted.</li>
                <li>Only authorised BEST staff can see customer and order details, and every action they take in the staff portal is logged.</li>
                <li>Passwords are hashed with a modern one-way algorithm.</li>
            </ul>

            <h2>Your rights</h2>
            <p>You may view and correct your details at any time in <a href="account.php?tab=profile">your account</a>, opt out of marketing in <a href="account.php?tab=privacy">your privacy settings</a>, or ask us to delete your account. Order records are kept for five years as required by South African tax law.</p>
            <p>If you are unhappy with how we handled your information you may contact the Information Regulator at <a href="https://inforegulator.org.za" rel="noopener" target="_blank">inforegulator.org.za</a>.</p>

            <h2>Contact</h2>
            <p>Our Information Officer can be reached through the <a href="contact.php?subject=Privacy">contact form</a>.</p>
        </main>

        <?php include 'includes/components/footer.php'; ?>
    </body>
</html>
