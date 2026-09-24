// NagiMemo Share & Credits
// NagiMemo v1.2.0
// Copyright (c) 2026 Lichiphen
// Licensed under the MIT License
// https://github.com/Lichiphen/NagiMemo/blob/main/LICENSE
(() => {
    'use strict';

    const INTENT_URL = 'https://twitter.com/intent/tweet';
    const TITLE_MAX_LENGTH = 120;
    const COPIED_DURATION = 1800;
    // 埋め込み(note/Voicy等)のscriptや非表示要素の中身をタイトルに拾わない
    const SKIP_TITLE_SELECTOR = 'script, style, noscript, template, [hidden], [aria-hidden="true"]';
    const isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);

    const toAbsoluteUrl = (path) => new URL(path || '', document.baseURI).href;

    // 投稿本文の1行目(# 見出しがあればそれ)をシェア文のタイトルにする
    function getPostTitle(btn) {
        const postBody = btn.closest('.onelogbox')?.querySelector('.post-body');
        if (!postBody) return '';

        const h1 = postBody.querySelector('h1');
        if (h1 && h1.textContent.trim()) {
            return h1.textContent.trim().slice(0, TITLE_MAX_LENGTH);
        }

        const walker = document.createTreeWalker(postBody, NodeFilter.SHOW_TEXT, {
            acceptNode: (node) => (node.parentElement?.closest(SKIP_TITLE_SELECTOR)
                ? NodeFilter.FILTER_REJECT
                : NodeFilter.FILTER_ACCEPT)
        });
        for (let node = walker.nextNode(); node; node = walker.nextNode()) {
            const text = node.textContent.trim();
            if (text) return text.slice(0, TITLE_MAX_LENGTH);
        }
        return '';
    }

    function shareToX(btn) {
        const title = getPostTitle(btn) || 'Check this out!';
        const intent = `${INTENT_URL}?text=${encodeURIComponent(`${title}\n${toAbsoluteUrl(btn.dataset.url)}`)}`;

        // モバイルは同じタブで開く(インストール済みならOSがXアプリへ引き渡す／二重画面を防ぐ)
        if (isMobile) {
            window.location.href = intent;
            return;
        }
        const win = window.open(intent, '_blank');
        if (win) {
            win.opener = null;
        } else {
            window.location.href = intent;
        }
    }

    async function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(text);
                return true;
            } catch (err) {
                // http環境や権限拒否時は下のフォールバックへ
            }
        }
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none;';
        document.body.appendChild(ta);
        ta.select();
        let ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (err) {
            ok = false;
        }
        ta.remove();
        return ok;
    }

    function showCopyState(btn, state) {
        const label = btn.querySelector('.share-label');
        if (!btn.dataset.label && label) btn.dataset.label = label.textContent;

        clearTimeout(btn._copyTimer);
        btn.classList.remove('is-copied', 'is-error');
        btn.classList.add(state === 'error' ? 'is-error' : 'is-copied');
        if (label) label.textContent = state === 'error' ? 'Failed' : 'Copied';

        btn._copyTimer = setTimeout(() => {
            btn.classList.remove('is-copied', 'is-error');
            if (label) label.textContent = btn.dataset.label;
        }, COPIED_DURATION);
    }

    document.addEventListener('click', async (e) => {
        const xBtn = e.target.closest('.js-share-x');
        if (xBtn) {
            e.preventDefault();
            shareToX(xBtn);
            return;
        }

        const copyBtn = e.target.closest('.js-share-copy');
        if (copyBtn) {
            e.preventDefault();
            const ok = await copyText(toAbsoluteUrl(copyBtn.dataset.url));
            showCopyState(copyBtn, ok ? 'copied' : 'error');
        }
    });

    // --- Credits Modal ---
    function initModal() {
        const modal = document.getElementById('credit-modal');
        const trigger = document.getElementById('credits-trigger');
        const closeBtn = document.getElementById('modal-close');
        if (!modal || !trigger || !closeBtn) return;

        const open = () => {
            modal.classList.add('modal-open');
            modal.setAttribute('aria-hidden', 'false');
            closeBtn.focus();
        };
        const close = () => {
            if (!modal.classList.contains('modal-open')) return;
            modal.classList.remove('modal-open');
            modal.setAttribute('aria-hidden', 'true');
            trigger.focus();
        };

        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            open();
        });
        closeBtn.addEventListener('click', close);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) close();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('modal-open')) {
                e.preventDefault();
                close();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModal);
    } else {
        initModal();
    }
})();
