// NagiMemo Profile Modal
// NagiMemo v1.2.1
// Copyright (c) 2026 Lichiphen
// Licensed under the MIT License
// https://github.com/Lichiphen/NagiMemo/blob/main/LICENSE
(() => {
    'use strict';

    const MODAL_ID = 'profile-modal';
    const CONTENT_SOURCE_ID = 'hidden-profile-content';
    let lastTrigger = null;

    const getModal = () => document.getElementById(MODAL_ID);
    const isOpen = () => !!getModal()?.classList.contains('active');

    function getOrCreateModal() {
        let modal = getModal();
        if (modal) return modal;

        // サイドバー内の [[FREESPACE:1]] をモーダル本文として使う
        const source = document.getElementById(CONTENT_SOURCE_ID);
        const contentHTML = source ? source.innerHTML : '<p>プロフィール情報が見つかりません</p>';

        modal = document.createElement('div');
        modal.id = MODAL_ID;
        modal.innerHTML =
            '<div class="modal-overlay"></div>' +
            '<div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="profile-modal-title">' +
                '<div class="modal-header">' +
                    '<h3 class="modal-title" id="profile-modal-title">Profile</h3>' +
                    '<button class="modal-close" type="button" aria-label="Close">' +
                        '<span class="material-symbols-rounded">close</span>' +
                    '</button>' +
                '</div>' +
                `<div class="modal-body">${contentHTML}</div>` +
            '</div>';

        document.body.appendChild(modal);
        return modal;
    }

    function openProfileModal(trigger) {
        const modal = getOrCreateModal();
        lastTrigger = trigger || null;
        // 生成直後でもトランジションが効くよう1フレーム待つ
        requestAnimationFrame(() => {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            modal.querySelector('.modal-close')?.focus();
        });
    }

    // フェードアウトは CSS (visibility/opacity の transition) が担当する
    function closeProfileModal() {
        const modal = getModal();
        if (!modal || !modal.classList.contains('active')) return;
        modal.classList.remove('active');
        document.body.style.overflow = '';
        lastTrigger?.focus();
    }

    function initProfileModal() {
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('.linkbtn_profile');
            if (trigger) {
                e.preventDefault();
                openProfileModal(trigger);
                return;
            }
            if (isOpen() && (e.target.classList.contains('modal-overlay') || e.target.closest('.modal-close'))) {
                e.preventDefault();
                closeProfileModal();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isOpen()) {
                e.preventDefault();
                closeProfileModal();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProfileModal);
    } else {
        initProfileModal();
    }
})();
