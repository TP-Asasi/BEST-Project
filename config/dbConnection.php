<?php
    // dbConnection.php connects to the TiDB Cloud database and gives every page $conn
    // TiDB speaks the MySQL protocol, so the normal mysqli extension is used
    // TiDB Cloud only accepts encrypted connections, so ssl is on unless DB_SSL=false (local MySQL/XAMPP)
    date_default_timezone_set('Africa/Johannesburg');
    mysqli_report(MYSQLI_REPORT_OFF);

    require_once __DIR__ . '/env.php';

    $dbHost     = env('DB_HOST', '127.0.0.1');
    $dbPort     = (int) env('DB_PORT', '4000');
    $dbUser     = env('DB_USER', 'root');
    $dbPassword = env('DB_PASSWORD', '');
    $dbName     = env('DB_NAME', 'best_books');
    $dbUseSsl   = strtolower(env('DB_SSL', 'true')) !== 'false';

    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

    $flags = 0;
    if ($dbUseSsl) {
        // the system certificate store already trusts the authority TiDB Cloud uses,
        // on Windows (XAMPP) point DB_SSL_CA at a downloaded isrgrootx1.pem instead
        $caPath = env('DB_SSL_CA', PHP_OS_FAMILY === 'Windows' ? __DIR__ . '/isrgrootx1.pem' : '/etc/ssl/certs/ca-certificates.crt');
        $conn->ssl_set(null, null, $caPath, null, null);
        $flags = MYSQLI_CLIENT_SSL;
    }

    @$conn->real_connect($dbHost, $dbUser, $dbPassword, $dbName, $dbPort, null, $flags);

    if ($conn->connect_errno) {
        error_log('DB connection failed: ' . $conn->connect_error);
    } else {
        $conn->set_charset('utf8mb4');
        // match the database clock to south african time
        $conn->query("SET time_zone = '+02:00'");
    }

    unset($dbPassword);
