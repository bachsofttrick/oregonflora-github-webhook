<?php

// ─── Config ───────────────────────────────────────────────────────────────────
define('SECRET',      'f59f6536d7f962e9a3844502a2d4ddde93146fb343865cdbc077ffbebe1cd473');   // Must match the secret in GitHub
define('BRANCH',      'refs/heads/main');        // Branch to react to
define('LOG_FILE',    __DIR__ . '/webhook.log'); // Set to '' to disable logging
define('DEPLOY_CMD',  'ls'); // Command to run on push

// ─── Slack Config ─────────────────────────────────────────────────────────────
define('SLACK_WEBHOOK_URL', 'https://hooks.slack.com/services/XXX/YYY/ZZZ'); // Incoming Webhook URL
define('SLACK_CHANNEL',     '#deployments');  // Channel to post in (optional, can be set in Slack)
define('SLACK_USERNAME',    'Deploy Bot');    // Display name
define('SLACK_ICON',        ':rocket:');      // Emoji icon

// ─── Helpers ─────────────────────────────────────────────────────────────────
function log_msg(string $msg): void {
    if (!LOG_FILE) return;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function abort(int $code, string $msg): never {
    http_response_code($code);
    log_msg("ABORT $code: $msg");
    exit($msg);
}

// ─── 1. Only accept POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort(405, 'Method Not Allowed');
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
    http_response_code(200);
    exit("Ignored event: $event");
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
    http_response_code(200);
    log_msg("Ignored ref: $ref (watching " . BRANCH . ")");
    exit("Ignored ref: $ref");
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
        abort(500, "Deploy failed (exit $exitCode):\n$outputStr");
    }
}

// ─── 7. Notify Slack ─────────────────────────────────────────────────────────
function notify_slack(string $text, bool $success = true, array $fields = []): void {
    if (!SLACK_WEBHOOK_URL) return;

    $color = $success ? '#36a64f' : '#d9534f'; // green / red

    $attachment = [
        'color'    => $color,
        'text'     => $text,
        'fields'   => $fields,
        'footer'   => 'GitHub Webhook',
        'ts'       => time(),
    ];

    $body = json_encode([
        'username'    => SLACK_USERNAME,
        'icon_emoji'  => SLACK_ICON,
        'channel'     => SLACK_CHANNEL,
        'attachments' => [$attachment],
    ]);

    $ch = curl_init(SLACK_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);
    $res = curl_exec($ch);
    if (curl_errno($ch)) {
        log_msg('Slack notify error: ' . curl_error($ch));
    }
    curl_close($ch);
}

// ─── 8. Respond ──────────────────────────────────────────────────────────────
http_response_code(200);
log_msg("Deploy successful for commit: " . substr($headSha, 0, 7) . " — $commitMsg");
echo json_encode([
    'status'  => 'ok',
    'ref'     => $ref,
    'sha'     => substr($headSha, 0, 7),
    'pusher'  => $pusher,
    'message' => $commitMsg,
]);
