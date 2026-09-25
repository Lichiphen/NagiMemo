<?php
/**
 * NagiMemo Updater
 * NagiMemo Updater v1.2.3
 * Updater Build: 202609252228
 * GitHubから最新版のNagiMemo一式と nagimemo_update.php を取得・更新するスクリプト
 *
 * 設置場所: てがろぐ(tegalog.cgi)と同じディレクトリ
 */

// ============================================================
// 【ユーザー設定項目】ここを自由に書き換えてください
// ============================================================

/**
 * 1. アップデート専用パスワード（通常は空のままでOK）
 * 空のままにすると、初回アクセス時にブラウザ上でパスワードを設定できます。
 * （初回設定は、てがろぐに管理者でログインしている人だけが行えます）
 * 画面で設定したパスワードは同じフォルダの nagimemo_update_auth.php に暗号化して保存され、
 * 画面から変更できます。ここに合言葉を書いた場合は、画面で設定するまでその合言葉でログインできます。
 */
$update_password = '';

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
$auth_file = __DIR__ . '/nagimemo_update_auth.php';
$remember_cookie = 'nagimemo_updater_remember';
$remember_days = 30;
$min_password_length = 8;
$max_login_failures = 10;
$login_failure_window = 900;
$insecure_default_passwords = array('ねこちゃん');	// 過去の配布版の初期値（公開済みなので無効扱い）
$tegalog_psif_file = __DIR__ . '/psif.cgi';
$tegalog_ini_file = __DIR__ . '/tegalog.ini';
$tegalog_session_cookie = 'fomlid';	// + tegalog.ini の coexistsuffix
$state_file = __DIR__ . '/nagimemo_update_state.php';	// 前回このアップデーターが書き込んだ配布版のハッシュ
// 利用者が編集してよいファイル。編集されていれば上書きせず、新しい配布版を *.new.html として横に置く
$protected_files = array(
    'skin-nagimemo/modules/sidebar.html',
    'skin-nagimemo/modules/footer.html',
    'skin-nagimemo/modules/noindex.html',
);
// 過去に配布したことのある内容のハッシュ（BOM除去・改行LFで正規化した sha1）。これと一致すれば「未編集」とみなす
$known_distributed_hashes = array(
    'skin-nagimemo/modules/sidebar.html' => array('9846ae6149b9348fac576e2427d2180a92843e9c', 'b7fdc4a215e261967175da62113e933c8407230f', 'd36fa41c3d8f0db122c9506da46947cb6c34ff25'),
    'skin-nagimemo/modules/footer.html' => array('486f8fe597141151dfdea9c3de3962503fda7a87', '71cc7975f10203851d56531bb6c28d648b1c3583', '78bde6dedecb83bb59d0fe635ae5f3556136ee7a', 'df29ac7f08f79201d0de5dc01e8f1332160e2d79', 'ffdebb3c98e8864a3745dfec6c66e4561c52f64c'),
    'skin-nagimemo/modules/noindex.html' => array('65e46e5e387364606dbf039ede71fb7c7d0d6363'),
);

// アップデーターの画面・状態APIはブラウザや中継サーバーにキャッシュさせない
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
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

function fetch_remote_data($url, $timeout, $bypass_cache = true)
{
    // raw.githubusercontent.com 等は数分間キャッシュを返すため、クエリを変えて常に最新を取りに行く
    if ($bypass_cache) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'nocache=' . time() . mt_rand(1000, 9999);
    }
    $ctx = stream_context_create(array(
        'http' => array(
            'timeout' => $timeout,
            'header' => "User-Agent: NagiMemo-Updater\r\nCache-Control: no-cache\r\nPragma: no-cache\r\n",
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

// ============================================================
// 利用者が編集してよいファイルの保護
// ============================================================

function nm_normalized_hash($content)
{
    $content = str_replace("\r\n", "\n", (string) $content);
    if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
        $content = substr($content, 3);
    }
    return sha1($content);
}

function nm_state_load($file)
{
    $data = array('distributed' => array());
    if (!is_file($file)) {
        return $data;
    }
    $raw = @file_get_contents($file);
    $pos = $raw === false ? false : strpos($raw, "\n");
    $json = $pos === false ? null : json_decode(substr($raw, $pos + 1), true);
    return is_array($json) ? array_merge($data, $json) : $data;
}

function nm_state_save($file, $data)
{
    $body = "<?php http_response_code(404); exit; ?>\n" . json_encode($data);
    if (@file_put_contents($file, $body, LOCK_EX) === false) {
        return false;
    }
    @chmod($file, 0600);
    return true;
}

// 手元のファイルが「配布版のまま（未編集）」かどうか
function nm_is_unmodified_distribution($path, $local_content, $state, $known_hashes, $local_version, $repo_user, $repo_name)
{
    $local_hash = nm_normalized_hash($local_content);
    // 1. 前回このアップデーターが書き込んだ内容
    if (isset($state['distributed'][$path]) && $state['distributed'][$path] === $local_hash) {
        return true;
    }
    // 2. 過去に配布した内容
    if (isset($known_hashes[$path]) && in_array($local_hash, $known_hashes[$path], true)) {
        return true;
    }
    // 3. 導入中バージョンのタグにある内容（タグの内容は変わらないのでキャッシュ回避は不要）
    if ($local_version !== null && $local_version !== '') {
        $tag_url = "https://raw.githubusercontent.com/{$repo_user}/{$repo_name}/v{$local_version}/" . $path;
        $tag_content = fetch_remote_data($tag_url, 5, false);
        if ($tag_content !== null && nm_normalized_hash($tag_content) === $local_hash) {
            return true;
        }
    }
    return false;
}

function nm_new_copy_path($path)
{
    $dot = strrpos($path, '.');
    return $dot === false ? $path . '.new' : substr($path, 0, $dot) . '.new' . substr($path, $dot);
}

// ============================================================
// 認証（パスワードは nagimemo_update_auth.php にハッシュで保存）
// ============================================================

function nm_random_hex($bytes)
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($bytes));
    }
    return bin2hex(openssl_random_pseudo_bytes($bytes));
}

function nm_is_https()
{
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    return isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
}

function nm_cookie_path()
{
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/';
    $dir = str_replace('\\', '/', dirname($script));
    return rtrim($dir, '/') . '/';
}

function nm_set_cookie($name, $value, $expires)
{
    $parts = array(
        rawurlencode($name) . '=' . rawurlencode($value),
        'Path=' . nm_cookie_path(),
        'Expires=' . gmdate('D, d M Y H:i:s', $expires) . ' GMT',
        'Max-Age=' . max(0, $expires - time()),
        'HttpOnly',
        'SameSite=Lax',
    );
    if (nm_is_https()) {
        $parts[] = 'Secure';
    }
    header('Set-Cookie: ' . implode('; ', $parts), false);
}

function nm_auth_default()
{
    return array(
        'version' => 1,
        'password_hash' => '',
        'updated_at' => 0,
        'tokens' => array(),
        'failures' => array(),
    );
}

function nm_auth_load($file)
{
    if (!is_file($file)) {
        return nm_auth_default();
    }
    $raw = @file_get_contents($file);
    if ($raw === false) {
        return nm_auth_default();
    }
    $pos = strpos($raw, "\n");
    $data = $pos === false ? null : json_decode(substr($raw, $pos + 1), true);
    if (!is_array($data)) {
        return nm_auth_default();
    }
    return array_merge(nm_auth_default(), $data);
}

function nm_auth_save($file, $data)
{
    // 先頭行で即終了させるので、URL で直接開かれても中身は出力されない
    $body = "<?php http_response_code(404); exit; ?>\n" . json_encode($data);
    if (@file_put_contents($file, $body, LOCK_EX) === false) {
        return false;
    }
    @chmod($file, 0600);
    return true;
}

function nm_auth_prune(&$data, $failure_window)
{
    $now = time();
    $tokens = array();
    foreach ($data['tokens'] as $token) {
        if (isset($token['expires']) && (int) $token['expires'] > $now) {
            $tokens[] = $token;
        }
    }
    usort($tokens, function ($a, $b) {
        return (int) $b['created'] - (int) $a['created'];
    });
    $data['tokens'] = array_slice($tokens, 0, 10);

    $failures = array();
    foreach ($data['failures'] as $at) {
        if ((int) $at > $now - $failure_window) {
            $failures[] = (int) $at;
        }
    }
    $data['failures'] = $failures;
}

function nm_find_remember_token($data, $cookie_value)
{
    $pieces = explode(':', (string) $cookie_value, 2);
    if (count($pieces) !== 2) {
        return null;
    }
    foreach ($data['tokens'] as $index => $token) {
        if ($token['id'] === $pieces[0] && hash_equals($token['hash'], hash('sha256', $pieces[1]))) {
            return $index;
        }
    }
    return null;
}

function nm_issue_remember_token(&$data, $cookie_name, $days)
{
    $id = nm_random_hex(8);
    $secret = nm_random_hex(32);
    $expires = time() + $days * 86400;
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 120) : '';
    $data['tokens'][] = array(
        'id' => $id,
        'hash' => hash('sha256', $secret),
        'created' => time(),
        'expires' => $expires,
        'agent' => $agent,
    );
    nm_set_cookie($cookie_name, $id . ':' . $secret, $expires);
    return $expires;
}

// てがろぐに権限Lv.9(管理者)でログイン中かを、てがろぐのセッションファイルから直接確認する
function nm_tegalog_admin_user($psif_file, $ini_file, $cookie_base)
{
    // tegalog.ini: Cookie 名の接尾辞(coexistsuffix) と ユーザ情報(userids: ID<>権限<>名前<>...、複数は <,> 区切り)
    $suffix = '';
    $userids = 'admin<>9<>';	// 未設定なら初期ID admin(Lv.9)
    $ini = @file($ini_file, FILE_IGNORE_NEW_LINES);
    if ($ini !== false) {
        foreach ($ini as $line) {
            if (strpos($line, 'coexistsuffix=') === 0) {
                $suffix = preg_replace('/[^a-zA-Z0-9]/', '', substr($line, 14));
            } elseif (strpos($line, 'userids=') === 0) {
                $userids = substr($line, 8);
            }
        }
    }

    $cookie_name = $cookie_base . $suffix;
    if (empty($_COOKIE[$cookie_name])) {
        return null;
    }
    $session_id = (string) $_COOKIE[$cookie_name];
    $lines = @file($psif_file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return null;
    }

    $user_id = null;
    foreach ($lines as $line) {
        if (strpos($line, 'SESSION=') !== 0) {
            continue;
        }
        $parts = explode(',', substr($line, 8), 3);
        if (count($parts) === 3 && (int) $parts[0] >= time() && hash_equals($parts[1], $session_id)) {
            $user_id = trim($parts[2]);
            break;
        }
    }
    if ($user_id === null || $user_id === '') {
        return null;
    }

    foreach (explode('<,>', $userids) as $entry) {
        $fields = explode('<>', $entry);
        if ($fields[0] === $user_id && isset($fields[1]) && (int) $fields[1] >= 9) {
            return $user_id;
        }
    }
    return null;
}

function nm_csrf_token()
{
    if (empty($_SESSION['nm_csrf'])) {
        $_SESSION['nm_csrf'] = nm_random_hex(16);
    }
    return $_SESSION['nm_csrf'];
}

function nm_csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(nm_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function nm_csrf_valid()
{
    return isset($_POST['csrf'], $_SESSION['nm_csrf']) && hash_equals($_SESSION['nm_csrf'], (string) $_POST['csrf']);
}

function nm_flash($message = null, $is_error = false)
{
    if ($message !== null) {
        $_SESSION['nm_flash'] = array('message' => $message, 'error' => $is_error);
        return null;
    }
    $flash = isset($_SESSION['nm_flash']) ? $_SESSION['nm_flash'] : null;
    unset($_SESSION['nm_flash']);
    return $flash;
}

function nm_redirect_self($updater_file, $return_url)
{
    $location = $updater_file;
    if ($return_url !== '') {
        $location .= '?return_url=' . rawurlencode($return_url);
    }
    header('Location: ' . $location, true, 303);
    exit;
}

function nm_validate_new_password($new1, $new2, $min_length)
{
    if ($new1 !== $new2) {
        return '確認用のパスワードが一致しません。';
    }
    if (function_exists('mb_strlen') ? mb_strlen($new1, 'UTF-8') < $min_length : strlen($new1) < $min_length) {
        return 'パスワードは ' . $min_length . ' 文字以上にしてください。';
    }
    return null;
}

function nm_render_auth_page($view, $vars)
{
    extract($vars);
    $h = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex,nofollow">
        <title><?php echo $view === 'setup' ? 'Setup' : 'Login'; ?> - NagiMemo Updater</title>
        <style>
            :root {
                --bg-color: #fcfaf2;
                --container-bg: #ffffff;
                --accent-color: #7d6b5d;
                --text-color: #4a4238;
                --muted-text: #85786c;
                --border-color: #e0dbd1;
                --warning-color: #a35d5d;
            }
            * { box-sizing: border-box; }
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
            }
            .login-box {
                background: var(--container-bg);
                padding: 36px 32px;
                border-radius: 14px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                border: 1px solid var(--border-color);
                width: min(100%, 380px);
                text-align: center;
            }
            h1 {
                color: var(--accent-color);
                font-size: 1.3rem;
                margin: 0 0 12px;
                letter-spacing: 0.08em;
            }
            p {
                margin: 0 0 16px;
                font-size: 0.92rem;
                line-height: 1.75;
            }
            .note {
                color: var(--muted-text);
                font-size: 0.85rem;
                text-align: left;
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
                margin-bottom: 12px;
                background: #fffdf9;
                font-size: 1rem;
            }
            .remember {
                display: flex;
                align-items: center;
                gap: 8px;
                justify-content: flex-start;
                margin: 2px 0 16px;
                font-size: 0.88rem;
                cursor: pointer;
                text-align: left;
            }
            .remember input {
                width: 16px;
                height: 16px;
                accent-color: var(--accent-color);
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
            }
            button:hover,
            .back-link:hover {
                opacity: 0.92;
            }
            .back-link {
                margin-top: 10px;
                background: #b2a394;
            }
            code {
                background: #f5f0e8;
                padding: 1px 5px;
                border-radius: 4px;
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>NagiMemo Updater</h1>
            <?php if ($error_message !== ''): ?>
                <p class="error"><?php echo $h($error_message); ?></p>
            <?php endif; ?>

            <?php if ($view === 'login'): ?>
                <p>アップデート画面を開くにはパスワードが必要です。</p>
                <form method="post">
                    <?php echo nm_csrf_field(); ?>
                    <?php echo render_hidden_return_input($return_url); ?>
                    <input type="hidden" name="auth_action" value="login">
                    <input type="password" name="password" placeholder="Password" autocomplete="current-password" required autofocus>
                    <label class="remember"><input type="checkbox" name="remember" value="1">このブラウザにログイン情報を保存する（<?php echo (int) $remember_days; ?>日間）</label>
                    <button type="submit">ログイン</button>
                </form>
            <?php elseif ($view === 'setup' && $tegalog_admin !== null): ?>
                <p>アップデーター用のパスワードを設定してください。<br>次回からはこのパスワードでログインします。</p>
                <form method="post">
                    <?php echo nm_csrf_field(); ?>
                    <?php echo render_hidden_return_input($return_url); ?>
                    <input type="hidden" name="auth_action" value="setup">
                    <input type="password" name="new_password" placeholder="新しいパスワード（<?php echo (int) $min_length; ?>文字以上）" autocomplete="new-password" minlength="<?php echo (int) $min_length; ?>" required autofocus>
                    <input type="password" name="new_password_confirm" placeholder="もう一度入力" autocomplete="new-password" minlength="<?php echo (int) $min_length; ?>" required>
                    <label class="remember"><input type="checkbox" name="remember" value="1" checked>このブラウザにログイン情報を保存する（<?php echo (int) $remember_days; ?>日間）</label>
                    <button type="submit">パスワードを設定する</button>
                </form>
                <p class="note">てがろぐに管理者「<?php echo $h($tegalog_admin); ?>」でログインしているため設定できます。</p>
            <?php else: ?>
                <p>アップデーターのパスワードがまだ設定されていません。</p>
                <p class="note">安全のため、最初の設定は<strong>てがろぐに管理者（権限Lv.9）でログインした状態</strong>でのみ行えます。てがろぐの管理画面にログインしてから、もう一度このページを開いてください。</p>
                <a class="back-link" style="background: var(--accent-color);" href="<?php echo $h($tegalog_admin_url); ?>">てがろぐにログインする</a>
                <p class="note" style="margin-top: 16px;">画面から設定できない場合は、<code>nagimemo_update.php</code> をテキストエディタで開き、<code>$update_password</code> に合言葉を書いてアップロードしても使えます。</p>
            <?php endif; ?>
            <a class="back-link" href="<?php echo $h($back_href); ?>">戻る</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * 認証を行う。ログイン済みなら状態の配列を返し、そうでなければログイン/初期設定画面を出して終了する。
 */
function nm_require_auth($config)
{
    extract($config);

    if (function_exists('session_status') ? session_status() !== PHP_SESSION_ACTIVE : session_id() === '') {
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.cookie_samesite', 'Lax');
        if (nm_is_https()) {
            @ini_set('session.cookie_secure', '1');
        }
        session_name('NAGIMEMO_UPDATER');
        session_start();
    }

    $auth = nm_auth_load($auth_file);
    nm_auth_prune($auth, $failure_window);

    $has_hash = $auth['password_hash'] !== '';
    $legacy_ok = !$has_hash && is_string($legacy_password) && $legacy_password !== ''
        && !in_array($legacy_password, $insecure_passwords, true);
    $source = $has_hash ? 'file' : ($legacy_ok ? 'legacy' : 'none');
    $authed = !empty($_SESSION['nagimemo_auth']);
    $remember_index = null;

    // 保存済みログイン（クッキー）でのログイン
    if (isset($_COOKIE[$remember_cookie])) {
        $remember_index = nm_find_remember_token($auth, $_COOKIE[$remember_cookie]);
        if ($remember_index === null) {
            nm_set_cookie($remember_cookie, '', time() - 3600);
        } elseif (!$authed && $source !== 'none') {
            session_regenerate_id(true);
            $_SESSION['nagimemo_auth'] = true;
            $authed = true;
        }
    }
    if ($source === 'none') {
        // パスワード未設定（または公開済みの初期値のまま）ならログインを無効にして初期設定へ
        $authed = false;
        unset($_SESSION['nagimemo_auth']);
    }

    $error_message = '';
    $action = isset($_POST['auth_action']) ? (string) $_POST['auth_action'] : '';

    if ($action !== '' && !nm_csrf_valid()) {
        $error_message = '画面の有効期限が切れました。もう一度お試しください。';
        $action = '';
    }

    if ($action === 'login' && !$authed && $source !== 'none') {
        if (count($auth['failures']) >= $max_failures) {
            $error_message = 'ログインの失敗が続いたため、しばらく時間をおいてからお試しください。';
        } else {
            $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
            $ok = $source === 'file'
                ? password_verify($password, $auth['password_hash'])
                : hash_equals($legacy_password, $password);
            if ($ok) {
                session_regenerate_id(true);
                $_SESSION['nagimemo_auth'] = true;
                $auth['failures'] = array();
                if (!empty($_POST['remember'])) {
                    nm_issue_remember_token($auth, $remember_cookie, $remember_days);
                }
                nm_auth_save($auth_file, $auth);
                nm_redirect_self($updater_file, $return_url);
            }
            $auth['failures'][] = time();
            nm_auth_save($auth_file, $auth);
            sleep(1);
            $error_message = 'パスワードが一致しません。';
        }
    }

    $tegalog_admin = null;
    if ($source === 'none') {
        $tegalog_admin = nm_tegalog_admin_user($tegalog_psif_file, $tegalog_ini_file, $tegalog_cookie);
    }

    if ($action === 'setup' && $source === 'none' && $tegalog_admin !== null) {
        $new1 = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';
        $new2 = isset($_POST['new_password_confirm']) ? (string) $_POST['new_password_confirm'] : '';
        $invalid = nm_validate_new_password($new1, $new2, $min_length);
        if ($invalid !== null) {
            $error_message = $invalid;
        } else {
            $auth['password_hash'] = password_hash($new1, PASSWORD_DEFAULT);
            $auth['updated_at'] = time();
            $auth['tokens'] = array();
            $auth['failures'] = array();
            if (!empty($_POST['remember'])) {
                nm_issue_remember_token($auth, $remember_cookie, $remember_days);
            }
            if (nm_auth_save($auth_file, $auth)) {
                session_regenerate_id(true);
                $_SESSION['nagimemo_auth'] = true;
                nm_flash('パスワードを設定しました。');
                nm_redirect_self($updater_file, $return_url);
            }
            $error_message = basename($auth_file) . ' を保存できませんでした。フォルダの書き込み権限を確認してください。';
        }
    }

    if (!$authed) {
        nm_render_auth_page($source === 'none' ? 'setup' : 'login', array(
            'error_message' => $error_message,
            'return_url' => $return_url,
            'back_href' => $back_href,
            'remember_days' => $remember_days,
            'min_length' => $min_length,
            'tegalog_admin' => $tegalog_admin,
            'tegalog_admin_url' => $tegalog_admin_url,
        ));
    }

    // ---- ここから先はログイン済み：アカウント操作 ----
    if ($action === 'logout') {
        if ($remember_index !== null) {
            array_splice($auth['tokens'], $remember_index, 1);
            nm_auth_save($auth_file, $auth);
        }
        nm_set_cookie($remember_cookie, '', time() - 3600);
        $_SESSION = array();
        session_regenerate_id(true);
        nm_redirect_self($updater_file, $return_url);
    }

    if ($action === 'forget_all') {
        $auth['tokens'] = array();
        nm_auth_save($auth_file, $auth);
        nm_set_cookie($remember_cookie, '', time() - 3600);
        nm_flash('保存されていたログイン情報をすべて解除しました。');
        nm_redirect_self($updater_file, $return_url);
    }

    if ($action === 'change_password') {
        $current = isset($_POST['current_password']) ? (string) $_POST['current_password'] : '';
        $current_ok = $source === 'file'
            ? password_verify($current, $auth['password_hash'])
            : hash_equals((string) $legacy_password, $current);
        $new1 = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';
        $new2 = isset($_POST['new_password_confirm']) ? (string) $_POST['new_password_confirm'] : '';
        $invalid = $current_ok ? nm_validate_new_password($new1, $new2, $min_length) : '現在のパスワードが違います。';
        if ($invalid !== null) {
            nm_flash($invalid, true);
        } else {
            $keep_remember = $remember_index !== null;
            $auth['password_hash'] = password_hash($new1, PASSWORD_DEFAULT);
            $auth['updated_at'] = time();
            $auth['tokens'] = array();
            if ($keep_remember) {
                nm_issue_remember_token($auth, $remember_cookie, $remember_days);
            }
            if (nm_auth_save($auth_file, $auth)) {
                nm_flash('パスワードを変更しました。ほかのブラウザに保存されていたログイン情報は解除されています。');
            } else {
                nm_flash(basename($auth_file) . ' を保存できませんでした。フォルダの書き込み権限を確認してください。', true);
            }
        }
        nm_redirect_self($updater_file, $return_url);
    }

    $remember_expires = null;
    if ($remember_index !== null && isset($auth['tokens'][$remember_index])) {
        $remember_expires = (int) $auth['tokens'][$remember_index]['expires'];
    }

    return array(
        'source' => $source,
        'remember_expires' => $remember_expires,
        'remembered_count' => count($auth['tokens']),
        'flash' => nm_flash(),
    );
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
        return 'NagiMemo 一式の管理範囲に差分があり、nagimemo_update.php 本体の更新も検出しました。';
    }

    if ($skin_repair_needed) {
        return 'NagiMemo 一式の管理範囲に差分を検出しました。';
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
        return 'cover.html の管理範囲や不足ファイルを修復しつつ、nagimemo_update.php 本体も更新できます。';
    }

    if ($skin_repair_needed) {
        return 'cover.html の管理範囲または不足ファイルに差分を検出したため、skin-nagimemo / NagiGallery / NagiPicts / skin-nagi_sitemap を修復更新します。';
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

function build_cover_file_list()
{
    return array(
        'skin-nagimemo/skin-cover.html',
        'NagiGallery/skin-cover.html',
        'NagiPicts/skin-cover.html',
        'skin-nagi_sitemap/skin-cover.html',
    );
}

function build_cover_custom_block_markers()
{
    return array(
        'head' => array(
            'start' => '[[!-- NAGIMEMO:CUSTOM-HEAD:START --]]',
            'end' => '[[!-- NAGIMEMO:CUSTOM-HEAD:END --]]',
        ),
        'foot' => array(
            'start' => '[[!-- NAGIMEMO:CUSTOM-FOOT:START --]]',
            'end' => '[[!-- NAGIMEMO:CUSTOM-FOOT:END --]]',
        ),
    );
}

function normalize_cover_managed_content($content, $markers)
{
    if (!is_string($content) || $content === '') {
        return null;
    }

    $normalized = normalize_newlines($content);

    foreach ($markers as $block_name => $marker) {
        $block = extract_settings_block($normalized, $marker['start'], $marker['end']);
        if ($block === null) {
            return null;
        }

        $normalized = replace_settings_block(
            $normalized,
            "\n__NAGIMEMO_" . strtoupper($block_name) . "_BLOCK__\n",
            $marker['start'],
            $marker['end']
        );
    }

    return trim($normalized);
}

function merge_cover_with_local_custom_blocks($remote_content, $local_content, $markers)
{
    if (!is_string($remote_content) || $remote_content === '') {
        return $remote_content;
    }

    $merged = normalize_newlines($remote_content);
    if (!is_string($local_content) || $local_content === '') {
        return $merged;
    }

    $local_normalized = normalize_newlines($local_content);

    foreach ($markers as $marker) {
        $remote_block = extract_settings_block($merged, $marker['start'], $marker['end']);
        if ($remote_block === null) {
            continue;
        }

        $local_block = extract_settings_block($local_normalized, $marker['start'], $marker['end']);
        if ($local_block === null) {
            continue;
        }

        $merged = replace_settings_block($merged, $local_block, $marker['start'], $marker['end']);
    }

    return $merged;
}

function build_remote_cover_contents($repo_user, $repo_name, $branch, $cover_files)
{
    $contents = array();

    foreach ($cover_files as $cover_file) {
        $cover_url = "https://raw.githubusercontent.com/{$repo_user}/{$repo_name}/{$branch}/" . str_replace('\\', '/', $cover_file);
        $contents[$cover_file] = fetch_remote_data($cover_url, 5);
    }

    return $contents;
}

function detect_skin_health_issues($cover_files, $remote_cover_contents)
{
    $issues = array();
    $required_files = array(
        'skin-nagimemo/shared-heatmap.css',
        'skin-nagimemo/heatmap-labels.js',
        'skin-nagimemo/updater-notice.js',
        'skin-nagimemo/modules/updater-notice-modal.html',
    );
    $cover_markers = build_cover_custom_block_markers();

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

        $normalized_local = normalize_cover_managed_content($content, $cover_markers);
        if ($normalized_local === null) {
            $issues[] = $cover_file . ' にアップデート保持用コメントが見つかりません。';
            continue;
        }

        $remote_cover_content = isset($remote_cover_contents[$cover_file]) ? $remote_cover_contents[$cover_file] : null;
        if (!is_string($remote_cover_content) || $remote_cover_content === '') {
            continue;
        }

        $normalized_remote = normalize_cover_managed_content($remote_cover_content, $cover_markers);
        if ($normalized_remote === null) {
            $issues[] = $cover_file . ' の GitHub 配布版にアップデート保持用コメントが見つかりません。';
            continue;
        }

        if ($normalized_local !== $normalized_remote) {
            $issues[] = $cover_file . ' の管理範囲が現在の配布版と一致しません。';
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

$auth_state = null;
if (!$status_mode) {
    $auth_state = nm_require_auth(array(
        'auth_file' => $auth_file,
        'legacy_password' => $update_password,
        'insecure_passwords' => $insecure_default_passwords,
        'remember_cookie' => $remember_cookie,
        'remember_days' => $remember_days,
        'min_length' => $min_password_length,
        'max_failures' => $max_login_failures,
        'failure_window' => $login_failure_window,
        'tegalog_psif_file' => $tegalog_psif_file,
        'tegalog_ini_file' => $tegalog_ini_file,
        'tegalog_cookie' => $tegalog_session_cookie,
        'tegalog_admin_url' => $fallback_return_url . '?mode=admin',
        'updater_file' => $updater_file,
        'return_url' => $request_return_url,
        'back_href' => build_return_href($request_return_url, $fallback_return_url),
    ));
}

$remote_version_url = "https://raw.githubusercontent.com/{$repo_user}/{$repo_name}/{$branch}/{$version_file}";
$remote_updater_url = "https://raw.githubusercontent.com/{$repo_user}/{$repo_name}/{$branch}/{$updater_entry}";
$zip_url = "https://github.com/{$repo_user}/{$repo_name}/archive/refs/heads/{$branch}.zip";
$cover_files = build_cover_file_list();
$cover_custom_markers = build_cover_custom_block_markers();

$local_skin_version = get_local_version($version_file);
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
$remote_skin_content = fetch_remote_data($remote_version_url, 5);
$remote_skin_version = get_version_from_content($remote_skin_content);
$remote_cover_contents = build_remote_cover_contents($repo_user, $repo_name, $branch, $cover_files);
$skin_health_issues = detect_skin_health_issues($cover_files, $remote_cover_contents);
$skin_version_update = $local_skin_version && $remote_skin_version && version_compare($local_skin_version, $remote_skin_version, '<');
$skin_repair_needed = !$skin_version_update && !empty($skin_health_issues);
$skin_needs_update = $skin_version_update || $skin_repair_needed;

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


$message_lines = array();
$error = false;
$manual_replace_path = '';
$updated_skin_files = 0;
$updated_package_counts = array();

if (isset($_POST['update']) && $any_update_available && nm_csrf_valid()) {
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
                $update_state = nm_state_load($state_file);
                $kept_custom_files = array();
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

                    $local_file_content = null;
                    if (file_exists($relative_path)) {
                        $local_file_content = @file_get_contents($relative_path);
                    }

                    if (in_array($relative_path, $protected_files, true) && $local_file_content !== null && $local_file_content !== false) {
                        $remote_hash = nm_normalized_hash($remote_file_content);
                        if (nm_normalized_hash($local_file_content) === $remote_hash) {
                            $update_state['distributed'][$relative_path] = $remote_hash;
                            continue;
                        }
                        if (!nm_is_unmodified_distribution($relative_path, $local_file_content, $update_state, $known_distributed_hashes, $local_skin_version, $repo_user, $repo_name)) {
                            // 利用者が編集したファイルは残し、新しい配布版を横に置く
                            $new_copy = nm_new_copy_path($relative_path);
                            if (@file_put_contents($new_copy, $remote_file_content, LOCK_EX) !== false) {
                                $kept_custom_files[] = $relative_path . '（新しい配布版: ' . basename($new_copy) . '）';
                            } else {
                                $kept_custom_files[] = $relative_path;
                            }
                            continue;
                        }
                    }

                    if (in_array($relative_path, $cover_files, true)) {
                        $remote_file_content = merge_cover_with_local_custom_blocks(
                            $remote_file_content,
                            $local_file_content,
                            $cover_custom_markers
                        );
                    }

                    if ($local_file_content !== false && $local_file_content !== null && $local_file_content === $remote_file_content) {
                        continue;
                    }

                    if (@file_put_contents($relative_path, $remote_file_content, LOCK_EX) !== false) {
                        if (in_array($relative_path, $protected_files, true)) {
                            $update_state['distributed'][$relative_path] = nm_normalized_hash($remote_file_content);
                        }
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

                nm_state_save($state_file, $update_state);
                if (!empty($kept_custom_files)) {
                    $message_lines[] = '次のファイルは編集されているため上書きしませんでした: ' . implode('、', $kept_custom_files);
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
        . ' のままですが、cover.html の管理範囲または不足ファイルに差分を検出したため修復更新します。';
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
        .account-box {
            margin-top: 22px;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            background: #fffdf9;
        }
        .account-box summary {
            cursor: pointer;
            padding: 14px 18px;
            font-weight: 700;
            color: var(--accent-color);
            list-style: none;
        }
        .account-box summary::-webkit-details-marker {
            display: none;
        }
        .account-box summary::after {
            content: "＋";
            float: right;
            color: var(--muted-text);
        }
        .account-box[open] summary::after {
            content: "－";
        }
        .account-body {
            padding: 0 18px 18px;
            display: grid;
            gap: 16px;
        }
        .account-body p {
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.7;
            color: var(--muted-text);
        }
        .account-body form {
            display: grid;
            gap: 10px;
        }
        .account-body input[type="password"] {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: #fff;
            font-size: 0.95rem;
        }
        .account-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .account-actions form {
            display: block;
        }
        .btn.small {
            padding: 9px 14px;
            font-size: 0.88rem;
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
                        <?php echo nm_csrf_field(); ?>
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

            <?php if (!empty($auth_state['flash'])): ?>
                <div class="result-box<?php echo $auth_state['flash']['error'] ? ' error' : ''; ?>"><?php echo htmlspecialchars($auth_state['flash']['message'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($auth_state['source'] === 'legacy'): ?>
                <div class="notice-box">現在は nagimemo_update.php に書かれた合言葉でログインしています。下の「アカウント設定」で新しいパスワードを設定すると、以後は画面から変更できるようになります。</div>
            <?php endif; ?>

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
                    <?php echo nm_csrf_field(); ?>
                    <?php echo render_hidden_return_input($request_return_url); ?>
                    <button type="submit" name="update" value="1" class="btn" <?php echo !$any_update_available ? 'disabled' : ''; ?>>今すぐアップデートする</button>
                </form>
                <a class="btn secondary" href="<?php echo htmlspecialchars($return_href, ENT_QUOTES, 'UTF-8'); ?>">元のページへ戻る</a>
                <a class="btn ghost" href="<?php echo htmlspecialchars($updater_file, ENT_QUOTES, 'UTF-8'); ?>">再読み込み</a>
            </div>

            <details class="account-box"<?php echo !empty($auth_state['flash']['error']) ? ' open' : ''; ?>>
                <summary>アカウント設定</summary>
                <div class="account-body">
                    <p>
                        <?php if ($auth_state['remember_expires'] !== null): ?>
                            このブラウザはログイン情報を保存しています（<?php echo htmlspecialchars(date('Y/m/d', $auth_state['remember_expires']), ENT_QUOTES, 'UTF-8'); ?> まで有効）。
                        <?php else: ?>
                            このブラウザはログイン情報を保存していません（ブラウザを閉じるとログアウトされます）。
                        <?php endif; ?>
                    </p>
                    <form method="post">
                        <?php echo nm_csrf_field(); ?>
                        <?php echo render_hidden_return_input($request_return_url); ?>
                        <input type="hidden" name="auth_action" value="change_password">
                        <input type="password" name="current_password" placeholder="現在のパスワード" autocomplete="current-password" required>
                        <input type="password" name="new_password" placeholder="新しいパスワード（<?php echo (int) $min_password_length; ?>文字以上）" autocomplete="new-password" minlength="<?php echo (int) $min_password_length; ?>" required>
                        <input type="password" name="new_password_confirm" placeholder="新しいパスワード（確認）" autocomplete="new-password" minlength="<?php echo (int) $min_password_length; ?>" required>
                        <div><button type="submit" class="btn small">パスワードを変更する</button></div>
                    </form>
                    <div class="account-actions">
                        <form method="post">
                            <?php echo nm_csrf_field(); ?>
                            <?php echo render_hidden_return_input($request_return_url); ?>
                            <input type="hidden" name="auth_action" value="logout">
                            <button type="submit" class="btn small secondary">ログアウト</button>
                        </form>
                        <?php if ($auth_state['remembered_count'] > 0): ?>
                            <form method="post">
                                <?php echo nm_csrf_field(); ?>
                                <?php echo render_hidden_return_input($request_return_url); ?>
                                <input type="hidden" name="auth_action" value="forget_all">
                                <button type="submit" class="btn small ghost">保存したログインをすべて解除（<?php echo (int) $auth_state['remembered_count']; ?>件）</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </details>

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
