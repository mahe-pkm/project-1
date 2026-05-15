<?php
declare(strict_types=1);

/**
 * Generic Secure PHP Website Updater
 *
 * What it does:
 * - Shows a beautiful deployment status UI on GET.
 * - Accepts secure webhook/manual update on POST.
 * - Verifies secret or GitHub HMAC.
 * - Downloads a fixed Git repository branch zip.
 * - Extracts and validates files.
 * - Creates backup.
 * - Replaces live website files.
 * - Writes deployment logs.
 *
 * Before production:
 * - Change WEBHOOK_SECRET.
 * - Change REPO_NAME.
 * - Change REPO_ZIP_URL.
 * - Test on staging.
 */

const WEBHOOK_SECRET = 'CHANGE_THIS_SECRET';

const REPO_NAME = 'OWNER/REPOSITORY';
const BRANCH_NAME = 'main';
const REPO_ZIP_URL = 'https://codeload.github.com/OWNER/REPOSITORY/zip/refs/heads/main';

const ALLOW_MANUAL_UPDATE = false;

const WEB_ROOT = __DIR__ . '/../';
const DEPLOY_DIR = __DIR__;
const TMP_DIR = DEPLOY_DIR . '/tmp';
const BACKUP_DIR = DEPLOY_DIR . '/backups';
const LOG_FILE = DEPLOY_DIR . '/deploy-log.jsonl';
const LOCK_FILE = DEPLOY_DIR . '/.deploy.lock';

const REQUIRED_PATHS = [
    'index.html',
    'assets'
];

const PROTECTED_ROOT_ITEMS = [
    'deploy',
    '.env',
    '.htaccess',
    'web.config'
];

function now_iso(): string
{
    return date('c');
}

function ensure_dirs(): void
{
    foreach ([TMP_DIR, BACKUP_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

function log_event(string $status, string $message, array $extra = []): void
{
    ensure_dirs();

    $entry = array_merge([
        'time' => now_iso(),
        'status' => $status,
        'repo' => REPO_NAME,
        'branch' => BRANCH_NAME,
        'message' => $message,
    ], $extra);

    file_put_contents(LOG_FILE, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

function latest_logs(int $limit = 12): array
{
    if (!file_exists(LOG_FILE)) {
        return [];
    }

    $lines = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_slice($lines ?: [], -$limit);

    return array_reverse(array_map(function ($line) {
        $decoded = json_decode($line, true);
        return is_array($decoded) ? $decoded : [
            'status' => 'unknown',
            'message' => $line,
            'time' => ''
        ];
    }, $lines));
}

function verify_request(): bool
{
    $body = file_get_contents('php://input') ?: '';

    $githubSignature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

    if ($githubSignature) {
        $expected = 'sha256=' . hash_hmac('sha256', $body, WEBHOOK_SECRET);
        return hash_equals($expected, $githubSignature);
    }

    $token = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';

    if ($token && hash_equals(WEBHOOK_SECRET, $token)) {
        return true;
    }

    if (ALLOW_MANUAL_UPDATE && isset($_POST['manual_token'])) {
        return hash_equals(WEBHOOK_SECRET, (string) $_POST['manual_token']);
    }

    return false;
}

function clean_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
}

function copy_dir(string $source, string $destination, array $excludeNames = []): void
{
    if (!is_dir($source)) {
        throw new RuntimeException('Source folder not found.');
    }

    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    $items = scandir($source);

    if ($items === false) {
        throw new RuntimeException('Could not read source folder.');
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || in_array($item, $excludeNames, true)) {
            continue;
        }

        $src = $source . '/' . $item;
        $dst = $destination . '/' . $item;

        if (is_dir($src)) {
            copy_dir($src, $dst, []);
        } else {
            if (!copy($src, $dst)) {
                throw new RuntimeException('Could not copy file: ' . $item);
            }
        }
    }
}

function download_file(string $url, string $destination): void
{
    $fp = fopen($destination, 'w');

    if (!$fp) {
        throw new RuntimeException('Could not create repository zip file.');
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_FAILONERROR => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_USERAGENT => 'Generic Website Updater'
    ]);

    $ok = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);
    fclose($fp);

    if (!$ok || $httpCode >= 400) {
        @unlink($destination);
        throw new RuntimeException('Repository download failed. ' . $error);
    }
}

function extract_zip(string $zipFile, string $extractTo): string
{
    $zip = new ZipArchive();

    if ($zip->open($zipFile) !== true) {
        throw new RuntimeException('Could not open repository zip.');
    }

    $zip->extractTo($extractTo);
    $zip->close();

    $items = array_values(array_filter(scandir($extractTo) ?: [], function ($item) {
        return $item !== '.' && $item !== '..';
    }));

    if (!$items) {
        throw new RuntimeException('Extracted repository is empty.');
    }

    $repoRoot = $extractTo . '/' . $items[0];

    if (!is_dir($repoRoot)) {
        throw new RuntimeException('Repository root folder not found after extraction.');
    }

    return $repoRoot;
}

function validate_repo(string $repoRoot): void
{
    foreach (REQUIRED_PATHS as $path) {
        if (!file_exists($repoRoot . '/' . $path)) {
            throw new RuntimeException('Required path missing from repo: ' . $path);
        }
    }
}

function create_backup(): string
{
    ensure_dirs();

    $backupPath = BACKUP_DIR . '/backup-' . date('Ymd-His');

    if (!mkdir($backupPath, 0755, true) && !is_dir($backupPath)) {
        throw new RuntimeException('Could not create backup folder.');
    }

    $items = scandir(WEB_ROOT);

    if ($items === false) {
        throw new RuntimeException('Could not read web root for backup.');
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === 'deploy') {
            continue;
        }

        $src = WEB_ROOT . '/' . $item;
        $dst = $backupPath . '/' . $item;

        if (is_dir($src)) {
            copy_dir($src, $dst, []);
        } else {
            copy($src, $dst);
        }
    }

    return $backupPath;
}

function delete_web_root_contents(): void
{
    $items = scandir(WEB_ROOT);

    if ($items === false) {
        throw new RuntimeException('Could not read web root.');
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || in_array($item, PROTECTED_ROOT_ITEMS, true)) {
            continue;
        }

        $path = WEB_ROOT . '/' . $item;

        if (is_dir($path)) {
            clean_dir($path);
            rmdir($path);
        } else {
            unlink($path);
        }
    }
}

function acquire_lock(): void
{
    if (file_exists(LOCK_FILE)) {
        $age = time() - filemtime(LOCK_FILE);

        if ($age < 900) {
            throw new RuntimeException('Deployment already running.');
        }

        unlink(LOCK_FILE);
    }

    file_put_contents(LOCK_FILE, (string) time());
}

function release_lock(): void
{
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
}

function deploy(): array
{
    ensure_dirs();
    acquire_lock();

    try {
        clean_dir(TMP_DIR);

        $zipFile = TMP_DIR . '/repo.zip';
        $extractTo = TMP_DIR . '/extract';

        mkdir($extractTo, 0755, true);

        download_file(REPO_ZIP_URL, $zipFile);
        $repoRoot = extract_zip($zipFile, $extractTo);

        validate_repo($repoRoot);

        $backupPath = create_backup();

        delete_web_root_contents();

        copy_dir($repoRoot, WEB_ROOT, [
            '.git',
            '.github',
            'deploy',
            'node_modules',
            '.env'
        ]);

        log_event('success', 'Deployment completed successfully.', [
            'backup' => basename($backupPath)
        ]);

        return [
            'status' => 'success',
            'message' => 'Deployment completed successfully.',
            'backup' => basename($backupPath)
        ];
    } catch (Throwable $e) {
        log_event('error', $e->getMessage());

        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    } finally {
        release_lock();
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function render_ui(): void
{
    $logs = latest_logs();
    $latest = $logs[0] ?? null;
    $status = (string) ($latest['status'] ?? 'idle');
    $statusClass = $status === 'success' ? 'success' : ($status === 'error' ? 'error' : 'idle');
    $lastTime = (string) ($latest['time'] ?? 'No deployments yet');
    $lastMessage = (string) ($latest['message'] ?? 'Waiting for first deployment.');

    header('Content-Type: text/html; charset=utf-8');

    echo '<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Website Update Center</title>
  <style>
    :root {
      --bg: #eef4ff;
      --card: #ffffff;
      --text: #172033;
      --muted: #667085;
      --primary: #0477ed;
      --success: #12b76a;
      --error: #f04438;
      --warning: #f79009;
      --border: #dbe4f0;
      --shadow: 0 24px 70px rgba(16, 69, 124, .14);
      --radius: 24px;
      --font: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: var(--font);
      background:
        radial-gradient(circle at top left, rgba(4,119,237,.18), transparent 34rem),
        linear-gradient(135deg, #f8fbff 0%, var(--bg) 100%);
      color: var(--text);
      min-height: 100vh;
      padding: 32px;
    }

    .shell {
      width: min(1120px, 100%);
      margin: 0 auto;
    }

    .hero {
      display: grid;
      grid-template-columns: 1.4fr .8fr;
      gap: 24px;
      align-items: stretch;
      margin-bottom: 24px;
    }

    .card {
      background: rgba(255,255,255,.88);
      border: 1px solid rgba(219,228,240,.9);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      backdrop-filter: blur(14px);
      padding: 28px;
    }

    .eyebrow {
      color: var(--primary);
      font-weight: 800;
      letter-spacing: .12em;
      font-size: 12px;
      text-transform: uppercase;
      margin-bottom: 10px;
    }

    h1 {
      margin: 0;
      font-size: clamp(30px, 5vw, 52px);
      line-height: 1.02;
      letter-spacing: -0.04em;
    }

    .lead {
      color: var(--muted);
      line-height: 1.7;
      margin: 18px 0 0;
      max-width: 720px;
    }

    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border-radius: 999px;
      padding: 10px 14px;
      font-weight: 800;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: .04em;
    }

    .status-pill.success { background: rgba(18,183,106,.12); color: var(--success); }
    .status-pill.error { background: rgba(240,68,56,.12); color: var(--error); }
    .status-pill.idle { background: rgba(4,119,237,.12); color: var(--primary); }

    .dot {
      width: 9px;
      height: 9px;
      border-radius: 999px;
      background: currentColor;
      box-shadow: 0 0 0 6px rgba(4,119,237,.08);
    }

    .meta {
      display: grid;
      gap: 16px;
      margin-top: 22px;
    }

    .meta-item {
      padding: 16px;
      border: 1px solid var(--border);
      border-radius: 18px;
      background: #fbfdff;
    }

    .meta-label {
      color: var(--muted);
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      margin-bottom: 6px;
    }

    .meta-value {
      font-weight: 800;
      overflow-wrap: anywhere;
    }

    .grid {
      display: grid;
      grid-template-columns: .9fr 1.1fr;
      gap: 24px;
    }

    .timeline {
      display: grid;
      gap: 14px;
    }

    .log-item {
      display: grid;
      gap: 7px;
      padding: 16px;
      border-radius: 18px;
      border: 1px solid var(--border);
      background: #fbfdff;
    }

    .log-top {
      display: flex;
      justify-content: space-between;
      gap: 14px;
      align-items: center;
      color: var(--muted);
      font-size: 13px;
    }

    .log-status {
      font-weight: 800;
      text-transform: uppercase;
      font-size: 12px;
    }

    .log-status.success { color: var(--success); }
    .log-status.error { color: var(--error); }

    .log-message {
      color: var(--text);
      font-weight: 650;
    }

    .warning {
      border-color: rgba(247,144,9,.34);
      background: rgba(247,144,9,.08);
    }

    .btn {
      border: 0;
      border-radius: 16px;
      background: var(--primary);
      color: white;
      padding: 14px 18px;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 14px 28px rgba(4,119,237,.22);
    }

    .input {
      width: 100%;
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 13px 14px;
      margin: 12px 0;
    }

    @media (max-width: 850px) {
      body { padding: 18px; }
      .hero, .grid { grid-template-columns: 1fr; }
      .card { padding: 22px; }
    }
  </style>
</head>
<body>
  <main class="shell">
    <section class="hero">
      <div class="card">
        <div class="eyebrow">Deployment Center</div>
        <h1>Website Update Status</h1>
        <p class="lead">This page shows the current deployment status for your Git-powered website update flow. Secrets are hidden and update settings are locked in server configuration.</p>
      </div>

      <aside class="card">
        <span class="status-pill ' . h($statusClass) . '">
          <span class="dot"></span>
          ' . h($status) . '
        </span>

        <div class="meta">
          <div class="meta-item">
            <div class="meta-label">Last Message</div>
            <div class="meta-value">' . h($lastMessage) . '</div>
          </div>
          <div class="meta-item">
            <div class="meta-label">Last Update Time</div>
            <div class="meta-value">' . h($lastTime) . '</div>
          </div>
        </div>
      </aside>
    </section>

    <section class="grid">
      <div class="card">
        <div class="eyebrow">Environment</div>

        <div class="meta">
          <div class="meta-item">
            <div class="meta-label">Repository</div>
            <div class="meta-value">' . h(REPO_NAME) . '</div>
          </div>

          <div class="meta-item">
            <div class="meta-label">Branch</div>
            <div class="meta-value">' . h(BRANCH_NAME) . '</div>
          </div>

          <div class="meta-item warning">
            <div class="meta-label">Safety</div>
            <div class="meta-value">Repo URL, branch, and server path are fixed in PHP config. Public requests cannot change them.</div>
          </div>
        </div>';

    if (ALLOW_MANUAL_UPDATE) {
        echo '<form method="post" style="margin-top:20px">
          <div class="meta-label">Manual Update Token</div>
          <input class="input" type="password" name="manual_token" placeholder="Enter deployment token">
          <button class="btn" type="submit">Run Manual Update</button>
        </form>';
    }

    echo '</div>

      <div class="card">
        <div class="eyebrow">Recent Logs</div>
        <div class="timeline">';

    if (!$logs) {
        echo '<div class="log-item">
          <div class="log-message">No deployment logs yet.</div>
        </div>';
    }

    foreach ($logs as $log) {
        $logStatus = h((string) ($log['status'] ?? 'unknown'));
        $logTime = h((string) ($log['time'] ?? ''));
        $logMessage = h((string) ($log['message'] ?? ''));

        echo '<div class="log-item">
          <div class="log-top">
            <span>' . $logTime . '</span>
            <span class="log-status ' . $logStatus . '">' . $logStatus . '</span>
          </div>
          <div class="log-message">' . $logMessage . '</div>
        </div>';
    }

    echo '</div>
      </div>
    </section>
  </main>
</body>
</html>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_request()) {
        http_response_code(403);
        log_event('error', 'Invalid webhook signature or token.');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid webhook signature or token.']);
        exit;
    }

    $result = deploy();

    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

render_ui();
