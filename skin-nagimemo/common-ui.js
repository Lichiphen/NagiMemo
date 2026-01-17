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

    // Run format
    formatImageGrid();
    // Re-run on resize? Not needed for logic, only CSS handles resize.
})();
