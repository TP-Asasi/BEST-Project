<?php
    // env.php loads the settings from config/.env when running locally
    // on Render they are set as environment variables instead
    // only reads the file the first time so it is safe to include more than once
    if (!defined('BEST_ENV_LOADED')) {
        define('BEST_ENV_LOADED', true);

        $envFile = __DIR__ . '/.env';
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                // skip blank lines and comments then save each key and value
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                // a real environment variable wins over the file
                if (getenv(trim($key)) === false) {
                    putenv(trim($key) . '=' . trim($value));
                }
            }
        }
    }

    // read a setting with a fallback
    function env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        return $value === false || $value === '' ? $default : $value;
    }
