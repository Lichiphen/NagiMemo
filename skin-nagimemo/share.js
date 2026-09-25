// NagiMemo Share & Credits
// NagiMemo v1.2.3
// Copyright (c) 2026 Lichiphen
// Licensed under the MIT License
// https://github.com/Lichiphen/NagiMemo/blob/main/LICENSE
(() => {
    'use strict';

    const INTENT_URL = 'https://twitter.com/intent/tweet';
    const GUIDE_ID = 'share-x-guide';
    const TITLE_MAX_LENGTH = 120;
    const COPIED_DURATION = 1800;
    // 埋め込み(note/Voicy等)のscriptや非表示要素の中身をタイトルに拾わない
    const SKIP_TITLE_SELECTOR = 'script, style, noscript, template, [hidden], [aria-hidden="true"]';
    const isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
    const isAndroid = /Android/i.test(navigator.userAgent);
    // XのiOSアプリ内ブラウザ。実機でintentのログイン画面やループを確認したため、
    // 安定した直接投稿の方法が見つかるまではコピー案内を使う
    const isXInAppIOS = /Twitter for (iPhone|iPad)/i.test(navigator.userAgent);

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

    function getShareText(btn) {
        const title = getPostTitle(btn) || 'Check this out!';
        return `${title}\n${toAbsoluteUrl(btn.dataset.url)}`;
    }

    function shareToX(btn) {
        const text = getShareText(btn);
        const intent = `${INTENT_URL}?text=${encodeURIComponent(text)}`;

        // AndroidのXアプリはintentで開くと1行目に空白行を入れるので、使えるなら共有シート経由で渡す
        // (https限定。XアプリのWebViewには navigator.share が無いのでintentのまま)
        if (isAndroid && typeof navigator.share === 'function') {
            navigator.share({ text }).catch((err) => {
                if (err.name !== 'AbortError') window.location.href = intent;
            });
            return;
        }

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

    // --- XのiOSアプリ内ブラウザ用の案内(シェア文をコピーして、投稿画面への貼り付けを促す) ---
    function getGuide() {
        let guide = document.getElementById(GUIDE_ID);
        if (guide) return guide;

        guide = document.createElement('div');
        guide.id = GUIDE_ID;
        guide.setAttribute('aria-hidden', 'true');
        guide.innerHTML = `
            <div class="modal-box share-guide-box" role="dialog" aria-modal="true" aria-labelledby="${GUIDE_ID}-title">
                <h3 id="${GUIDE_ID}-title">Xでシェア</h3>
                <p class="share-guide-lead">Xアプリ内では、シェアボタンから投稿画面が開かないことがあります。</p>
                <p class="share-guide-status" aria-live="polite"></p>
                <textarea class="share-guide-text" readonly rows="3" aria-label="シェア文"></textarea>
                <ol class="share-guide-steps">
                    <li>このページを閉じて、Xで新しい投稿を開く</li>
                    <li>コピーした文を投稿画面に貼り付ける</li>
                </ol>
                <button type="button" class="modal-close-btn share-guide-close">Close</button>
            </div>`;
        document.body.appendChild(guide);

        const close = () => {
            if (!guide.classList.contains('modal-open')) return;
            guide.classList.remove('modal-open');
            guide.setAttribute('aria-hidden', 'true');
            guide._trigger?.focus();
        };
        guide.querySelector('.share-guide-close').addEventListener('click', close);
        guide.addEventListener('click', (e) => {
            if (e.target === guide) close();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && guide.classList.contains('modal-open')) {
                e.preventDefault();
                close();
            }
        });
        return guide;
    }

    function showXInAppGuide(btn, text, copied) {
        const guide = getGuide();
        const status = guide.querySelector('.share-guide-status');
        status.textContent = copied ? '✓ シェア文をコピーしました' : 'コピーできませんでした。下の文を長押ししてコピーしてください';
        status.classList.toggle('is-error', !copied);
        guide.querySelector('.share-guide-text').value = text;
        guide._trigger = btn;
        guide.classList.add('modal-open');
        guide.setAttribute('aria-hidden', 'false');
        guide.querySelector('.share-guide-close').focus();
    }

    document.addEventListener('click', async (e) => {
        const xBtn = e.target.closest('.js-share-x');
        if (xBtn && isXInAppIOS) {
            e.preventDefault();
            const text = getShareText(xBtn);
            showXInAppGuide(xBtn, text, await copyText(text));
            return;
        }
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
