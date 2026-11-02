<?php
    // statCard.php is a stat card on the dashboard: icon, title, value and an optional note and link
    // the page sets $cardTitle, $cardValue, $cardMeta, $cardIcon, $cardLink and $cardClass before including it
    $cardTag = !empty($cardLink) ? 'a' : 'article';
?>
<<?= $cardTag ?> class="stat-card <?= e($cardClass ?? '') ?>"<?= !empty($cardLink) ? ' href="' . e($cardLink) . '"' : '' ?>>
    <i class="bi bi-<?= e($cardIcon ?? 'graph-up') ?> stat-card-icon" aria-hidden="true"></i>
    <h2><?= e($cardTitle ?? '') ?></h2>
    <p class="stat-card-value"><?= e((string) ($cardValue ?? '')) ?></p>
    <?php if (!empty($cardMeta)): ?>
        <p class="stat-card-meta"><?= e($cardMeta) ?></p>
    <?php endif; ?>
</<?= $cardTag ?>>
<?php $cardLink = $cardMeta = $cardClass = null; ?>
