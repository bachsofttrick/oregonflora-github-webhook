<?php

// ─── Load .env ────────────────────────────────────────────────────────────────
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#') continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

// ─── Config ───────────────────────────────────────────────────────────────────
define('SECRET',      !empty($_ENV['SECRET'])      ? $_ENV['SECRET'] : '');
define('BRANCH',      !empty($_ENV['BRANCH'])      ? $_ENV['BRANCH'] : 'refs/heads/main');
define('LOG_FOLDER',  !empty($_ENV['LOG_FOLDER'])  ? $_ENV['LOG_FOLDER'] : __DIR__);
define('DEPLOY_CMD',  !empty($_ENV['DEPLOY_CMD'])  ? $_ENV['DEPLOY_CMD'] : '');
define('SLACK_WEBHOOK_URL', !empty($_ENV['SLACK_WEBHOOK_URL']) ? $_ENV['SLACK_WEBHOOK_URL'] : '');

$branch_built_to_msg = BRANCH === 'refs/heads/main' ? 'production' : 'development';

// ─── Helpers ─────────────────────────────────────────────────────────────────
function log_msg(string $msg): void {
    if (!LOG_FOLDER) return;
    $logFile = LOG_FOLDER . '/webhook.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function abort(int $code, string $msg, bool $allowLog = true): never {
    http_response_code($code);
    if ($allowLog) log_msg("ABORT $code: $msg");
    exit($msg);
}

function notify_slack(string $text): void {
    if (!SLACK_WEBHOOK_URL) return;

    $body = json_encode([
        'text'    => $text
    ]);

    $ch = curl_init(SLACK_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);
    curl_exec($ch);
    if (curl_errno($ch)) {
        log_msg('Slack notify error: ' . curl_error($ch));
    }
}

// ─── 1. Only accept POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort(405, 'Method Not Allowed', false);
}

// ─── 2. Verify the GitHub signature ──────────────────────────────────────────
$rawBody   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

if (empty($sigHeader)) {
    abort(400, 'Missing X-Hub-Signature-256 header');
}

$expected = 'sha256=' . hash_hmac('sha256', $rawBody, SECRET);

if (!hash_equals($expected, $sigHeader)) {
    abort(401, 'Invalid signature');
}

// ─── 3. Accept only push events ───────────────────────────────────────────────
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';

if ($event !== 'push') {
    abort(200, "Ignored event: $event", false);
}

// ─── 4. Parse payload ────────────────────────────────────────────────────────
$payload = json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    abort(400, 'Invalid JSON payload');
}

$ref       = $payload['ref']                          ?? '';
$pusher    = $payload['pusher']['name']               ?? 'unknown';
$headSha   = $payload['head_commit']['id']            ?? 'unknown';
$repoName  = $payload['repository']['full_name']      ?? 'unknown';
$commitMsg = $payload['head_commit']['message']       ?? '';

log_msg("Push received — repo: $repoName | ref: $ref | pusher: $pusher | sha: " . substr($headSha, 0, 7));

// ─── 5. Filter by branch ─────────────────────────────────────────────────────
if ($ref !== BRANCH) {
    abort(200, "Ignored ref: $ref");
}

// ─── 6. Run deploy command ────────────────────────────────────────────────────
if (DEPLOY_CMD) {
    $output   = [];
    $exitCode = 0;
    exec(DEPLOY_CMD, $output, $exitCode);

    $outputStr = implode("\n", $output);
    log_msg("Deploy exit code: $exitCode");
    log_msg("Deploy output:\n$outputStr");

    if ($exitCode !== 0) {
        $msg = "Deploy at $branch_built_to_msg failed (exit $exitCode):\n$outputStr";
        notify_slack($msg);
        abort(500, $msg);
    }
}

// ─── 7. Respond ──────────────────────────────────────────────────────────────
$msg = "Deploy at $branch_built_to_msg successful for commit: " . substr($headSha, 0, 7) . " — $commitMsg";
http_response_code(200);
notify_slack($msg);
log_msg($msg);
echo json_encode([
    'status'  => 'ok',
    'ref'     => $ref,
    'sha'     => substr($headSha, 0, 7),
    'pusher'  => $pusher,
    'message' => $commitMsg,
]);
?>
