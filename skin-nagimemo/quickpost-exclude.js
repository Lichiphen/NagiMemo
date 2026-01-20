/**
 * QUICKPOST Recent Image NagiSwipe Excluder
 * Prevents NagiSwipe from triggering on recent image thumbnails in QUICKPOST.
 */
(function() {
    // Only run if the user is logged in (body has .loggedin-YES)
    if (!document.body.classList.contains('loggedin-YES')) return;

    function excludeThumbnails() {
        const thumbnails = document.querySelectorAll('.recentimginsert');
        thumbnails.forEach(link => {
            // Remove data-ns-index which NagiSwipe uses to identify items
            if (link.hasAttribute('data-ns-index')) {
                link.removeAttribute('data-ns-index');
            }
            
            // Add a flag to potentially help NagiSwipe ignore it if the above isn't enough
            link.classList.add('ns-exclude');

            // Forcefully stop propagation for click events to prevent NagiSwipe's listener (on document) from catching it
            // However, we must ensure Tegalog's internal insertion logic still works.

            // Prevent dragging to avoid accidental re-upload
            link.setAttribute('draggable', 'false');
            const img = link.querySelector('img');
            if (img) {
                img.setAttribute('draggable', 'false');
                img.style.webkitUserDrag = 'none';
                img.style.userDrag = 'none';
            }
        });
    }

    // Capture dragstart to be absolute sure
    document.addEventListener('dragstart', (e) => {
        if (e.target.closest('.recentimginsert')) {
            e.preventDefault();
        }
    }, true);

    // Run periodically or on specific triggers because these thumbnails might be loaded dynamically
    const observer = new MutationObserver((mutations) => {
        excludeThumbnails();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Initial run
    excludeThumbnails();
})();
