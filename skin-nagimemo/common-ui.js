// FAB Sidebar/Post Toggle and Widget Formatting
(function () {
    'use strict';
    
    var fabMenu = document.getElementById('fab-menu');
    var fabPost = document.getElementById('fab-post');
    var closeBtn = document.querySelector('.close-sidebar');
    var sidebar = document.getElementById('sidebar');
    var postArea = document.querySelector('.postarea-wrapper');
    var sidebarOverlay = document.getElementById('sidebar-overlay');
    var postOverlay = document.getElementById('post-overlay');

    function openSidebar() {
        closePost(); // Close post if open
        if (sidebar) sidebar.classList.add('active');
        if (sidebarOverlay) sidebarOverlay.classList.add('active');
        if (fabMenu) {
            fabMenu.querySelector('span').textContent = 'close';
            fabMenu.classList.add('active');
        }
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('active');
        if (sidebarOverlay) sidebarOverlay.classList.remove('active');
        if (fabMenu) {
            fabMenu.querySelector('span').textContent = 'menu';
            fabMenu.classList.remove('active');
        }
    }

    function openPost() {
        closeSidebar(); // Close sidebar if open
        if (postArea) postArea.classList.add('active');
        if (postOverlay) postOverlay.classList.add('active');
        if (fabPost) {
            fabPost.querySelector('span').textContent = 'close';
            fabPost.classList.add('active');
        }
    }

    function closePost() {
        if (postArea) postArea.classList.remove('active');
        if (postOverlay) postOverlay.classList.remove('active');
        if (fabPost) {
            fabPost.querySelector('span').textContent = 'edit';
            fabPost.classList.remove('active');
        }
    }

    function toggleSidebar(e) {
        if (e) e.preventDefault();
        if (sidebar && sidebar.classList.contains('active')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    function togglePost(e) {
        if (e) e.preventDefault();
        if (postArea && postArea.classList.contains('active')) {
            closePost();
        } else {
            openPost();
        }
    }

    if (fabMenu) fabMenu.onclick = toggleSidebar;
    if (fabPost) fabPost.onclick = togglePost;
    if (sidebarOverlay) sidebarOverlay.onclick = closeSidebar;
    if (postOverlay) postOverlay.onclick = closePost;
    if (closeBtn) closeBtn.onclick = closeSidebar;

    // PC Scroll FAB Visibility Logic
    function checkScrollFab() {
        // Only active on PC (> 900px)
        if (window.innerWidth > 900) {
            if (window.scrollY > 300) {
                if (fabPost) fabPost.classList.add('pc-scroll-visible');
            } else {
                if (fabPost) fabPost.classList.remove('pc-scroll-visible');
            }
        } else {
            // On mobile, ensure class is removed to fallback to default CSS
            if (fabPost) fabPost.classList.remove('pc-scroll-visible');
        }
    }

    window.addEventListener('scroll', checkScrollFab);
    window.addEventListener('resize', checkScrollFab);
    // Initial check
    checkScrollFab();

    // Widget Link Fix: Merge link and post count for Archives, Hashtags, and Categories
    function mergeLinkAndCount(containerSelector, linkClass) {
        var areas = document.querySelectorAll(containerSelector);
        for (var i = 0; i < areas.length; i++) {
            var links = areas[i].querySelectorAll(linkClass);
            for (var j = 0; j < links.length; j++) {
                var link = links[j];
                // Find .num that is a sibling (next element)
                var sibling = link.nextSibling;
                while (sibling) {
                    if (sibling.nodeType === 1 && sibling.classList && sibling.classList.contains('num')) {
                        link.appendChild(sibling);
                        break;
                    }
                    sibling = sibling.nextSibling;
                }
            }
        }
    }

    mergeLinkAndCount('.widget.datelistarea', '.datelistlink');
    mergeLinkAndCount('.hashtaglistarea', '.taglink');
    // For categories, target both .catlink and .categorylink
    mergeLinkAndCount('.categoryarea', '.catlink');
    mergeLinkAndCount('.categoryarea', '.categorylink');

    // Execute Twemoji
    if (typeof twemoji !== 'undefined') {
        twemoji.parse(document.body);
    }

    // Enable animations after page load (prevent initial animation flash)
    setTimeout(function () {
        document.body.classList.add('animations-ready');
    }, 100);

    // =========================================================
    // Image Grid Formatter (JS fallback for robust layout)
    // 1 image -> 100% width
    // 2+ images -> 2 columns
    // =========================================================
    function formatImageGrid() {
        var posts = document.querySelectorAll('.post-body, .onelogbox'); // Target both post types
        
        posts.forEach(function(post) {
            var images = Array.from(post.querySelectorAll('.imagelink'));
            if (images.length === 0) return;

            // Group consecutive images
            var groups = [];
            var currentGroup = [];

            images.forEach(function(img, index) {
                if (currentGroup.length === 0) {
                    currentGroup.push(img);
                } else {
                    var prevImg = currentGroup[currentGroup.length - 1];
                    // Check if they are virtually adjacent (ignoring BR and whitespace)
                    var node = prevImg.nextSibling;
                    var isAdjacent = false;
                    
                    while (node && node !== img) {
                        if (node.nodeType === 3 && node.textContent.trim() === '') {
                            // Empty text node, skip
                        } else if (node.tagName === 'BR') {
                            // BR tag, likely purely decorative between images, treat as adjacent
                            node.classList.add('grid-hidden-br'); // Hide this BR via CSS
                        } else {
                            // Found content (text or other tag), so NOT adjacent
                            break; 
                        }
                        
                        // If we reached the target image immediately after skips
                        if (node.nextSibling === img) {
                            isAdjacent = true;
                        }
                        node = node.nextSibling;
                    }
                    
                    // Direct sibling check (rare but possible)
                    if (prevImg.nextSibling === img) isAdjacent = true;

                    if (isAdjacent) {
                        currentGroup.push(img);
                    } else {
                        groups.push(currentGroup);
                        currentGroup = [img];
                    }
                }
            });
            if (currentGroup.length > 0) groups.push(currentGroup);

            // Apply classes based on group size
            groups.forEach(function(group) {
                var isGrid = group.length >= 2;
                group.forEach(function(img, idx) {
                    // Reset classes
                    img.classList.remove('single-image', 'grid-image', 'grid-first', 'grid-last', 'grid-odd', 'grid-even');
                    
                    if (isGrid) {
                        img.classList.add('grid-image');
                        if (idx % 2 === 0) img.classList.add('grid-odd'); // 1st, 3rd...
                        else img.classList.add('grid-even');              // 2nd, 4th...
                    } else {
                        img.classList.add('single-image');
                    }
                });
                
                // Add clearfix after the last image of a grid group
                if (isGrid) {
                    var lastImg = group[group.length - 1];
                    // Create or mark the spacer
                    // We can reuse the following BR if it exists, or insert a clearer
                    var nextNode = lastImg.nextSibling;
                    if (nextNode && nextNode.tagName === 'BR') {
                        nextNode.classList.add('grid-clear-br');
                    } else {
                        // Insert a phantom clearer if needed (for float clearing)
                        // But strictly, CSS 'grid-image' uses float, so we need to clear.
                        // We rely on CSS ::after on the parent or the next element clearing.
                        // Let's force a clear-fix span if text follows immediately
                        var spacer = document.createElement('div');
                        spacer.style.clear = 'both';
                        if (nextNode) {
                            lastImg.parentNode.insertBefore(spacer, nextNode);
                        } else {
                            lastImg.parentNode.appendChild(spacer);
                        }
                    }
                }
            });
        });
    }

    // Hide a single <br> right after a pict when the next node is plain text
    function hideSingleBrAfterImage() {
        var posts = document.querySelectorAll('.post-body, .onelogbox, .article-body');
        posts.forEach(function(post) {
            var images = post.querySelectorAll('.imagelink');
            images.forEach(function(imgLink) {
                var node = imgLink.nextSibling;
                while (node && node.nodeType === 3 && node.textContent.trim() === '') {
                    node = node.nextSibling;
                }
                if (!node || node.nodeName !== 'BR') return;

                var brNode = node;
                var next = brNode.nextSibling;
                while (next && next.nodeType === 3 && next.textContent.trim() === '') {
                    next = next.nextSibling;
                }
                if (!next || next.nodeName === 'BR') return;

                if (next.nodeType === 3 && next.textContent.trim() !== '') {
                    brNode.classList.add('grid-hidden-br');
                }
            });
        });
    }

    // Disable Autocomplete for all text/search inputs
    function disableAutocomplete() {
        var inputs = document.querySelectorAll('input[type="text"], input[type="search"]');
        inputs.forEach(function(input) {
            input.setAttribute('autocomplete', 'off');
        });
    }

    // Helper: Remove immediate sibling <br> (Tegalog extra spacing fix)
    function removeNextBr(element) {
        var next = element.nextSibling;
        if (next && next.nodeName === 'BR') {
            next.parentNode.removeChild(next);
        } else if (next && next.nodeType === 3 && next.textContent.trim() === '') {
            var nextNext = next.nextSibling;
            if (nextNext && nextNext.nodeName === 'BR') {
                nextNext.parentNode.removeChild(nextNext);
            }
        }
    }

    // =========================================================
    // Code Block Copy Button
    // =========================================================
    function initCodeCopyButtons() {
        var codeBlocks = document.querySelectorAll('code.decoration1');

        codeBlocks.forEach(function(codeEl) {
            // Skip if already wrapped
            if (codeEl.parentNode.classList && codeEl.parentNode.classList.contains('code-block-wrapper')) return;

            // Wrap the code element
            var wrapper = document.createElement('div');
            wrapper.className = 'code-block-wrapper';
            codeEl.parentNode.insertBefore(wrapper, codeEl);
            wrapper.appendChild(codeEl);

            // Create copy button
            var copyBtn = document.createElement('button');
            copyBtn.className = 'code-copy-btn';
            copyBtn.textContent = 'コピー';
            copyBtn.type = 'button';
            wrapper.appendChild(copyBtn);

            // Remove immediate sibling <br>
            removeNextBr(wrapper);

            // Click handler
            copyBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Copy to clipboard
                var text = codeEl.textContent || codeEl.innerText || '';
                navigator.clipboard.writeText(text).then(function() {
                    showCopyToast(e.clientX, e.clientY, 'コピー完了');
                }).catch(function(err) {
                    // Fallback for older browsers
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.cssText = 'position:fixed;opacity:0;';
                    document.body.appendChild(ta);
                    ta.select();
                    try {
                        document.execCommand('copy');
                        showCopyToast(e.clientX, e.clientY, 'コピー完了');
                    } catch (err2) {
                        showCopyToast(e.clientX, e.clientY, 'コピー失敗', true);
                    }
                    document.body.removeChild(ta);
                });
            });
        });
    }

    function showCopyToast(x, y, message, isError) {
        var toast = document.createElement('div');
        toast.className = 'copy-toast';
        toast.textContent = message;
        if (isError) {
            toast.style.background = '#ef4444';
        }
        toast.style.left = x + 'px';
        toast.style.top = y + 'px';
        document.body.appendChild(toast);

        // Remove after animation
        setTimeout(function() {
            toast.remove();
        }, 1500);
    }

    function cleanupDecorations() {
        // Cleanup for ul.decorationL
        var lists = document.querySelectorAll('ul.decorationL');
        lists.forEach(function(list) {
            removeNextBr(list);
        });

        // Cleanup for p.decorationF
        var bubbles = document.querySelectorAll('p.decorationF');
        bubbles.forEach(function(bubble) {
            var node1 = bubble.nextSibling;
            
            // Pattern 1: [Bubble] -> [BR] -> [Not BR]
            if (node1 && node1.nodeName === 'BR') {
                var node2 = node1.nextSibling;
                
                // If there's an empty text node between BRs, skip it to check the next real node
                if (node2 && node2.nodeType === 3 && node2.textContent.trim() === '') {
                    node2 = node2.nextSibling;
                }

                // If the second node is NOT a BR, it means it's a single return. Remove it.
                if (node2 && node2.nodeName !== 'BR') {
                    node1.parentNode.removeChild(node1);
                }
            } 
            // Pattern 2: [Bubble] -> [Whitespace] -> [BR] -> [Not BR]
            else if (node1 && node1.nodeType === 3 && node1.textContent.trim() === '') {
                var node2 = node1.nextSibling;
                if (node2 && node2.nodeName === 'BR') {
                    var node3 = node2.nextSibling;
                    if (node3 && node3.nodeType === 3 && node3.textContent.trim() === '') {
                        node3 = node3.nextSibling;
                    }
                    if (node3 && node3.nodeName !== 'BR') {
                        node2.parentNode.removeChild(node2);
                    }
                }
            }
        });
    }

    // Run format
    formatImageGrid();
    hideSingleBrAfterImage();
    disableAutocomplete();
    initCodeCopyButtons();
    cleanupDecorations();
    // Re-run on resize? Not needed for logic, only CSS handles resize.
})();
