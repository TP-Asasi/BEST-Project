<!-- header.php is the shared head for the shop pages
 contains the page title, fonts, bootstrap and the site stylesheet
-->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php global $pageTitle, $pageDescription, $baseHref; ?>
    <?php if (!empty($baseHref)): ?>
        <!-- only set by pages served from other addresses like 404.php so links still point at the site root -->
        <base href="<?= e($baseHref) ?>">
    <?php endif; ?>

    <title><?= isset($pageTitle) ? e($pageTitle) . ' | BEST Books' : 'BEST Books — Books-Extraordinary-Supply-Town' ?></title>
    <meta name="description" content="<?= e($pageDescription ?? 'Shop books online from Books-Extraordinary-Supply-Town. Check stock at every BEST store, order for delivery or click and collect.') ?>">
    <meta name="theme-color" content="#1E4D3A">
    <link rel="icon" href="assets/images/logo.svg" type="image/svg+xml">

    <!-- fonts: Fraunces for headings, Public Sans for body text -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- bootstrap and bootstrap icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- site stylesheet, the version number changes with every edit so browsers fetch the new css -->
    <link href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/style.css') ?>" rel="stylesheet">
</head>
