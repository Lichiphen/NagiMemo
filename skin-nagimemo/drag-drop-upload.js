(function() {
    'use strict';
    
    var overlay = null;
    var isActive = false;
    var collectedFiles = []; // Store files across multiple drops

    function getOrCreateOverlay() {
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'nagi-drop-overlay';
            overlay.innerHTML = '<div style="font-size:1.5rem;font-weight:700;color:#fff;border:4px dashed #fff;padding:40px 80px;border-radius:20px;background:rgba(255,255,255,0.1);text-align:center;">ここに画像をドロップ<br><small style="font-size:0.9rem;opacity:0.8;">（複数回ドロップで追加）</small></div>';
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(37,99,235,0.9);z-index:999999;display:none;align-items:center;justify-content:center;cursor:copy;';
            document.body.appendChild(overlay);
            
            overlay.ondragover = function(e) {
                e.preventDefault();
                e.stopPropagation();
            };
            
            overlay.ondrop = function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('NagiMemo: DROP on overlay');
                addFiles(e.dataTransfer.files);
                hide();
            };
        }
        return overlay;
    }

    function show() {
        var o = getOrCreateOverlay();
        if (!isActive) {
            o.style.display = 'flex';
            isActive = true;
            console.log('NagiMemo: Show overlay');
        }
    }

    function hide() {
        if (overlay && isActive) {
            overlay.style.display = 'none';
            isActive = false;
            console.log('NagiMemo: Hide overlay');
        }
    }

    // Add files to collection (cumulative mode)
    function addFiles(fileList) {
        if (!fileList || fileList.length === 0) {
            console.log('NagiMemo: No files');
            return;
        }
        
        var addedCount = 0;
        for (var i = 0; i < fileList.length; i++) {
            var file = fileList[i];
            if (file.type.indexOf('image') === 0) {
                // Check for duplicates by name and size
                var isDuplicate = collectedFiles.some(function(f) {
                    return f.name === file.name && f.size === file.size;
                });
                if (!isDuplicate) {
                    collectedFiles.push(file);
                    addedCount++;
                }
            }
        }
        
        if (addedCount === 0) {
            toast('画像ファイルをドロップしてください', true);
            return;
        }
        
        console.log('NagiMemo: Added', addedCount, 'files. Total:', collectedFiles.length);
        updateFileInput();
        toast(collectedFiles.length + '枚の画像を選択中 (+' + addedCount + ')');
    }

    // Update the file input with all collected files
    function updateFileInput() {
        var input = findFileInput();
        
        if (!input) {
            // Try opening modal first
            var fab = document.getElementById('fab-post');
            if (fab) {
                fab.click();
                setTimeout(function() {
                    var i2 = findFileInput();
                    if (i2) {
                        assignFilesToInput(i2);
                    }
                }, 500);
            }
            return;
        }
        
        assignFilesToInput(input);
    }

    function findFileInput() {
        return document.querySelector('input[name="upload_file"]') ||
               document.querySelector('input[type="file"][accept*="image"]') ||
               document.querySelector('input[type="file"]');
    }

    function assignFilesToInput(input) {
        if (collectedFiles.length === 0) return;
        
        try {
            var dt = new DataTransfer();
            collectedFiles.forEach(function(f) { dt.items.add(f); });
            input.files = dt.files;
            
            if (input.files.length > 0) {
                input.dispatchEvent(new Event('change', {bubbles: true}));
                console.log('NagiMemo: Assigned', input.files.length, 'files to input');
            }
        } catch (e) {
            console.error('NagiMemo:', e);
            toast(e.message, true);
        }
    }

    // Handle clipboard paste (Ctrl+V)
    function handlePaste(e) {
        var items = e.clipboardData && e.clipboardData.items;
        if (!items) return;
        
        var images = [];
        for (var i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') === 0) {
                var file = items[i].getAsFile();
                if (file) {
                    // Generate a unique name for pasted images
                    var ext = file.type.split('/')[1] || 'png';
                    var timestamp = new Date().toISOString().replace(/[:.]/g, '-');
                    var namedFile = new File([file], 'pasted-' + timestamp + '.' + ext, { type: file.type });
                    images.push(namedFile);
                }
            }
        }
        
        if (images.length > 0) {
            console.log('NagiMemo: Pasted', images.length, 'image(s)');
            // Add to collection
            images.forEach(function(img) {
                collectedFiles.push(img);
            });
            updateFileInput();
            toast(collectedFiles.length + '枚の画像を選択中 (貼り付け+' + images.length + ')');
            e.preventDefault();
        }
    }

    // Clear collected files (called when form is submitted or reset)
    function clearFiles() {
        collectedFiles = [];
        console.log('NagiMemo: Cleared file collection');
    }

    function toast(msg, err) {
        var t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);padding:12px 24px;border-radius:8px;font-weight:bold;z-index:9999999;color:#fff;background:' + (err ? '#ef4444' : '#10b981');
        document.body.appendChild(t);
        setTimeout(function() { t.remove(); }, 3000);
    }

    // Global drag handlers
    var dragCount = 0;
    
    document.addEventListener('dragenter', function(e) {
        e.preventDefault();
        dragCount++;
        if (dragCount === 1) show();
    }, true);
    
    document.addEventListener('dragleave', function(e) {
        e.preventDefault();
        dragCount--;
        if (dragCount === 0) hide();
    }, true);
    
    document.addEventListener('dragover', function(e) {
        e.preventDefault();
    }, true);
    
    document.addEventListener('drop', function(e) {
        e.preventDefault();
        dragCount = 0;
        hide();
    }, true);
    
    // Clipboard paste handler
    document.addEventListener('paste', handlePaste, false);
    
    // ESC to cancel overlay
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isActive) {
            hide();
            dragCount = 0;
        }
    });
    
    // Clear files when form is submitted
    document.addEventListener('submit', function(e) {
        if (e.target.querySelector('input[name="upload_file"]')) {
            setTimeout(clearFiles, 100);
        }
    }, true);
    
    // Expose clear function globally for manual reset
    window.NagiMemoDropClear = clearFiles;

    console.log('NagiMemo: Drag-drop v7 (累積モード + Ctrl+V貼り付け)');
})();
