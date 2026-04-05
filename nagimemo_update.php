<?php
/**
 * NagiMemo Updater
 * NagiMemo Updater v1.1.8
 * Updater Build: 2026040604
 * GitHubから最新版のNagiMemo一式と nagimemo_update.php を取得・更新するスクリプト
 *
 * 設置場所: てがろぐ(tegalog.cgi)と同じディレクトリ
 */

// ============================================================
// 【ユーザー設定項目】ここを自由に書き換えてください
// ============================================================

/**
 * 1. アップデート専用パスワード
 * 空にするとパスワードなしで誰でもアクセス可能になります。
 * セキュリティのため、何らかの合言葉を設定することを強く推奨します。
 */
$update_password = 'ねこちゃん';

/**
 * 2. IP制限
 * 特定のIPアドレスからしかアクセスできないようにしたい場合に指定します。
 * 例: $allowed_ips = array('123.456.78.9');
 * 空配列 array() のままであれば、IP制限は行われません。
 */
$allowed_ips = array();

// ============================================================
// 【システム設定】ここから下は通常触る必要はありません
// ============================================================

$repo_user = 'Lichiphen';
$repo_name = 'NagiMemo';
$branch = 'main';
$package_dirs = array('skin-nagimemo', 'NagiGallery', 'NagiPicts', 'skin-nagi_sitemap');
$package_labels = array(
    'skin-nagimemo' => 'skin-nagimemo',
    'NagiGallery' => 'NagiGallery',
    'NagiPicts' => 'NagiPicts',
    'skin-nagi_sitemap' => 'skin-nagi_sitemap',
);
$version_file = 'skin-nagimemo/modules/copyright.html';
$updater_file = basename(__FILE__);
$updater_entry = 'nagimemo_update.php';
$fallback_return_url = 'tegalog.cgi';
$status_mode = isset($_GET['mode']) && $_GET['mode'] === 'status';
$user_settings_marker = "// ============================================================\n// 【ユーザー設定項目】ここを自由に書き換えてください\n// ============================================================\n";
$system_settings_marker = "// ============================================================\n// 【システム設定】ここから下は通常触る必要はありません\n// ============================================================\n";

function normalize_newlines($content)
{
    return str_replace(array("\r\n", "\r"), "\n", $content);
}

function respond_json($payload, $status_code)
{
    http_response_code($status_code);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fetch_remote_data($url, $timeout)
{
    $ctx = stream_context_create(array(
        'http' => array(
            'timeout' => $timeout,
            'header' => "User-Agent: NagiMemo-Updater\r\n",
        ),
    ));

    $content = @file_get_contents($url, false, $ctx);
    if ($content === false) {
        return null;
    }

    return $content;
}

function get_version_from_content($content)
{
    if (!is_string($content) || $content === '') {
        return null;
    }

    if (preg_match('/NagiMemo(?: Updater)? v([\d\.]+)/', $content, $matches)) {
        return $matches[1];
    }

    return null;
}

function get_updater_build_from_content($content)
{
    if (!is_string($content) || $content === '') {
        return null;
    }

    if (preg_match('/Updater Build:\s*([0-9]{10,14})/', $content, $matches)) {
        return $matches[1];
    }

    return null;
}

function get_local_version($file_path)
{
    if (!file_exists($file_path)) {
        return null;
    }

    $content = @file_get_contents($file_path);
    return get_version_from_content($content);
}

function extract_settings_block($content, $start_marker, $end_marker)
{
    $content = normalize_newlines($content);
    $start_pos = strpos($content, $start_marker);
    $end_pos = strpos($content, $end_marker);

    if ($start_pos === false || $end_pos === false) {
        return null;
    }

    $start_pos += strlen($start_marker);
    if ($end_pos < $start_pos) {
        return null;
    }

    return substr($content, $start_pos, $end_pos - $start_pos);
}

function replace_settings_block($content, $replacement, $start_marker, $end_marker)
{
    $content = normalize_newlines($content);
    $start_pos = strpos($content, $start_marker);
    $end_pos = strpos($content, $end_marker);

    if ($start_pos === false || $end_pos === false) {
        return $content;
    }

    $start_pos += strlen($start_marker);
    if ($end_pos < $start_pos) {
        return $content;
    }

    return substr($content, 0, $start_pos) . $replacement . substr($content, $end_pos);
}

function get_updater_signature($content, $start_marker, $end_marker)
{
    if (!is_string($content) || $content === '') {
        return null;
    }

    $normalized = normalize_newlines($content);
    $normalized = replace_settings_block($normalized, "__NAGIMEMO_USER_SETTINGS__\n", $start_marker, $end_marker);
    return substr(hash('sha256', $normalized), 0, 12);
}

function compare_build_stamps($left, $right)
{
    if (!is_string($left) || !is_string($right) || $left === '' || $right === '') {
        return null;
    }

    $left = ltrim($left, '0');
    $right = ltrim($right, '0');
    $left = $left === '' ? '0' : $left;
    $right = $right === '' ? '0' : $right;

    if (strlen($left) < strlen($right)) {
        return -1;
    }

    if (strlen($left) > strlen($right)) {
        return 1;
    }

    return strcmp($left, $right);
}

function evaluate_updater_state($local_content, $remote_content, $start_marker, $end_marker)
{
    $local_version = get_version_from_content($local_content);
    $remote_version = get_version_from_content($remote_content);
    $local_build = get_updater_build_from_content($local_content);
    $remote_build = get_updater_build_from_content($remote_content);
    $local_signature = get_updater_signature($local_content, $start_marker, $end_marker);
    $remote_signature = get_updater_signature($remote_content, $start_marker, $end_marker);

    $state = 'unknown';
    $needs_update = false;
    $has_difference = $local_signature !== null
        && $remote_signature !== null
        && $local_signature !== $remote_signature;

    if ($remote_signature === null) {
        $state = 'remote_unavailable';
    } elseif ($local_signature !== null && $remote_signature === $local_signature) {
        $state = 'latest';
    } else {
        $build_compare = compare_build_stamps($local_build, $remote_build);

        if ($build_compare !== null) {
            if ($build_compare < 0) {
                $state = 'remote_newer';
                $needs_update = true;
            } elseif ($build_compare > 0) {
                $state = 'local_newer';
            } else {
                $state = 'diverged_same_build';
            }
        } else {
            if ($local_build === null && $remote_build !== null) {
                $state = 'legacy_local';
                $needs_update = true;
            } elseif ($local_build !== null && $remote_build === null) {
                $state = 'local_newer';
            } elseif ($local_version !== null && $remote_version !== null) {
                $version_compare = version_compare($local_version, $remote_version);
                if ($version_compare < 0) {
                    $state = 'remote_newer';
                    $needs_update = true;
                } elseif ($version_compare > 0) {
                    $state = 'local_newer';
                } else {
                    $state = 'diverged_same_build';
                }
            } else {
                $state = $has_difference ? 'diverged_same_build' : 'latest';
            }
        }
    }

    return array(
        'state' => $state,
        'needs_update' => $needs_update,
        'has_difference' => $has_difference,
        'local_version' => $local_version,
        'remote_version' => $remote_version,
        'local_build' => $local_build,
        'remote_build' => $remote_build,
        'local_signature' => $local_signature,
        'remote_signature' => $remote_signature,
    );
}

function merge_updater_with_local_settings($remote_content, $local_content, $start_marker, $end_marker)
{
    $local_settings = extract_settings_block($local_content, $start_marker, $end_marker);
    if ($local_settings === null) {
        return normalize_newlines($remote_content);
    }

    return replace_settings_block($remote_content, $local_settings, $start_marker, $end_marker);
}

function sanitize_return_url($value)
{
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);
    if ($value === '' || strpos($value, '//') === 0) {
        return '';
    }

    $parts = @parse_url($value);
    if ($parts === false) {
        return '';
    }

    if (!empty($parts['scheme']) || !empty($parts['host'])) {
        $current_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $current_parts = $current_host !== '' ? @parse_url((isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . $current_host) : array();
        $request_host = isset($current_parts['host']) ? $current_parts['host'] : $current_host;
        if ($request_host === '' || !isset($parts['host']) || strcasecmp($parts['host'], $request_host) !== 0) {
            return '';
        }

        return $value;
    }

    return $value;
}

function build_return_href($return_url, $fallback)
{
    if ($return_url !== '') {
        return $return_url;
    }

    return $fallback;
}

function render_hidden_return_input($return_url)
{
    if ($return_url === '') {
        return '';
    }

    return '<input type="hidden" name="return_url" value="' . htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8') . '">';
}

function updater_status_text($updater_info)
{
    if (!is_array($updater_info) || !isset($updater_info['state'])) {
        return '不明';
    }

    if ($updater_info['state'] === 'remote_unavailable') {
        return '取得失敗';
    }

    if ($updater_info['needs_update']) {
        return '更新あり';
    }

    if ($updater_info['state'] === 'local_newer' || $updater_info['state'] === 'diverged_same_build') {
        return 'ローカル差分';
    }

    return '最新';
}

function updater_detail_text($updater_info)
{
    if (!is_array($updater_info) || !isset($updater_info['state'])) {
        return '更新状態を判定できませんでした。';
    }

    if ($updater_info['state'] === 'remote_unavailable') {
        return 'GitHubの更新情報を取得できませんでした。';
    }

    if ($updater_info['state'] === 'remote_newer' || $updater_info['state'] === 'legacy_local') {
        $local_label = $updater_info['local_build'] !== null
            ? 'build ' . $updater_info['local_build']
            : ($updater_info['local_version'] !== null ? 'v' . $updater_info['local_version'] : '不明');
        $remote_label = $updater_info['remote_build'] !== null
            ? 'build ' . $updater_info['remote_build']
            : ($updater_info['remote_version'] !== null ? 'v' . $updater_info['remote_version'] : '不明');
        return 'GitHub版のほうが新しいため更新対象です。(' . $local_label . ' → ' . $remote_label . ')';
    }

    if ($updater_info['state'] === 'local_newer') {
        return 'この環境の nagimemo_update.php は GitHub 公開版より新しいか、未公開の変更を含んでいます。更新対象にはしません。';
    }

    if ($updater_info['state'] === 'diverged_same_build') {
        return 'GitHub版と内容差分はありますが、GitHub のほうが新しいとは判定されないため自動更新対象にはしません。';
    }

    return 'GitHub上の本体と一致しています。';
}

function build_update_headline($skin_needs_update, $updater_needs_update, $skin_repair_needed)
{
    if ($skin_repair_needed && $updater_needs_update) {
        return 'NagiMemo 一式の修復更新と nagimemo_update.php 本体の更新を検出しました。';
    }

    if ($skin_repair_needed) {
        return 'NagiMemo 一式の設置内容に問題を検出しました。';
    }

    if ($skin_needs_update && $updater_needs_update) {
        return 'GitHub 上で NagiMemo 一式と nagimemo_update.php 本体の両方に新しい更新を検出しました。';
    }

    if ($skin_needs_update) {
        return 'GitHub 上で NagiMemo 一式の更新を検出しました。';
    }

    if ($updater_needs_update) {
        return 'GitHub 上で nagimemo_update.php 本体の更新を検出しました。';
    }

    return '現在の設置内容は最新版です。';
}

function build_update_intro($skin_needs_update, $updater_needs_update, $updater_info, $skin_repair_needed)
{
    if ($skin_repair_needed && $updater_needs_update) {
        return '壊れたアセット参照や不足ファイルを修復しつつ、nagimemo_update.php 本体も更新できます。';
    }

    if ($skin_repair_needed) {
        return '壊れたアセット参照や不足ファイルを検出したため、skin-nagimemo / NagiGallery / NagiPicts / skin-nagi_sitemap を修復更新します。';
    }

    if ($skin_needs_update && $updater_needs_update) {
        return 'skin-nagimemo / NagiGallery / NagiPicts / skin-nagi_sitemap と nagimemo_update.php 本体をまとめて確認・更新できます。';
    }

    if ($skin_needs_update) {
        if (is_array($updater_info) && isset($updater_info['state']) && ($updater_info['state'] === 'local_newer' || $updater_info['state'] === 'diverged_same_build')) {
            return 'NagiMemo 一式は更新対象です。nagimemo_update.php 本体はこの環境の差分を保持するため、自動更新対象にはしません。';
        }

        return 'skin-nagimemo / NagiGallery / NagiPicts / skin-nagi_sitemap をまとめて更新します。';
    }

    if ($updater_needs_update) {
        return 'nagimemo_update.php 本体のみ更新対象です。';
    }

    if (is_array($updater_info) && isset($updater_info['state']) && ($updater_info['state'] === 'local_newer' || $updater_info['state'] === 'diverged_same_build')) {
        return 'nagimemo_update.php 本体にはローカル差分がありますが、GitHub のほうが新しいとは判定されないため更新対象にはしません。';
    }

    return '現在の設置内容は最新版です。';
}

function get_package_target_root($relative_path, $package_dirs)
{
    if (!is_string($relative_path) || $relative_path === '') {
        return null;
    }

    foreach ($package_dirs as $dir) {
        if ($relative_path === $dir || strpos($relative_path, $dir . '/') === 0) {
            return $dir;
        }
    }

    return null;
}

function build_package_update_summary($counts, $labels)
{
    if (!is_array($counts) || empty($counts)) {
        return '';
    }

    $parts = array();
    foreach ($counts as $dir => $count) {
        if ($count < 1) {
            continue;
        }

        $label = isset($labels[$dir]) ? $labels[$dir] : $dir;
        $parts[] = $label . ': ' . $count . '件';
    }

    return implode(' / ', $parts);
}

function detect_skin_health_issues()
{
    $issues = array();
    $required_files = array(
        'skin-nagimemo/shared-heatmap.css',
        'skin-nagimemo/heatmap-labels.js',
        'skin-nagimemo/updater-notice.js',
        'skin-nagimemo/modules/updater-notice-modal.html',
    );
    $cover_files = array(
        'skin-nagimemo/skin-cover.html',
        'NagiGallery/skin-cover.html',
        'NagiPicts/skin-cover.html',
        'skin-nagi_sitemap/skin-cover.html',
    );

    foreach ($required_files as $file_path) {
        if (!file_exists($file_path)) {
            $issues[] = $file_path . ' が見つかりません。';
        }
    }

    foreach ($cover_files as $cover_file) {
        if (!file_exists($cover_file)) {
            $issues[] = $cover_file . ' が見つかりません。';
            continue;
        }

        $content = @file_get_contents($cover_file);
        if ($content === false) {
            $issues[] = $cover_file . ' を読み込めません。';
            continue;
        }

        if (preg_match('/P20[0-9]{8,}/', $content)) {
            $issues[] = $cover_file . ' に壊れたアセット参照を検出しました。';
        }
    }

    return array_values(array_unique($issues));
}

$request_return_url = '';
if (isset($_POST['return_url'])) {
    $request_return_url = sanitize_return_url($_POST['return_url']);
} elseif (isset($_GET['return_url'])) {
    $request_return_url = sanitize_return_url($_GET['return_url']);
}

$remote_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
if (!empty($allowed_ips) && !in_array($remote_addr, $allowed_ips, true)) {
    if ($status_mode) {
        respond_json(array('error' => 'Access denied.'), 403);
    }

    http_response_code(403);
    die('Access Denied: Your IP address is not allowed.');
}

$remote_version_url = "https://raw.githubusercontent.com/{$repo_user}/{$repo_name}/{$branch}/{$version_file}";
$remote_updater_url = "https://raw.githubusercontent.com/{$repo_user}/{$repo_name}/{$branch}/{$updater_entry}";
$zip_url = "https://github.com/{$repo_user}/{$repo_name}/archive/refs/heads/{$branch}.zip";

$local_skin_version = get_local_version($version_file);
$remote_skin_content = fetch_remote_data($remote_version_url, 5);
$remote_skin_version = get_version_from_content($remote_skin_content);
$skin_health_issues = detect_skin_health_issues();
$skin_version_update = $local_skin_version && $remote_skin_version && version_compare($local_skin_version, $remote_skin_version, '<');
$skin_repair_needed = !$skin_version_update && !empty($skin_health_issues);
$skin_needs_update = $skin_version_update || $skin_repair_needed;

$local_updater_content = @file_get_contents(__FILE__);
$remote_updater_content = fetch_remote_data($remote_updater_url, 5);
$updater_info = evaluate_updater_state($local_updater_content, $remote_updater_content, $user_settings_marker, $system_settings_marker);
$local_updater_version = $updater_info['local_version'];
$remote_updater_version = $updater_info['remote_version'];
$local_updater_build = $updater_info['local_build'];
$remote_updater_build = $updater_info['remote_build'];
$local_updater_signature = $updater_info['local_signature'];
$remote_updater_signature = $updater_info['remote_signature'];
$updater_needs_update = $updater_info['needs_update'];

$skin_signature = $remote_skin_version !== null ? 'skin:' . $remote_skin_version : '';
if ($skin_repair_needed) {
    $skin_signature .= ':repair';
}
$updater_signature = $remote_updater_signature !== null ? 'updater:' . $remote_updater_signature : '';
$any_update_available = $skin_needs_update || $updater_needs_update;

if ($status_mode) {
    respond_json(array(
        'has_update' => $any_update_available,
        'update_url' => $updater_file,
        'skin' => array(
            'local_version' => $local_skin_version,
            'remote_version' => $remote_skin_version,
            'needs_update' => $skin_needs_update,
            'repair_needed' => $skin_repair_needed,
            'health_issues' => $skin_health_issues,
            'signature' => $skin_signature,
            'targets' => array_values($package_dirs),
        ),
        'updater' => array(
            'local_version' => $local_updater_version,
            'remote_version' => $remote_updater_version,
            'local_build' => $local_updater_build,
            'remote_build' => $remote_updater_build,
            'state' => $updater_info['state'],
            'needs_update' => $updater_needs_update,
            'signature' => $updater_signature,
        ),
    ), 200);
}

session_start();

$auth_error = false;
if (!empty($update_password)) {
    if (isset($_POST['password']) && $_POST['password'] === $update_password) {
        $_SESSION['nagimemo_auth'] = true;
    } elseif (isset($_POST['password'])) {
        $auth_error = true;
    }
}

if (!empty($update_password) && empty($_SESSION['nagimemo_auth'])) {
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Authentication - NagiMemo Updater</title>
        <style>
            :root {
                --bg-color: #fcfaf2;
                --container-bg: #ffffff;
                --accent-color: #7d6b5d;
                --text-color: #4a4238;
                --border-color: #e0dbd1;
                --warning-color: #d77b7b;
            }
            body {
                background-color: var(--bg-color);
                color: var(--text-color);
                font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Hiragino Sans", Meiryo, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                padding: 16px;
                box-sizing: border-box;
            }
            .login-box {
                background: var(--container-bg);
                padding: 40px;
                border-radius: 14px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                border: 1px solid var(--border-color);
                width: min(100%, 360px);
                text-align: center;
            }
            h1 {
                color: var(--accent-color);
                font-size: 1.3rem;
                margin: 0 0 12px;
                letter-spacing: 0.08em;
            }
            p {
                margin: 0 0 18px;
                font-size: 0.95rem;
                line-height: 1.7;
            }
            .error {
                color: var(--warning-color);
                font-weight: 700;
            }
            input[type="password"] {
                width: 100%;
                padding: 12px;
                border: 1px solid var(--border-color);
                border-radius: 8px;
                margin-bottom: 16px;
                box-sizing: border-box;
                background: #fffdf9;
            }
            button,
            .back-link {
                width: 100%;
                padding: 12px;
                border: none;
                border-radius: 8px;
                background: var(--accent-color);
                color: #fff;
                cursor: pointer;
                transition: opacity 0.2s;
                font-size: 1rem;
                text-decoration: none;
                display: inline-block;
                box-sizing: border-box;
            }
            button:hover,
            .back-link:hover {
                opacity: 0.92;
            }
            .back-link {
                margin-top: 10px;
                background: #b2a394;
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>NagiMemo Updater</h1>
            <p>アップデート画面を開くにはパスワードが必要です。</p>
            <?php if ($auth_error): ?>
                <p class="error">パスワードが一致しません。</p>
            <?php endif; ?>
            <form method="post">
                <?php echo render_hidden_return_input($request_return_url); ?>
                <input type="password" name="password" placeholder="Password" required autofocus>
                <button type="submit">Login</button>
            </form>
            <a class="back-link" href="<?php echo htmlspecialchars(build_return_href($request_return_url, $fallback_return_url), ENT_QUOTES, 'UTF-8'); ?>">戻る</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$message_lines = array();
$error = false;
$manual_replace_path = '';
$updated_skin_files = 0;
$updated_package_counts = array();

if (isset($_POST['update']) && $any_update_available) {
    $temp_zip = 'temp_update.zip';
    $zip_binary = fetch_remote_data($zip_url, 20);

    if ($zip_binary === null) {
        $message_lines[] = 'GitHubからのダウンロードに失敗しました。';
        $error = true;
    } elseif (@file_put_contents($temp_zip, $zip_binary) === false) {
        $message_lines[] = '一時ZIPファイルを保存できませんでした。';
        $error = true;
    } else {
        $zip = new ZipArchive();
        if ($zip->open($temp_zip) === true) {
            $extract_root = "{$repo_name}-{$branch}/";

            if ($skin_needs_update) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    if (strpos($filename, $extract_root) !== 0) {
                        continue;
                    }

                    $relative_path = substr($filename, strlen($extract_root));
                    $target_root = get_package_target_root($relative_path, $package_dirs);
                    if ($target_root === null) {
                        continue;
                    }

                    if (substr($filename, -1) === '/') {
                        if (!is_dir($relative_path) && !@mkdir($relative_path, 0755, true)) {
                            $message_lines[] = $relative_path . ' の作成に失敗しました。';
                            $error = true;
                        }
                        continue;
                    }

                    $dir = dirname($relative_path);
                    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                        $message_lines[] = $dir . ' の作成に失敗しました。';
                        $error = true;
                        continue;
                    }

                    $remote_file_content = $zip->getFromIndex($i);
                    if ($remote_file_content === false) {
                        $message_lines[] = $relative_path . ' の取得に失敗しました。';
                        $error = true;
                        continue;
                    }

                    if (file_exists($relative_path)) {
                        $local_file_content = @file_get_contents($relative_path);
                        if ($local_file_content !== false && $local_file_content === $remote_file_content) {
                            continue;
                        }
                    }

                    if (@file_put_contents($relative_path, $remote_file_content, LOCK_EX) !== false) {
                        $updated_skin_files++;
                        if (!isset($updated_package_counts[$target_root])) {
                            $updated_package_counts[$target_root] = 0;
                        }
                        $updated_package_counts[$target_root]++;
                    } else {
                        $message_lines[] = $relative_path . ' の更新に失敗しました。';
                        $error = true;
                    }
                }

                if ($updated_skin_files > 0) {
                    $package_summary = build_package_update_summary($updated_package_counts, $package_labels);
                    $message_lines[] = 'NagiMemo 一式を更新しました。' . ($package_summary !== '' ? ' (' . $package_summary . ')' : '');
                    $local_skin_version = $remote_skin_version;
                    $skin_health_issues = array();
                    $skin_repair_needed = false;
                    $skin_needs_update = false;
                } else {
                    $message_lines[] = 'NagiMemo 一式は更新対象でしたが、差分はありませんでした。';
                    $local_skin_version = $remote_skin_version;
                    $skin_health_issues = array();
                    $skin_repair_needed = false;
                    $skin_needs_update = false;
                }
            }

            if ($updater_needs_update) {
                $remote_zip_updater = $zip->getFromName($extract_root . $updater_entry);
                if ($remote_zip_updater === false) {
                    $message_lines[] = 'ZIP内に nagimemo_update.php が見つかりませんでした。';
                    $error = true;
                } else {
                    $merged_updater = merge_updater_with_local_settings(
                        $remote_zip_updater,
                        $local_updater_content,
                        $user_settings_marker,
                        $system_settings_marker
                    );

                    if (@file_put_contents(__FILE__, $merged_updater, LOCK_EX) !== false) {
                        $message_lines[] = 'nagimemo_update.php 本体を更新しました。';
                        $local_updater_content = $merged_updater;
                        $updater_info = evaluate_updater_state($local_updater_content, $remote_updater_content, $user_settings_marker, $system_settings_marker);
                        $local_updater_version = $updater_info['local_version'];
                        $remote_updater_version = $updater_info['remote_version'];
                        $local_updater_build = $updater_info['local_build'];
                        $remote_updater_build = $updater_info['remote_build'];
                        $local_updater_signature = $updater_info['local_signature'];
                        $remote_updater_signature = $updater_info['remote_signature'];
                        $updater_needs_update = $updater_info['needs_update'];
                    } else {
                        $manual_replace_path = 'nagimemo_update.new.php';
                        if (@file_put_contents($manual_replace_path, $merged_updater, LOCK_EX) !== false) {
                            $message_lines[] = 'nagimemo_update.php の自己更新に失敗したため、' . $manual_replace_path . ' を作成しました。手動で置き換えてください。';
                        } else {
                            $message_lines[] = 'nagimemo_update.php の自己更新にも退避保存にも失敗しました。';
                            $error = true;
                        }
                    }
                }
            }

            $zip->close();
        } else {
            $message_lines[] = 'ZIPファイルの解凍に失敗しました。';
            $error = true;
        }

        if (file_exists($temp_zip)) {
            @unlink($temp_zip);
        }
    }

    $any_update_available = $skin_needs_update || $updater_needs_update;
    if (!$error && empty($message_lines)) {
        $message_lines[] = '更新対象はありませんでした。';
    } elseif (!$error && !$any_update_available) {
        $message_lines[] = 'すべて最新の状態になりました。';
    }
}

$show_update_modal = $any_update_available && !isset($_POST['update']);
$return_href = build_return_href($request_return_url, $fallback_return_url);
$has_result_message = !empty($message_lines);
$update_headline = build_update_headline($skin_needs_update, $updater_needs_update, $skin_repair_needed);
$update_intro = build_update_intro($skin_needs_update, $updater_needs_update, $updater_info, $skin_repair_needed);
$skin_row_text = $remote_skin_version !== null
    ? (($local_skin_version !== null ? 'v' . $local_skin_version : '不明') . ' → v' . $remote_skin_version)
    : '更新情報を取得できませんでした。';
if ($skin_repair_needed) {
    $skin_row_text = ($local_skin_version !== null ? 'v' . $local_skin_version : '不明')
        . ' のままですが、設置ファイルの参照崩れまたは不足ファイルを検出したため修復更新します。';
}
$updater_row_text = updater_detail_text($updater_info);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NagiMemo Updater</title>
    <link rel="icon" href="https://cdn.jsdelivr.net/npm/@lichiphen/nagimemo-updater/icon.png">
    <link rel="apple-touch-icon" href="https://cdn.jsdelivr.net/npm/@lichiphen/nagimemo-updater/icon.png">
    <style>
        :root {
            --bg-color: #fcfaf2;
            --container-bg: #ffffff;
            --accent-color: #7d6b5d;
            --accent-dark: #645548;
            --text-color: #4a4238;
            --border-color: #e0dbd1;
            --success-color: #e8f4ec;
            --success-text: #527562;
            --warning-color: #fff4f4;
            --warning-text: #a35d5d;
            --muted-text: #85786c;
            --overlay-color: rgba(63, 52, 44, 0.45);
        }
        * {
            box-sizing: border-box;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Hiragino Sans", Meiryo, sans-serif;
            margin: 0;
            min-height: 100vh;
            padding: 24px 16px 40px;
        }
        .page-shell {
            width: min(100%, 760px);
            margin: 0 auto;
        }
        .container {
            background: var(--container-bg);
            padding: 32px 24px;
            border-radius: 18px;
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.07);
            border: 1px solid var(--border-color);
        }
        .icon-area {
            text-align: center;
            margin-bottom: 14px;
        }
        .icon-area img {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
        }
        h1 {
            font-size: clamp(1.4rem, 4vw, 1.8rem);
            margin: 0 0 12px;
            text-align: center;
            letter-spacing: 0.08em;
            color: var(--accent-color);
        }
        .intro {
            text-align: center;
            font-size: 0.95rem;
            line-height: 1.8;
            color: var(--muted-text);
            margin: 0 0 28px;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }
        .status-card {
            background: #fffdf9;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 18px;
        }
        .status-card .label {
            display: block;
            font-size: 0.82rem;
            letter-spacing: 0.08em;
            color: var(--muted-text);
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .status-card strong {
            display: block;
            font-size: 1.35rem;
            margin-bottom: 6px;
        }
        .status-card small {
            display: block;
            color: var(--muted-text);
            line-height: 1.6;
        }
        .result-box,
        .notice-box {
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 18px;
            line-height: 1.75;
            white-space: pre-line;
        }
        .result-box {
            background: var(--success-color);
            color: var(--success-text);
            border: 1px solid #cde3d4;
        }
        .result-box.error {
            background: var(--warning-color);
            color: var(--warning-text);
            border-color: #f0d3d3;
        }
        .notice-box {
            background: #fffdf9;
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }
        .target-list {
            list-style: none;
            padding: 0;
            margin: 0 0 22px;
            display: grid;
            gap: 12px;
        }
        .target-list li {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 16px 18px;
            background: #fffdf9;
        }
        .target-list li strong {
            display: block;
            margin-bottom: 6px;
            font-size: 1rem;
        }
        .target-list li span {
            display: block;
            color: var(--muted-text);
            line-height: 1.6;
            font-size: 0.92rem;
        }
        .badge {
            white-space: nowrap;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            background: #f2ece5;
            color: var(--accent-color);
        }
        .badge.update {
            background: #fff1f1;
            color: #b56262;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 8px;
        }
        .btn {
            appearance: none;
            border: none;
            border-radius: 10px;
            background: var(--accent-color);
            color: #fff;
            padding: 13px 18px;
            font-size: 0.98rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-decoration: none;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.2s;
        }
        .btn:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }
        .btn.secondary {
            background: #b2a394;
        }
        .btn.ghost {
            background: transparent;
            color: var(--accent-color);
            border: 1px solid var(--border-color);
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .footer {
            margin-top: 26px;
            text-align: center;
            font-size: 0.8rem;
            line-height: 1.8;
            color: var(--muted-text);
        }
        .footer a {
            color: inherit;
        }
        .update-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: var(--overlay-color);
            backdrop-filter: blur(5px);
            z-index: 1000;
        }
        .update-modal.is-open {
            display: flex;
        }
        .update-modal-panel {
            width: min(100%, 520px);
            background: var(--container-bg);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 28px 60px rgba(0, 0, 0, 0.18);
            padding: 26px 22px 22px;
        }
        .update-modal-panel h2 {
            margin: 0 0 10px;
            font-size: 1.35rem;
            color: var(--accent-dark);
        }
        .update-modal-panel p {
            margin: 0 0 18px;
            line-height: 1.8;
            color: var(--muted-text);
        }
        .update-modal-list {
            list-style: none;
            padding: 0;
            margin: 0 0 18px;
            display: grid;
            gap: 12px;
        }
        .update-modal-list li {
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 14px 16px;
            background: #fffdf9;
        }
        .update-modal-list strong {
            display: block;
            margin-bottom: 6px;
        }
        .update-modal-list span {
            color: var(--muted-text);
            font-size: 0.92rem;
        }
        .update-modal-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        @media (max-width: 640px) {
            .container {
                padding: 26px 18px;
            }
            .status-grid {
                grid-template-columns: 1fr;
            }
            .target-list li {
                flex-direction: column;
            }
            .badge {
                align-self: flex-start;
            }
            .actions,
            .update-modal-actions {
                flex-direction: column;
            }
            .btn,
            .update-modal-actions .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php if ($show_update_modal): ?>
        <div class="update-modal is-open" id="update-modal" aria-hidden="false">
            <div class="update-modal-panel" role="dialog" aria-modal="true" aria-labelledby="update-modal-title">
                <h2 id="update-modal-title">更新があります</h2>
                <p><?php echo htmlspecialchars($update_headline . ' ' . $update_intro, ENT_QUOTES, 'UTF-8'); ?></p>
                <ul class="update-modal-list">
                    <li>
                        <strong>NagiMemo 一式</strong>
                        <span><?php echo htmlspecialchars($skin_row_text, ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                    <li>
                        <strong>nagimemo_update.php 本体</strong>
                        <span><?php echo htmlspecialchars($updater_row_text, ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                </ul>
                <div class="update-modal-actions">
                    <form method="post">
                        <?php echo render_hidden_return_input($request_return_url); ?>
                        <button type="submit" name="update" value="1" class="btn">今すぐアップデートする</button>
                    </form>
                    <button type="button" class="btn ghost" data-close-update-modal>あとで確認する</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="page-shell">
        <div class="container">
            <div class="icon-area">
                <img src="https://cdn.jsdelivr.net/npm/@lichiphen/nagimemo-updater/icon.png" alt="NagiMemo Updater Icon">
            </div>
            <h1>NagiMemo Updater</h1>
            <p class="intro">GitHub 上の最新版と比較して、NagiMemo 一式（skin-nagimemo / NagiGallery / NagiPicts / skin-nagi_sitemap）と updater 自身の更新状態を確認します。</p>
            <div class="status-grid">
                <div class="status-card">
                    <span class="label">NagiMemo 一式</span>
                    <strong><?php echo htmlspecialchars($local_skin_version !== null ? 'v' . $local_skin_version : '不明', ENT_QUOTES, 'UTF-8'); ?></strong>
                    <small>最新: <?php echo htmlspecialchars($remote_skin_version !== null ? 'v' . $remote_skin_version : '取得失敗', ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
                <div class="status-card">
                    <span class="label">Updater</span>
                    <strong><?php echo htmlspecialchars(updater_status_text($updater_info), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <small><?php echo htmlspecialchars($remote_updater_build !== null ? 'build: ' . $remote_updater_build : ($remote_updater_signature !== null ? '署名: ' . $remote_updater_signature : 'GitHubの更新情報を取得できませんでした。'), ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
            </div>

            <?php if ($has_result_message): ?>
                <div class="result-box<?php echo $error ? ' error' : ''; ?>"><?php echo htmlspecialchars(implode("\n", $message_lines), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif ($any_update_available): ?>
                <div class="notice-box"><?php echo htmlspecialchars($update_headline . ' ' . $update_intro, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php else: ?>
                <div class="notice-box"><?php echo htmlspecialchars($update_intro, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <ul class="target-list">
                <li>
                    <div>
                        <strong>NagiMemo 一式</strong>
                        <span><?php echo htmlspecialchars($skin_row_text, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <span class="badge<?php echo $skin_needs_update ? ' update' : ''; ?>"><?php echo $skin_needs_update ? '更新あり' : '最新'; ?></span>
                </li>
                <li>
                    <div>
                        <strong>nagimemo_update.php 本体</strong>
                        <span><?php echo htmlspecialchars($updater_row_text, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <span class="badge<?php echo $updater_needs_update ? ' update' : ''; ?>"><?php echo htmlspecialchars(updater_status_text($updater_info), ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
            </ul>

            <div class="actions">
                <form method="post">
                    <?php echo render_hidden_return_input($request_return_url); ?>
                    <button type="submit" name="update" value="1" class="btn" <?php echo !$any_update_available ? 'disabled' : ''; ?>>今すぐアップデートする</button>
                </form>
                <a class="btn secondary" href="<?php echo htmlspecialchars($return_href, ENT_QUOTES, 'UTF-8'); ?>">元のページへ戻る</a>
                <a class="btn ghost" href="<?php echo htmlspecialchars($updater_file, ENT_QUOTES, 'UTF-8'); ?>">再読み込み</a>
            </div>

            <?php if ($manual_replace_path !== ''): ?>
                <div class="notice-box" style="margin-top: 18px;">自己更新ができなかったため、<?php echo htmlspecialchars($manual_replace_path, ENT_QUOTES, 'UTF-8'); ?> を作成しました。FTP 等で現在の nagimemo_update.php と置き換えてください。</div>
            <?php endif; ?>

            <div class="footer">
                <a href="https://github.com/Lichiphen/NagiMemo" target="_blank" rel="noopener noreferrer">GitHub Repository</a><br>
                &copy; 2026 Lichiphen | NagiMemo Project
            </div>
        </div>
    </div>

    <script>
    (function() {
        var modal = document.getElementById('update-modal');
        if (!modal) {
            return;
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }

        var closeBtn = document.querySelector('[data-close-update-modal]');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }

        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    })();
    </script>
</body>
</html>
