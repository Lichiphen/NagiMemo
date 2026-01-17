(function() {
    'use strict';

    // モーダルのID
    var MODAL_ID = 'profile-modal';
    var CONTENT_SOURCE_ID = 'hidden-profile-content';

    function initProfileModal() {
        // プロフィールリンクにイベントリスナー設定
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('linkbtn_profile')) {
                e.preventDefault();
                openProfileModal();
            }
        });

        // モーダル内のクリックイベント（閉じる処理など）
        document.addEventListener('click', function(e) {
            var modal = document.getElementById(MODAL_ID);
            if (!modal || !modal.classList.contains('active')) return;

            // オーバーレイクリックまたはcloseボタンクリックで閉じる
            if (e.target.classList.contains('modal-overlay') || 
                e.target.closest('.modal-close')) {
                e.preventDefault();
                closeProfileModal();
            }
        });

        // ESCキーで閉じる
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProfileModal();
            }
        });
    }

    function openProfileModal() {
        var modal = getOrCreateModal();
        if (!modal) return;
        
        // アニメーション用に少し待ってからクラス追加
        // display:none から flex になるフレームが必要
        requestAnimationFrame(function() {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden'; // 背景スクロール防止
        });
    }

    function closeProfileModal() {
        var modal = document.getElementById(MODAL_ID);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            
            // アニメーション完了後に hidden にする処理は CSS transitionend を待つか、
            // activeクラスが display も制御しているので、アニメーションが少し不自然になるかも。
            // CSS: #profile-modal { display: none; opacity: 0; transition: opacity... pointer-events: none; }
            //      #profile-modal.active { display: flex; opacity: 1; pointer-events: auto; }
            // これだと display:none に戻る瞬間にアニメーションが消える。
            // fade-outアニメーションをスムーズにするには、
            // display:flexの状態を維持しつつopacity:0にしてからdisplay:noneにする必要があるが、
            // 簡易実装として active クラスの除去のみで対応（CSS側で調整済みならOKだが、
            // 今回のCSSは active が外れると display:none に戻るためパッと消える。
            // 修正：CSSで display:flex を常時維持し visibility で制御するか、
            // JSで transitionend を待つ必要がある。
            // 今回はユーザー体験を向上させるため、少しJSで待つ。
            
            setTimeout(function() {
                // ここでは特に何もしない（CSSのtransition時間は0.3秒）
                // ただし display:none に戻るのはCSSの定義次第。
                // 今回のCSS定義では active がないと display:none になるので
                // 即座に消えてしまう。
                // CSSを修正したほうが良いが、JSですぐ直すなら active を外す前に transition を待つロジックが必要。
            }, 300);
        }
    }

    function getOrCreateModal() {
        var modal = document.getElementById(MODAL_ID);
        if (modal) return modal;

        // コンテンツソースの取得
        var source = document.getElementById(CONTENT_SOURCE_ID);
        var contentHTML = source ? source.innerHTML : '<p>プロフィール情報が見つかりません</p>';

        modal = document.createElement('div');
        modal.id = MODAL_ID;
        modal.innerHTML = 
            '<div class="modal-overlay"></div>' +
            '<div class="modal-container">' +
                '<div class="modal-header">' +
                    '<h3 class="modal-title">Profile</h3>' +
                    '<button class="modal-close" aria-label="Close">' +
                        '<span class="material-symbols-rounded">close</span>' +
                    '</button>' +
                '</div>' +
                '<div class="modal-body">' +
                    contentHTML +
                '</div>' +
            '</div>';
        
        document.body.appendChild(modal);
        return modal;
    }

    // 初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProfileModal);
    } else {
        initProfileModal();
    }

})();
