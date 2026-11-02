<?php
// forms.php holds the helpers every form uses on both the shop and the staff portal
// csrf tokens, flash messages, input cleaning and money formatting

// one csrf token per session shared by every form
function csrfToken(): string
{
    $_SESSION['csrfToken'] ??= bin2hex(random_bytes(32));
    return $_SESSION['csrfToken'];
}

// hidden input to place inside every post form
function csrfField(): string
{
    return '<input type="hidden" name="csrfToken" value="' . htmlspecialchars(csrfToken()) . '">';
}

// check the submitted token matches, also accepts the X-CSRF-Token header for fetch() calls
function verifyCsrf(): bool
{
    $sent = $_POST['csrfToken'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && hash_equals(csrfToken(), $sent);
}

// save a message for the next page
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// read the message and clear it
function takeFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// short escape helper for templates
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// trimmed text from the post with a maximum length
function postText(string $key, int $max = 255): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
}

// whole number from the query string or post, null when missing or not a number
function intParam(array $source, string $key): ?int
{
    $value = $source[$key] ?? null;
    return is_string($value) && ctype_digit($value) ? (int) $value : (is_int($value) ? $value : null);
}

// south african rand, R 1 234.50
function money(float|string|null $amount): string
{
    return 'R ' . number_format((float) $amount, 2, '.', ' ');
}

// basic checks used by registration, checkout and staff forms
function isValidEmail(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function isValidPhone(string $phone): bool
{
    return (bool) preg_match('/^\+?[0-9 ()-]{9,20}$/', $phone);
}

function isValidPostalCode(string $code): bool
{
    return (bool) preg_match('/^[0-9]{4}$/', $code);
}

// at least 8 characters with a letter and a number
function passwordProblems(string $password): ?string
{
    if (mb_strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must include at least one letter and one number.';
    }
    return null;
}

// the nine provinces, used by the address forms
const PROVINCES = [
    'Eastern Cape', 'Free State', 'Gauteng', 'KwaZulu-Natal', 'Limpopo',
    'Mpumalanga', 'North West', 'Northern Cape', 'Western Cape',
];

// only allow same-site paths for redirects after login
function safeReturnPath(?string $path, string $fallback): string
{
    if (!is_string($path) || $path === '' || !preg_match('#^[A-Za-z0-9_\-]+\.php(\?[A-Za-z0-9_\-=&%.]*)?$#', $path)) {
        return $fallback;
    }
    return $path;
}
