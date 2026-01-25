// NagiMemo Edit JS
// NagiMemo v1.1.6
// Copyright (c) 2026 Lichiphen
// Licensed under the MIT License
// https://github.com/Lichiphen/NagiMemo/blob/main/LICENSE

(function() {
    'use strict';
    
    // Add Placeholder if missing
    var ta = document.querySelector('textarea.tegalogpost');
    if (ta && !ta.getAttribute('placeholder')) {
        ta.setAttribute('placeholder', 'ここに本文を入力...');
    }
    
    // Move the inserted "Edit Customizer" info if needed, or perform cleanup
    // Default Tegalog output puts the insertion *after* the form. 
    // We might want to move some elements around if possible, but CSS is safer.
    
    // Example: Auto-expand init
    if (ta) {
        ta.addEventListener('input', function() {
            // Auto-resize logic if desired
        });
    }

    // Fix button labels to be prettier (removing old school brackets if any)
    var inputs = document.querySelectorAll('input[type="button"]');
    inputs.forEach(function(btn) {
        if (btn.value === '画像') btn.value = '📷 画像';
        if (btn.value === '装飾') btn.value = '✨ 装飾';
    });
    
    // Disable Autocomplete for all text/search inputs
    var textInputs = document.querySelectorAll('input[type="text"], input[type="search"]');
    textInputs.forEach(function(input) {
        input.setAttribute('autocomplete', 'off');
    });

    console.log('Nagimemo Edit: Loaded');
})();
