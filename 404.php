<?php
    // 404.php is shown for any address that does not exist, see the rule in .htaccess
    // book.php also includes it when a book id is unknown
    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/books.php';
    http_response_code(404);
    $pageTitle = 'Page Not Found';

    // the missing address can be in a subfolder so relative links would break
    $baseHref = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
    $activeNav = '';
?>
<!DOCTYPE html>
<html lang="en">
    <?php include __DIR__ . '/includes/components/header.php'; ?>

    <body>
        <?php include __DIR__ . '/includes/components/navBar.php'; ?>

        <main id="main" class="container-xl page-pad">
            <div class="empty-state">
                <i class="bi bi-book-half" aria-hidden="true"></i>
                <p class="not-found-code">404</p>
                <h1 class="h2">This page is missing from the shelf</h1>
                <p>The page or book you are looking for may have been moved or is no longer available.</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="index.php" class="btn btn-brand">Back to home</a>
                    <a href="catalogue.php" class="btn btn-outline-brand">Browse books</a>
                </div>
            </div>
        </main>

        <?php include __DIR__ . '/includes/components/footer.php'; ?>
    </body>
</html>
