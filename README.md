# NagiMemo

書くのが楽しくなる、てがろぐのスキンファイル群です。（てがろぐ Ver 4.9.0 対応）

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://github.com/Lichiphen/NagiMemo/blob/main/LICENSE)
[![GitHub](https://img.shields.io/badge/GitHub-NagiMemo-181717?logo=github)](https://github.com/Lichiphen/NagiMemo/)
[![GitLab](https://img.shields.io/badge/GitLab-NagiMemo-FC6D26?logo=gitlab)](https://gitlab.com/Lichiphen/NagiMemo)

現在ベータ版です。てがろぐはアップデートされていくCMSですので、スキン自体の完成は目指していないです。自分が使いやすいようにカスタマイズしたものですので、気ままに更新していきます。

- **v1.2.3**: X シェアボタンのスマホでの動きを改善
  - iPhone の X アプリ内から押したときは、シェア文をコピーして投稿画面への貼り付けを案内するように（X アプリ内ブラウザでは Web 版のログインを求められたり、画面がループしたりするため）
  - Android の Chrome など（https）では共有シートから X に渡すように（X の投稿画面の 1 行目に空白行が入るのを防ぐ）
  - PC、iPhone の Safari、Android の X アプリ内からの動きは今までどおり
- **v1.2.2**: 編集したサイドバー・フッター・検索避けを Updater が上書きしないように
  - `modules/sidebar.html` `footer.html` `noindex.html` は編集済みなら残し、新しい配布版を `〜.new.html` として横に置く
  - 更新通知は「あとで」・枠外クリック・Esc のどれで閉じてもその日は再表示しない（確認の通信も省略）
  - Updater が GitHub やブラウザのキャッシュで古い情報を表示することがある問題を修正
- **v1.2.1**: NagiMemo Updater のパスワードを画面から設定・変更できるように（ファイル編集不要）
  - 初回設定はてがろぐに管理者でログインしている人だけが可能、パスワードはハッシュ化して保存
  - 「このブラウザにログイン情報を保存する」で30日間パスワード入力を省略
  - ログイン試行回数の制限・CSRF対策を追加、配布版の初期パスワードを廃止
- **v1.2.0**: 内部のリファクタリングとてがろぐ 4.9.0 への正式対応
  - SHARE / COPY ボタンを小さなピル型に変更（コピー完了はボタン上に表示）
  - 投稿単独ページのタイトルに本文1行目を表示（`[[OGP:TITLE:CONTENT]]`）
  - 編集ボタン・ログイン時用スクリプトを `[[IF(loggedin)]]` で出し分け
  - Esc キーでメニュー・投稿欄・各モーダルを閉じられるように
  - X シェアの端末判定、note 等の埋め込みを含む投稿のシェア文、http 環境でのコードコピーの不具合を修正
- **v1.1.9**: てがろぐ4.9.0への仮対応（note, コミック, Voicy, Steam埋め込みの表示最適化、メタディスクリプション出力の追加など）

> [!IMPORTANT]
> **v1.2.0 以降は てがろぐ Ver 4.9.0 以降が必要です。**  
> 4.9.0 で追加された記法を使っているため、それより前のバージョンでは投稿単独ページのタイトルに記法がそのまま表示されます。（4.9.0 には重要な脆弱性の修正も含まれているので、てがろぐ本体の更新をおすすめします。）

> [!NOTE]
> NagiMemo は「てがろぐ用の見た目一式」です。  
> プログラミングに慣れていなくても導入できるように、**Release ZIP** と **NagiMemo Updater** の2つの導入ルートを用意しています。

## 🚀 デモ・設定方法
詳しい表示設定や使い方は、以下のデモサイトをご覧ください。

[**NagiMemo デモサイト**](https://notebook.lichiphen.com/nagimemo/)

> [!TIP]
> 「まず見た目を確認したい」という場合は、最初にデモサイトを見るのがいちばん分かりやすいです。  
> 導入前に、自分の用途に合うか確認できます。

---

## 📦 まずはどれを使えばいい？

NagiMemo の入手方法は、主に次の3つです。

### 1. いちばん簡単: GitHub の Release ZIP を使う

ダウンロードはこちら▶ **[GitHub Releases](https://github.com/Lichiphen/NagiMemo/releases)**

- GitHub に慣れていない方は、**まず Release ZIP を使う方法がおすすめ**です。
- ZIP をダウンロードして展開すれば、必要なファイルをひとまとめで確認できます。

### 2. すでに導入済み: NagiMemo Updater を使う
- すでに NagiMemo を設置していて、**更新を楽にしたい方向け**です。
- ブラウザから `nagimemo_update.php` を開くだけで、差分があるファイルをまとめて更新できます。

### 3. 最新ソースを直接見る: repository を使う
- GitHub の repository では、いまの最新ソースをそのまま確認できます。
- 「ファイル単位で中身を見たい」「更新内容を追いたい」方向けです。

> [!IMPORTANT]
> **v1.1.8 以降の Release ZIP には `nagimemo_update.php` を同梱します。**  
> そのため、Release ZIP をダウンロードするだけで、アップデータ本体も一緒に入手しやすくなります。

---

## 🆙 NagiMemo Updater（一括アップデートツール）
「スキンの更新があるたびに、FTPで何枚も上書きアップロードするのは面倒だな…」という方のために、AIと協力して**ワンクリックで最新版に更新できるツール**を用意しました。

- **直接ダウンロード**: [nagimemo_update.php](https://raw.githubusercontent.com/Lichiphen/NagiMemo/main/nagimemo_update.php)

> [!IMPORTANT]
> `nagimemo_update.php` は、**てがろぐ本体（`tegalog.cgi`）と同じフォルダ**に置いてください。  
> 別の場所に置くと、スキンの更新先を正しく見つけられません。

### 導入手順

1. 上のダウンロードリンク、または Release ZIP から `nagimemo_update.php` を用意します。（編集は不要です。IP制限をかけたい場合だけテキストエディタで設定してください）
2. `tegalog.cgi` があるフォルダへ `nagimemo_update.php` をアップロードします。
3. **てがろぐの管理画面にログインした状態で**、ブラウザから `https://あなたのURL/nagimemo_update.php` を開きます。
4. 初回はパスワード設定画面が出るので、アップデーター用のパスワードを決めて設定します。
5. 以後はそのパスワードでログインすると、更新がある場合にそのままアップデートできます。

> [!TIP]
> ログイン画面の「**このブラウザにログイン情報を保存する**」にチェックを入れると、30日間はパスワード入力なしで開けます。  
> パスワードの変更・ログアウト・保存したログインの解除は、アップデーター画面下部の「アカウント設定」から行えます。

> [!NOTE]
> - パスワードは同じフォルダの `nagimemo_update_auth.php` に暗号化（ハッシュ化）して保存されます。Web から中身は見えません。
> - 最初のパスワード設定は、安全のため **てがろぐに管理者（権限Lv.9）でログインしている人だけ** が行えます。
> - パスワードを忘れた場合は、FTP 等で `nagimemo_update_auth.php` を削除すると初回設定からやり直せます。
> - 以前の版で `nagimemo_update.php` に合言葉を書いていた場合は、その合言葉のままログインできます（画面から新しいパスワードを設定すると、以後はそちらが使われます）。配布版の初期値のままだった場合は、初回設定画面が表示されます。

> [!TIP]
> FTP で毎回ファイルを探して上書きする必要がないので、  
> 「更新はしたいけど、GitHub や差分管理はよく分からない」という方ほど Updater が向いています。

### 更新対象

- `skin-nagimemo`
- `NagiGallery`
- `NagiPicts`
- `skin-nagi_sitemap`
- `nagimemo_update.php`

> [!NOTE]
> Updater は GitHub 上の `main` ブランチ ZIP を参照し、**差分があるファイルだけ**を上書きします。  
> GitHub 側に新しく追加されたファイルがあれば、それも必要に応じて増やします。

> [!CAUTION]
> Updater は **同じ名前・同じ場所にあるファイル** は上書きします。  
> そのため、標準ファイルを直接編集している場合は、更新前にバックアップを取るのがおすすめです。

> [!TIP]
> 次の3ファイルは **自由に編集して大丈夫** です。編集していれば Updater は上書きせず、新しい配布版を `〜.new.html` として横に置きます（中身を見比べて、必要な部分だけ取り込めます）。
> - `skin-nagimemo/modules/sidebar.html`（サイドバーの項目）
> - `skin-nagimemo/modules/footer.html`（フッターの Home / Admin などのリンク）
> - `skin-nagimemo/modules/noindex.html`（検索避け）
>
> 編集していないファイルは通常どおり最新版に更新されます。

> [!WARNING]
> Updater は **サーバー上にだけ存在する独自ファイルを削除しません** が、  
> 標準ファイルを自分用に改造している場合、そのファイル名が配布版と同じなら更新で置き換わります。

> [!WARNING]
> **css や js を独自で追加される場合は、上書きされてしまう可能性が高いため、必ずバックアップを取ってからアップデートを行ってください。**  
> とくに `skin-cover.html` から独自の CSS / JS を読み込んでいる場合は、更新後に読み込み内容が配布版へ戻ることがあります。

> [!IMPORTANT]
> `skin-cover.html` には、**アップデート時も保持される独自追記用コメントブロック**を用意しています。  
> 独自の CSS や JS を追加したい場合は、必ず `NAGIMEMO:CUSTOM-HEAD` または `NAGIMEMO:CUSTOM-FOOT` のコメント範囲の中へ追加してください。  
> その範囲の外側は NagiMemo の管理範囲として扱われ、配布版に合わせて修復・上書きされます。

### GitHubでの配布について

GitHub に慣れていない方向けに、ざっくり分けると次の理解で大丈夫です。

- **Release**:
  - 配布用のまとまった ZIP です。
  - 「とりあえず導入したい」「必要なものをまとめて欲しい」場合はこちらが簡単です。
- **Repository**:
  - ソースコードそのものです。
  - 「中身を見たい」「最新の更新を追いたい」場合はこちらを見ます。

> [!IMPORTANT]
> Release ZIP は `skin-nagimemo` / `NagiGallery` / `NagiPicts` / `skin-nagi_sitemap` と  
> `nagimemo_update.php` を含む前提で生成します。

> [!TIP]
> GitHub の操作に慣れていない場合は、  
> **「Code」ボタンから repository を読むより、まず Release ページを見る**ほうが迷いにくいです。

---

## ⚖️ ライセンスについて
NagiMemo は **MIT License** を適用しています。
自分のサイトで使うだけであれば、著作権表示を消さない限り自由にご利用いただけます。

> [!NOTE]
> 個人サイトでの利用、色やレイアウトの調整、軽いカスタマイズはしやすいライセンスです。  
> ただし、配布や再公開をする場合は、元作品の情報を README などに残してください。

### 二次配布・改変配布について
本スキンを改変して第三者へ配布（GitHub等への公開を含む）する場合は、以下の内容を `README.md` 等に含めてください。

```markdown
# NagiMemo（改変版）

このファイルは **NagiMemo** を元に改変したものです。

## 元の作品について
- **作品名**：NagiMemo
- **作者**：Lichiphen
- **ライセンス**：MIT License
- **配布元**：
  - [GitHub](https://github.com/Lichiphen/NagiMemo/)
  - [GitLab](https://gitlab.com/lichiphen/NagiMemo)

## 著作権表記
Copyright (c) 2026 Lichiphen  
Released under the MIT License  
https://github.com/Lichiphen/NagiMemo/blob/main/LICENSE
```

---

## 🔗 関連リンク
- [**てがろぐ公式サイト**](https://www.nishishi.com/cgi/tegalog/) - いつもお世話になっています。

## 📩 お問い合わせ
フィードバックやご質問は以下の窓口からお気軽にどうぞ。

- [**mond**](https://mond.how/ja/lichiphen) (匿名可)
- [**X (Twitter)**](https://twitter.com/lichiphen)
- **Email**: `lichiphen@gmail.com`

## 🛠️ 使用ライブラリ・外部資産
本スキンでは以下の素晴らしいライブラリや資産を使用しています。

- [**Google Fonts**](https://fonts.google.com/) (LINE Seed JP / Poppins)
  - [LINE Seed JP](https://fonts.google.com/specimen/LINE+Seed+JP?query=Line+SEED)
  - [Poppins](https://fonts.google.com/specimen/Poppins)  
  - [Noto Sans JP](https://fonts.google.com/noto/specimen/Noto+Sans+JP)(Ver1.1.3まで使用)
- [**Material Symbols**](https://fonts.google.com/icons) (Rounded)
- [**Font Awesome Free**](https://fontawesome.com/) - シェアボタン・コードコピー等
- [**Twemoji**](https://github.com/twitter/twemoji)

### デモサイトで使用しているライブラリ
- [**NagiSwipe**](https://github.com/lichiphen/nagiswipe) 
