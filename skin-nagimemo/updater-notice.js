// NagiMemo v1.2.3
// Copyright (c) 2026 Lichiphen
// Licensed under the MIT License
// https://github.com/Lichiphen/NagiMemo/blob/main/LICENSE
(() => {
    'use strict';

    // ログイン中だけ、1日1回まで更新をお知らせする。
    // 「あとで」・枠外クリック・Esc のどれで閉じても当日は再表示せず、更新確認の通信も行わない。
    const SNOOZE_KEY = 'nagimemo-updater:snoozed-on';

    const modal = document.getElementById('updater-notice-modal');
    const openLink = document.getElementById('updater-notice-open');
    const laterButton = document.getElementById('updater-notice-later');

    if (!modal || !openLink || !laterButton) return;
    if (!document.body?.classList.contains('loggedin-YES')) return;
    if (!window.fetch) return;

    const today = () => {
        const now = new Date();
        return [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
    };

    function isSnoozedToday() {
        try {
            return window.localStorage.getItem(SNOOZE_KEY) === today();
        } catch (error) {
            return false;
        }
    }

    function snoozeToday() {
        try {
            window.localStorage.setItem(SNOOZE_KEY, today());
        } catch (error) {
            // localStorage が使えない環境ではページごとの表示になる
        }
    }

    if (isSnoozedToday()) return;

    const isOpen = () => modal.classList.contains('modal-open');

    function openModal() {
        modal.classList.add('modal-open');
        modal.setAttribute('aria-hidden', 'false');
        laterButton.focus();
    }

    function closeModal() {
        if (!isOpen()) return;
        modal.classList.remove('modal-open');
        modal.setAttribute('aria-hidden', 'true');
        snoozeToday();
    }

    function populateUpdateRow(target, label) {
        const row = modal.querySelector(`[data-update-target="${target}"]`);
        if (!row) return;
        row.hidden = false;
        const meta = row.querySelector('.updater-notice-meta');
        if (meta) meta.textContent = label;
    }

    function updateOpenLink(url) {
        try {
            const nextUrl = new URL(url || 'nagimemo_update.php', window.location.href);
            nextUrl.searchParams.set('return_url', window.location.href);
            openLink.href = nextUrl.toString();
        } catch (error) {
            openLink.href = 'nagimemo_update.php';
        }
    }

    laterButton.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            event.preventDefault();
            closeModal();
        }
    });

    const versionLabel = (skin) => (skin.repair_needed
        ? 'cover.html の管理範囲または不足ファイルを修復します。'
        : `${skin.local_version ? `v${skin.local_version}` : '不明'} → ${skin.remote_version ? `v${skin.remote_version}` : '最新'}`);

    fetch(`nagimemo_update.php?mode=status&t=${Date.now()}`, { credentials: 'same-origin', cache: 'no-store' })
        .then((response) => {
            if (!response.ok) throw new Error('status unavailable');
            return response.json();
        })
        .then((payload) => {
            if (!payload?.has_update) return;
            if (payload.skin?.needs_update) populateUpdateRow('skin', versionLabel(payload.skin));
            if (payload.updater?.needs_update) populateUpdateRow('updater', 'GitHub上の本体に更新があります。');
            updateOpenLink(payload.update_url);
            openModal();
        })
        .catch(() => {
            // 通知は補助機能なので失敗しても何もしない
        });
})();
