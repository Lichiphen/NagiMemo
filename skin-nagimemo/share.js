/**
 * NagiMemo Share & Credits Logic
 * v1.1.4
 */
(function() {
    'use strict';

    // --- Config ---
    const isAndroid = /Android/i.test(navigator.userAgent);

    // --- Helper: Extract title from post-body (first line = title) ---
    function getPostTitle(btn) {
        const article = btn.closest('.onelogbox');
        if (!article) return '';
        const postBody = article.querySelector('.post-body');
        if (!postBody) return '';
        
        // Try H1 first (if user wrote markdown # Title)
        const h1 = postBody.querySelector('h1');
        if (h1 && h1.textContent.trim()) {
            return h1.textContent.trim();
        }
        
        // Get first child node that contains text
        const walker = document.createTreeWalker(
            postBody,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );
        
        let firstText = '';
        let node;
        while (node = walker.nextNode()) {
            const text = node.textContent.trim();
            if (text) {
                firstText = text;
                break;
            }
        }
        
        // Return first 120 chars max
        return firstText.substring(0, 120);
    }

    // --- Share & Copy Click Handler ---
    document.addEventListener('click', async (e) => {
        // X Share Button
        const xBtn = e.target.closest('.js-share-x');
        if (xBtn) {
            e.preventDefault();
            const path = xBtn.getAttribute('data-url') || '';
            // Ensure absolute URL
            const absoluteUrl = new URL(path, document.baseURI).href;
            const title = getPostTitle(xBtn);
            const finalText = title || 'Check this out!';

            // Use twitter.com/intent/tweet for best compatibility 2026
            const shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(finalText)}&url=${encodeURIComponent(absoluteUrl)}`;
            
            // Open in new tab with security attributes
            window.open(shareUrl, '_blank', 'noopener,noreferrer');
            return;
        }

        // Copy Button
        const copyBtn = e.target.closest('.js-share-copy');
        if (copyBtn) {
            e.preventDefault();
            const path = copyBtn.getAttribute('data-url') || '';
            const absoluteUrl = new URL(path, document.baseURI).href;
            
            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(absoluteUrl);
                    showToast();
                    return;
                } catch (err) {
                    console.error('Clipboard failed', err);
                }
            }
            // Fallback
            const tempInput = document.getElementById('tempInput');
            if (tempInput) {
                tempInput.value = absoluteUrl;
                tempInput.select();
                try {
                    document.execCommand('copy');
                    showToast();
                } catch (err) {
                    console.error('ExecCommand copy failed', err);
                }
            }
        }
    });

    // --- Toast ---
    function showToast() {
        const toast = document.getElementById('toast');
        if (!toast) return;
        toast.classList.add('toast-show');
        setTimeout(() => {
            toast.classList.remove('toast-show');
        }, 2000);
    }

    // --- Credits Modal Logic ---
    function initModal() {
        const modal = document.getElementById('credit-modal');
        const trigger = document.getElementById('credits-trigger');
        const close = document.getElementById('modal-close');

        if (trigger && modal && close) {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                modal.classList.add('modal-open');
            });
            close.addEventListener('click', () => {
                modal.classList.remove('modal-open');
            });
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('modal-open');
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModal);
    } else {
        initModal();
    }
})();
