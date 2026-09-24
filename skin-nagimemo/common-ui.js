// NagiMemo v1.2.0
// Copyright (c) 2026 Lichiphen
// Licensed under the MIT License
// https://github.com/Lichiphen/NagiMemo/blob/main/LICENSE
(() => {
    'use strict';

    const PC_BREAKPOINT = 900;
    const FAB_SCROLL_THRESHOLD = 300;

    const fabMenu = document.getElementById('fab-menu');
    const fabPost = document.getElementById('fab-post');
    const closeBtn = document.querySelector('.close-sidebar');
    const sidebar = document.getElementById('sidebar');
    const postArea = document.querySelector('.postarea-wrapper');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const postOverlay = document.getElementById('post-overlay');

    const isEmptyText = (node) => node && node.nodeType === Node.TEXT_NODE && node.textContent.trim() === '';
    const isBr = (node) => node && node.nodeName === 'BR';

    // 空白テキストノードを飛ばした次の兄弟ノード
    function nextMeaningfulSibling(node) {
        let next = node.nextSibling;
        while (isEmptyText(next)) next = next.nextSibling;
        return next;
    }

    // =========================================================
    // Sidebar / QUICKPOST panels
    // =========================================================
    function setPanel(panel, overlay, fab, open) {
        panel?.classList.toggle('active', open);
        overlay?.classList.toggle('active', open);
        if (fab) {
            fab.classList.toggle('active', open);
            fab.setAttribute('aria-expanded', String(open));
        }
    }

    const isSidebarOpen = () => !!sidebar?.classList.contains('active');
    const isPostOpen = () => !!postArea?.classList.contains('active');

    function openSidebar() {
        closePost();
        setPanel(sidebar, sidebarOverlay, fabMenu, true);
    }
    function closeSidebar() {
        setPanel(sidebar, sidebarOverlay, fabMenu, false);
    }
    function openPost() {
        closeSidebar();
        setPanel(postArea, postOverlay, fabPost, true);
    }
    function closePost() {
        setPanel(postArea, postOverlay, fabPost, false);
    }

    fabMenu?.setAttribute('aria-controls', 'sidebar');
    fabMenu?.setAttribute('aria-expanded', 'false');
    fabPost?.setAttribute('aria-expanded', 'false');

    fabMenu?.addEventListener('click', (e) => {
        e.preventDefault();
        isSidebarOpen() ? closeSidebar() : openSidebar();
    });
    fabPost?.addEventListener('click', (e) => {
        e.preventDefault();
        isPostOpen() ? closePost() : openPost();
    });
    sidebarOverlay?.addEventListener('click', closeSidebar);
    postOverlay?.addEventListener('click', closePost);
    closeBtn?.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape' || e.defaultPrevented) return;
        // 他のモーダル(プロフィール・クレジット等)が開いている場合はそちらを優先
        if (document.querySelector('#profile-modal.active, #credit-modal.modal-open, #updater-notice-modal.modal-open')) return;
        if (isSidebarOpen()) closeSidebar();
        if (isPostOpen()) closePost();
    });

    // =========================================================
    // PC: 一定量スクロールしたら「書く」FABを表示
    // =========================================================
    let scrollTicking = false;
    function checkScrollFab() {
        scrollTicking = false;
        if (!fabPost) return;
        const visible = window.innerWidth > PC_BREAKPOINT && window.scrollY > FAB_SCROLL_THRESHOLD;
        fabPost.classList.toggle('pc-scroll-visible', visible);
    }
    function requestScrollCheck() {
        if (scrollTicking) return;
        scrollTicking = true;
        window.requestAnimationFrame(checkScrollFab);
    }
    window.addEventListener('scroll', requestScrollCheck, { passive: true });
    window.addEventListener('resize', requestScrollCheck, { passive: true });
    checkScrollFab();

    // =========================================================
    // Widgets: リンクと件数(.num)を1つのリンクにまとめる
    // =========================================================
    function mergeLinkAndCount(containerSelector, linkSelector) {
        document.querySelectorAll(`${containerSelector} ${linkSelector}`).forEach((link) => {
            let sibling = link.nextSibling;
            while (sibling) {
                if (sibling.nodeType === Node.ELEMENT_NODE && sibling.classList.contains('num')) {
                    link.appendChild(sibling);
                    break;
                }
                sibling = sibling.nextSibling;
            }
        });
    }

    // =========================================================
    // Image Grid Formatter
    // 1 image -> 100% width / 2+ consecutive images -> 2 columns
    // =========================================================
    function formatImageGrid() {
        document.querySelectorAll('.post-body, .onelogbox').forEach((post) => {
            const images = Array.from(post.querySelectorAll('.imagelink'));
            if (images.length === 0) return;

            // 空白・<br> だけを挟んで連続している画像をグループ化
            const groups = [];
            let current = [];
            images.forEach((img) => {
                const prev = current[current.length - 1];
                if (prev && isAdjacentImage(prev, img)) {
                    current.push(img);
                } else {
                    if (current.length) groups.push(current);
                    current = [img];
                }
            });
            if (current.length) groups.push(current);

            groups.forEach((group) => {
                if (group.length < 2) {
                    group[0].classList.add('single-image');
                    return;
                }
                const container = document.createElement('div');
                container.className = 'image-grid-container';
                group[0].parentNode.insertBefore(container, group[0]);
                group.forEach((img, idx) => {
                    img.classList.add('grid-image', idx % 2 === 0 ? 'grid-odd' : 'grid-even');
                    container.appendChild(img);
                });
            });
        });
    }

    function isAdjacentImage(prevImg, img) {
        const brs = [];
        let node = prevImg.nextSibling;
        while (node && node !== img) {
            if (isBr(node)) {
                brs.push(node);
            } else if (!isEmptyText(node)) {
                return false;
            }
            node = node.nextSibling;
        }
        if (node !== img) return false;
        // 画像間の改行は装飾目的とみなしてCSSで隠す
        brs.forEach((br) => br.classList.add('grid-hidden-br'));
        return true;
    }

    // 画像・動画の直後にある単独の <br> を隠す（連続した <br> は意図的な余白として残す）
    function hideSingleBrAfterMedia() {
        document.querySelectorAll('.post-body, .onelogbox, .article-body').forEach((post) => {
            post.querySelectorAll('.imagelink, figure.embeddedvideo').forEach((el) => {
                const br = nextMeaningfulSibling(el);
                if (!isBr(br)) return;
                if (isBr(nextMeaningfulSibling(br))) return;
                br.classList.add('grid-hidden-br');
            });
        });
    }

    function disableAutocomplete() {
        document.querySelectorAll('input[type="text"], input[type="search"]').forEach((input) => {
            input.setAttribute('autocomplete', 'off');
        });
    }

    // 直後の <br> を1つ削除（てがろぐの余分な改行対策）
    function removeNextBr(element) {
        const next = element.nextSibling;
        if (isBr(next)) {
            next.remove();
        } else if (isEmptyText(next) && isBr(next.nextSibling)) {
            next.nextSibling.remove();
        }
    }

    // =========================================================
    // Code Block Copy Button
    // =========================================================
    const COPY_ICON = '<svg viewBox="0 0 448 512" aria-hidden="true"><path d="M384 336H192c-8.8 0-16-7.2-16-16V64c0-8.8 7.2-16 16-16h140.1L432 147.9V320c0 8.8-7.2 16-16 16zM192 384H384c35.3 0 64-28.7 64-64V147.9c0-12.7-5.1-24.9-14.1-33.9L333.9 14.1c-9-9-21.2-14.1-33.9-14.1H192c-35.3 0-64 28.7-64 64V320c0 35.3 28.7 64 64 64zM64 128c-35.3 0-64 28.7-64 64V448c0 35.3 28.7 64 64 64H256c35.3 0 64-28.7 64-64V416H272v32c0 8.8-7.2 16-16 16H64c-8.8 0-16-7.2-16-16V192c0-8.8 7.2-16 16-16H96V128H64z"></path></svg>';

    async function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(text);
                return true;
            } catch (err) {
                // フォールバックへ
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

    function initCodeCopyButtons() {
        document.querySelectorAll('code.decoration1').forEach((codeEl) => {
            if (codeEl.parentNode.classList?.contains('code-block-wrapper')) return;

            const wrapper = document.createElement('div');
            wrapper.className = 'code-block-wrapper';
            codeEl.parentNode.insertBefore(wrapper, codeEl);
            wrapper.appendChild(codeEl);

            const copyBtn = document.createElement('button');
            copyBtn.type = 'button';
            copyBtn.className = 'code-copy-btn';
            copyBtn.innerHTML = `${COPY_ICON} <span>コピー</span>`;
            wrapper.appendChild(copyBtn);

            removeNextBr(wrapper);

            copyBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                const ok = await copyText(codeEl.textContent || '');
                showCopyToast(e.clientX, e.clientY, ok ? 'コピー完了' : 'コピー失敗', !ok);
            });
        });
    }

    function showCopyToast(x, y, message, isError) {
        const toast = document.createElement('div');
        toast.className = 'copy-toast';
        toast.setAttribute('role', 'status');
        toast.textContent = message;
        if (isError) toast.style.background = '#ef4444';
        toast.style.left = `${x}px`;
        toast.style.top = `${y}px`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 1500);
    }

    function cleanupDecorations() {
        document.querySelectorAll('ul.decorationL').forEach(removeNextBr);

        // 吹き出し(p.decorationF)直後の単独 <br> を削除（連続した <br> は残す）
        document.querySelectorAll('p.decorationF').forEach((bubble) => {
            const br = nextMeaningfulSibling(bubble);
            if (!isBr(br)) return;
            const after = nextMeaningfulSibling(br);
            if (after && !isBr(after)) br.remove();
        });
    }

    mergeLinkAndCount('.widget.datelistarea', '.datelistlink');
    mergeLinkAndCount('.hashtaglistarea', '.taglink');
    mergeLinkAndCount('.categoryarea', '.catlink');
    mergeLinkAndCount('.categoryarea', '.categorylink');

    if (typeof twemoji !== 'undefined') {
        twemoji.parse(document.body);
    }

    // 初期表示時のアニメーションのちらつきを防ぐ
    setTimeout(() => document.body.classList.add('animations-ready'), 100);

    formatImageGrid();
    hideSingleBrAfterMedia();
    disableAutocomplete();
    initCodeCopyButtons();
    cleanupDecorations();
})();
