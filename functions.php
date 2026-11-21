<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function interests_list(): array {
    return [
        'General Health', 'Maternal & Child Health', 'Nutrition', 'Mental Wellbeing',
        'Chronic Conditions', 'Fitness', 'First Aid', 'Malaria & Infectious Diseases'
    ];
}

function parse_interests(?string $s): array {
    if (!$s) return [];
    return array_values(array_filter(array_map('trim', explode(',', $s))));
}

function age_from_dob(?string $dob): string {
    if (!$dob) return 'N/A';
    return (new DateTime($dob))->diff(new DateTime())->y . ' years';
}

function avatar_url(?string $path): string {
    if ($path && is_file(__DIR__ . '/' . $path)) return $path;
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect width="120" height="120" fill="#e7f0fd"/><circle cx="60" cy="46" r="22" fill="#0B5ED7"/><path d="M20 112c4-26 22-38 40-38s36 12 40 38z" fill="#0B5ED7"/></svg>';
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

function tip_of_the_day(): string {
    $tips = [
        "Drink at least 8 glasses of clean water today. Staying hydrated helps your energy and focus.",
        "Sleep 7 to 9 hours tonight. Good rest strengthens your immune system.",
        "Wash your hands with soap for 20 seconds, especially before eating.",
        "Add a fruit or vegetable to every meal. Colour on your plate means more nutrients.",
        "Take a 30-minute walk today. Regular movement is good for your heart and mood.",
        "Sleep under a treated mosquito net to help protect yourself from malaria.",
        "Limit sugary drinks and choose water or fresh juice without added sugar.",
        "Take a few minutes to breathe deeply and stretch. Managing stress protects your health.",
        "Don't ignore a fever that lasts more than two days. Visit the hospital for a check-up.",
        "Regular check-ups catch problems early. Book a visit even when you feel fine."
    ];
    return $tips[date('z') % count($tips)];
}

function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header("Location: dashboard.php");
        exit;
    }
}

function category_style(string $cat): array {
    $map = [
        'General Health'                => ['#0B5ED7', '#4db8ff', 'bi-heart-pulse'],
        'Maternal & Child Health'       => ['#c2417a', '#f78fb3', 'bi-balloon-heart'],
        'Nutrition'                     => ['#2e8b57', '#8fd694', 'bi-basket'],
        'Mental Wellbeing'              => ['#6f42c1', '#b79df0', 'bi-emoji-smile'],
        'Chronic Conditions'            => ['#d9480f', '#ffb27a', 'bi-activity'],
        'Fitness'                       => ['#0f8b8d', '#7fe0d8', 'bi-bicycle'],
        'First Aid'                     => ['#c92a2a', '#ff8787', 'bi-bandaid'],
        'Malaria & Infectious Diseases' => ['#1971c2', '#74c0fc', 'bi-shield-check'],
    ];
    return $map[$cat] ?? ['#0B5ED7', '#4db8ff', 'bi-journal-medical'];
}

if (!defined('MAX_MENTEES')) define('MAX_MENTEES', 5);

function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out !== '' ? $out : '?';
}

function time_ago(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) {
        $d = (int)floor($diff / 86400);
        return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
    }
    return date('d M Y', strtotime($dt));
}

/* ===== Admin helpers ===== */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_verify(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

function csv_safe($v): string {
    $v = (string)$v;
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

function daily_counts(PDO $pdo, string $table, string $col, string $from, string $to, string $extra = ''): array {
    $st = $pdo->prepare("SELECT DATE($col) AS d, COUNT(*) AS c FROM $table WHERE $col BETWEEN ? AND ? $extra GROUP BY DATE($col)");
    $st->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['d']] = (int)$r['c'];
    return $out;
}

function date_range_labels(string $from, string $to): array {
    $labels = [];
    $d = new DateTime($from);
    $end = new DateTime($to);
    while ($d <= $end) {
        $labels[] = $d->format('Y-m-d');
        $d->modify('+1 day');
    }
    return $labels;
}

function series(array $labels, array $counts): array {
    return array_map(fn($l) => $counts[$l] ?? 0, $labels);
}

function resource_types(): array {
    return ['guide', 'tutorial', 'checklist', 'video', 'pdf', 'link'];
}