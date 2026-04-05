# NagiMemo

書くのが楽しくなる、てがろぐのスキンファイル群です。（てがろぐ V4.8.0 以降対応）

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://github.com/Lichiphen/NagiMemo/blob/main/LICENSE)
[![GitHub](https://img.shields.io/badge/GitHub-NagiMemo-181717?logo=github)](https://github.com/Lichiphen/NagiMemo/)
[![GitLab](https://img.shields.io/badge/GitLab-NagiMemo-FC6D26?logo=gitlab)](https://gitlab.com/Lichiphen/NagiMemo)

現在ベータ版です。てがろぐはアップデートされていくCMSですので、スキン自体の完成は目指していないです。自分が使いやすいようにカスタマイズしたものですので、気ままに更新していきます。

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

1. 上のダウンロードリンク、または Release ZIP から `nagimemo_update.php` を用意します。
2. ファイルをテキストエディタで開き、**アップデート用パスワード**と、必要なら **IP制限** を設定します。
3. `tegalog.cgi` があるフォルダへ `nagimemo_update.php` をアップロードします。
4. ブラウザで `https://あなたのURL/nagimemo_update.php` にアクセスします。
5. 設定したパスワードでログインすると、更新がある場合にそのままアップデートできます。

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
