<?php
    // messages.php shows contact form messages and POPIA requests for staff to answer
    // replying opens the staff member's email app, then they mark the message replied or closed

    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/helpers/auth.php';

    $conn = db();
    $account = requireStaff($conn);
    requireRole($account, ['Admin', 'Staff']);

    const MESSAGE_STATUSES = ['new' => 'New', 'replied' => 'Replied', 'closed' => 'Closed'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $messageID = intParam($_POST, 'messageID');
        $status = $_POST['status'] ?? '';
        if ($messageID && array_key_exists($status, MESSAGE_STATUSES)) {
            execute($conn, "UPDATE ContactMessage SET status = ?, handledBy = ? WHERE messageID = ?", [$status, $account['accountID'], $messageID]);
            logActivity($conn, 'Message', $status, "Marked message #$messageID as $status", $messageID);
            setFlash('success', 'Message marked ' . $status . '.');
        }
        header('Location: messages.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
        exit;
    }

    $filter = array_key_exists($_GET['status'] ?? '', MESSAGE_STATUSES) ? $_GET['status'] : 'open';
    $messages = fetchAll($conn,
        "SELECT m.*, u.firstName, u.lastName FROM ContactMessage m LEFT JOIN UserAccount u ON u.accountID = m.handledBy
         WHERE " . ($filter === 'open' ? "m.status <> 'closed'" : "m.status = ?") . "
         ORDER BY m.status = 'new' DESC, m.dateCreated DESC LIMIT 200",
        $filter === 'open' ? [] : [$filter]);

    $pageTitle = 'Messages';
    $activePage = 'messages';
    include __DIR__ . '/components/layout.php';
?>

<nav class="status-tabs" aria-label="Filter messages">
    <a href="messages.php" class="<?= $filter === 'open' ? 'active' : '' ?>">Open</a>
    <?php foreach (MESSAGE_STATUSES as $key => $label): ?>
        <a href="messages.php?status=<?= $key ?>" class="<?= $filter === $key ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</nav>

<div class="message-list">
    <?php foreach ($messages as $message): ?>
        <?php $isPopia = str_starts_with($message['subject'], 'POPIA'); ?>
        <article class="panel message-card<?= $message['status'] === 'new' ? ' is-new' : '' ?>">
            <header class="message-head">
                <div>
                    <h2><?= $isPopia ? '<i class="bi bi-shield-exclamation text-danger" aria-hidden="true"></i> ' : '' ?><?= e($message['subject']) ?></h2>
                    <p class="text-muted small mb-0"><?= e($message['name']) ?> · <a href="mailto:<?= e($message['email']) ?>"><?= e($message['email']) ?></a><?= $message['phoneNumber'] ? ' · ' . e($message['phoneNumber']) : '' ?> · <?= date('j M Y, H:i', strtotime($message['dateCreated'])) ?></p>
                </div>
                <span class="pill <?= $message['status'] === 'new' ? 'pill-accent' : ($message['status'] === 'replied' ? 'pill-success' : '') ?>"><?= MESSAGE_STATUSES[$message['status']] ?></span>
            </header>
            <p class="message-body"><?= nl2br(e($message['message'])) ?></p>
            <footer class="message-actions">
                <a class="btn btn-sm btn-brand" href="mailto:<?= e($message['email']) ?>?subject=<?= rawurlencode('Re: ' . $message['subject']) ?>"><i class="bi bi-reply" aria-hidden="true"></i> Reply by email</a>
                <?php foreach (MESSAGE_STATUSES as $key => $label): ?>
                    <?php if ($key !== $message['status']): ?>
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="messageID" value="<?= (int) $message['messageID'] ?>">
                            <input type="hidden" name="status" value="<?= $key ?>">
                            <button class="btn btn-sm btn-outline-secondary">Mark <?= strtolower($label) ?></button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($message['firstName']): ?><span class="small text-muted ms-auto">Handled by <?= e($message['firstName'] . ' ' . $message['lastName']) ?></span><?php endif; ?>
            </footer>
        </article>
    <?php endforeach; ?>
    <?php if (!$messages): ?><section class="panel"><p class="empty-note">No messages here.</p></section><?php endif; ?>
</div>

<?php include __DIR__ . '/components/layoutEnd.php'; ?>
