// NagiMemo v1.1.8
// Copyright (c) 2026 Lichiphen
// Licensed under the MIT License
// https://github.com/Lichiphen/NagiMemo/blob/main/LICENSE
(function() {
    'use strict';

    var modal = document.getElementById('updater-notice-modal');
    var openLink = document.getElementById('updater-notice-open');
    var laterButton = document.getElementById('updater-notice-later');

    if (!modal || !openLink || !laterButton) {
        return;
    }

    if (!document.body || !document.body.classList.contains('loggedin-YES')) {
        return;
    }

    if (!window.fetch || !window.localStorage) {
        return;
    }

    var currentSignature = '';

    function getTodayKey(signature) {
        var now = new Date();
        var year = now.getFullYear();
        var month = String(now.getMonth() + 1).padStart(2, '0');
        var day = String(now.getDate()).padStart(2, '0');
        return ['nagimemo-updater', year + '-' + month + '-' + day, signature].join(':');
    }

    function markSnoozed(signature) {
        try {
            window.localStorage.setItem(getTodayKey(signature), 'later');
        } catch (error) {
            return;
        }
    }

    function isSnoozed(signature) {
        try {
            return window.localStorage.getItem(getTodayKey(signature)) === 'later';
        } catch (error) {
            return false;
        }
    }

    function buildSignature(payload) {
        var skin = payload.skin && payload.skin.signature ? payload.skin.signature : 'skin:none';
        var updater = payload.updater && payload.updater.signature ? payload.updater.signature : 'updater:none';
        return skin + '|' + updater;
    }

    function closeModal() {
        modal.classList.remove('modal-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    function openModal() {
        modal.classList.add('modal-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function populateUpdateRow(target, label) {
        var row = modal.querySelector('[data-update-target="' + target + '"]');
        if (!row) {
            return;
        }

        row.hidden = false;
        var meta = row.querySelector('.updater-notice-meta');
        if (meta) {
            meta.textContent = label;
        }
    }

    function updateOpenLink(url) {
        var nextUrl;

        try {
            nextUrl = new URL(url || 'nagimemo_update.php', window.location.href);
            nextUrl.searchParams.set('return_url', window.location.href);
            openLink.href = nextUrl.toString();
        } catch (error) {
            openLink.href = 'nagimemo_update.php';
        }
    }

    function bindEvents() {
        laterButton.addEventListener('click', function() {
            if (currentSignature !== '') {
                markSnoozed(currentSignature);
            }
            closeModal();
        });

        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    }

    function loadStatus() {
        fetch('nagimemo_update.php?mode=status', {
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('status unavailable');
                }
                return response.json();
            })
            .then(function(payload) {
                if (!payload || !payload.has_update) {
                    return;
                }

                currentSignature = buildSignature(payload);
                if (isSnoozed(currentSignature)) {
                    return;
                }

                if (payload.skin && payload.skin.needs_update) {
                    populateUpdateRow(
                        'skin',
                        payload.skin.repair_needed
                            ? '設置ファイルの参照崩れまたは不足ファイルを修復します。'
                            : (payload.skin.local_version ? 'v' + payload.skin.local_version : '不明') +
                                ' → ' +
                                (payload.skin.remote_version ? 'v' + payload.skin.remote_version : '最新')
                    );
                }

                if (payload.updater && payload.updater.needs_update) {
                    populateUpdateRow('updater', 'GitHub上の本体に更新があります。');
                }

                updateOpenLink(payload.update_url);
                openModal();
            })
            .catch(function() {
                return;
            });
    }

    bindEvents();
    loadStatus();
})();
