// NagiMemo Edit JS
// NagiMemo v1.2.2
// Copyright (c) 2026 Lichiphen
// Licensed under the MIT License
// https://github.com/Lichiphen/NagiMemo/blob/main/LICENSE

(() => {
    'use strict';

    const ta = document.querySelector('textarea.tegalogpost');
    if (ta && !ta.getAttribute('placeholder')) {
        ta.setAttribute('placeholder', 'ここに本文を入力...');
    }

    const BUTTON_LABELS = { '画像': '📷 画像', '装飾': '✨ 装飾' };
    document.querySelectorAll('input[type="button"]').forEach((btn) => {
        if (BUTTON_LABELS[btn.value]) btn.value = BUTTON_LABELS[btn.value];
    });

    document.querySelectorAll('input[type="text"], input[type="search"]').forEach((input) => {
        input.setAttribute('autocomplete', 'off');
    });
})();
