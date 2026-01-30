<?php
/**
 * NagiMemo Updater 
 * GitHubから最新のNagiMemoスキンを取得・更新するスクリプト
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
 * 例: $allowed_ips = ['123.456.78.9'];
 * 空配列 [] のままであれば、IP制限は行われません。
 */
$allowed_ips = []; 

// ============================================================
// 【システム設定】ここから下は通常触る必要はありません
// ============================================================

$repo_user = 'Lichiphen';
$repo_name = 'NagiMemo';
$branch    = 'main';
$skin_dir  = 'skin-nagimemo'; 
$version_file = $skin_dir . '/modules/copyright.html';

// --- セキュリティチェック処理 ---
session_start();

// IPアドレスのチェック
if (!empty($allowed_ips) && !in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    http_response_code(403);
    die('Access Denied: Your IP address is not allowed.');
}

// パスワードのチェック
if (!empty($update_password)) {
    if (isset($_POST['password']) && $_POST['password'] === $update_password) {
        $_SESSION['nagimemo_auth'] = true;
    }

    if (!isset($_SESSION['nagimemo_auth'])) {
        // ログイン画面の表示（簡易版UI）
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Authentication - NagiMemo Updater</title>
            <style>
                body { background-color: #fcfaf2; color: #4a4238; font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .login-box { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e0dbd1; width: 300px; text-align: center; }
                h2 { color: #7d6b5d; font-size: 1.2rem; margin-bottom: 20px; letter-spacing: 0.1em; }
                input[type="password"] { width: 100%; padding: 12px; border: 1px solid #e0dbd1; border-radius: 4px; margin-bottom: 20px; box-sizing: border-box; background: #fcfaf2; }
                button { width: 100%; padding: 12px; background: #7d6b5d; color: #fff; border: none; border-radius: 4px; cursor: pointer; transition: opacity 0.2s; font-size: 1rem; }
                button:hover { opacity: 0.9; }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2>Authentication</h2>
                <form method="post">
                    <input type="password" name="password" placeholder="Password" required autofocus>
                    <button type="submit">Login</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
// --- セキュリティチェック終了 ---

// --- バージョン取得関数 ---
function get_local_version($file_path) {
    if (!file_exists($file_path)) return null;
    $content = file_get_contents($file_path);
    if (preg_match('/NagiMemo v([\d\.]+)/', $content, $matches)) {
        return $matches[1];
    }
    return null;
}

function get_remote_version($url) {
    $ctx = stream_context_create(['http' => ['timeout' => 5, 'header' => "User-Agent: PHP\r\n"]]);
    $content = @file_get_contents($url, false, $ctx);
    if ($content === false) return null;
    if (preg_match('/NagiMemo v([\d\.]+)/', $content, $matches)) {
        return $matches[1];
    }
    return null;
}

// --- メイン処理 ---
$local_ver  = get_local_version($version_file);
$remote_url = "https://raw.githubusercontent.com/{$repo_user}/{$repo_name}/{$branch}/{$version_file}";
$remote_ver = get_remote_version($remote_url);

$update_available = false;
if ($local_ver && $remote_ver && version_compare($local_ver, $remote_ver, '<')) {
    $update_available = true;
}

$message = "";
$error = false;

// --- アップデート実行 ---
if (isset($_POST['update']) && $update_available) {
    $zip_url = "https://github.com/{$repo_user}/{$repo_name}/archive/refs/heads/{$branch}.zip";
    $temp_zip = 'temp_update.zip';
    
    // 1. ダウンロード
    $data = @file_get_contents($zip_url);
    if ($data === false) {
        $message = "GitHubからのダウンロードに失敗しました。";
        $error = true;
    } else {
        file_put_contents($temp_zip, $data);
        
        $zip = new ZipArchive;
        if ($zip->open($temp_zip) === TRUE) {
            // GitHubのZIPは リポジトリ名-ブランチ名/ という階層が含まれる
            $extract_root = "{$repo_name}-{$branch}/";
            
            // skin-nagimemo フォルダのみを更新する
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                
                // skin-nagimemo ディレクトリ内のファイルのみ対象
                if (strpos($filename, $extract_root . $skin_dir . '/') === 0) {
                    $relative_path = substr($filename, strlen($extract_root));
                    
                    if (substr($filename, -1) == '/') {
                        if (!is_dir($relative_path)) mkdir($relative_path, 0755, true);
                    } else {
                        $dir = dirname($relative_path);
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        copy("zip://".$temp_zip."#".$filename, $relative_path);
                    }
                }
            }
            $zip->close();
            unlink($temp_zip);
            $message = "アップデートが完了しました！バージョン {$remote_ver} に更新されました。";
            $local_ver = $remote_ver; // 表示用
            $update_available = false;
        } else {
            $message = "ZIPファイルの解凍に失敗しました。";
            $error = true;
        }
    }
}
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
            --text-color: #4a4238;
            --border-color: #e0dbd1;
            --success-color: #8da399;
            --warning-color: #d1a3a3;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Hiragino Sans", Meiryo, sans-serif;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: var(--container-bg);
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            max-width: 500px;
            width: 90%;
            border: 1px solid var(--border-color);
        }
        h1 {
            font-size: 1.5rem;
            margin: 0 0 30px 0;
            text-align: center;
            letter-spacing: 0.1em;
            color: var(--accent-color);
            border-bottom: 2px solid var(--bg-color);
            padding-bottom: 15px;
        }
        .icon-area {
            text-align: center;
            margin-bottom: 15px;
        }
        .icon-area img {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }
        .version-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .version-box {
            text-align: center;
            padding: 15px;
            background: var(--bg-color);
            border-radius: 6px;
        }
        .version-label {
            font-size: 0.8rem;
            opacity: 0.7;
            margin-bottom: 5px;
            display: block;
        }
        .version-num {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .status-msg {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .status-msg.update {
            background-color: #fff4f4;
            color: #a35d5d;
            border: 1px solid #f2dfdf;
        }
        .status-msg.latest {
            background-color: #f4fff9;
            color: #5d8a7a;
            border: 1px solid #dfefeb;
        }
        .status-msg.result {
            background-color: var(--accent-color);
            color: white;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 6px;
            background-color: var(--accent-color);
            color: white;
            font-size: 1rem;
            cursor: pointer;
            transition: opacity 0.2s;
            text-decoration: none;
            text-align: center;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .footer {
            margin-top: 30px;
            font-size: 0.75rem;
            text-align: center;
            opacity: 0.5;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="icon-area">
        <img src="https://cdn.jsdelivr.net/npm/@lichiphen/nagimemo-updater/icon.png" alt="Icon">
    </div>
    <h1>NagiMemo Updater</h1>

    <?php if ($message): ?>
        <div class="status-msg result" style="<?= $error ? 'background: var(--warning-color);' : '' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="version-info">
        <div class="version-box">
            <span class="version-label">現在のバージョン</span>
            <span class="version-num"><?= $local_ver ?: '不明' ?></span>
        </div>
        <div class="version-box">
            <span class="version-label">最新のバージョン</span>
            <span class="version-num"><?= $remote_ver ?: '取得失敗' ?></span>
        </div>
    </div>

    <?php if ($update_available): ?>
        <div class="status-msg update">
            最新バージョンがあります！<br>
            アップデートして新機能や修正を反映しますか？
        </div>
        <form method="post">
            <button type="submit" name="update" class="btn">今すぐアップデートする</button>
        </form>
    <?php elseif ($remote_ver && $local_ver === $remote_ver): ?>
        <div class="status-msg latest">
            お使いのスキンは最新バージョンです。
        </div>
        <button class="btn" disabled>アップデート不要</button>
    <?php else: ?>
        <div class="status-msg" style="background:#eee;">
            情報の取得に失敗したか、スキンの構成が正しくありません。
        </div>
    <?php endif; ?>

    <div class="footer">
        <a href="https://github.com/Lichiphen/NagiMemo" target="_blank" style="color: inherit; text-decoration: none;">GitHub Repository</a><br>
        &copy; 2026 Lichiphen | NagiMemo Project
    </div>
</div>

</body>
</html>
