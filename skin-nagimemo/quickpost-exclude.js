// QUICKPOST Recent Image NagiSwipe Excluder
// Prevents NagiSwipe from triggering on recent image thumbnails in QUICKPOST.
// NagiMemo v1.2.3
// Copyright (c) 2026 Lichiphen
// Licensed under the MIT License
// https://github.com/Lichiphen/NagiMemo/blob/main/LICENSE
(() => {
    'use strict';

    if (!document.body.classList.contains('loggedin-YES')) return;

    function excludeThumbnails() {
        // NagiSwipe が後から data-ns-index を付け直す場合があるため毎回全件を確認する
        document.querySelectorAll('.recentimginsert').forEach((link) => {
            // NagiSwipe が対象判定に使う属性を外し、除外フラグを付ける
            link.removeAttribute('data-ns-index');
            link.classList.add('ns-exclude');

            // 誤ドラッグによる再アップロードを防ぐ
            link.setAttribute('draggable', 'false');
            const img = link.querySelector('img');
            if (img) {
                img.setAttribute('draggable', 'false');
                img.style.webkitUserDrag = 'none';
            }
        });
    }

    document.addEventListener('dragstart', (e) => {
        if (e.target.closest?.('.recentimginsert')) e.preventDefault();
    }, true);

    // サムネイルは動的に追加されるため監視する（変更はフレーム単位でまとめて処理）
    let scheduled = false;
    new MutationObserver(() => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            excludeThumbnails();
        });
    }).observe(document.body, { childList: true, subtree: true });

    excludeThumbnails();
})();
