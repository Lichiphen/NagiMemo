// NagiMemo v1.1.8
// Copyright (c) 2026 Lichiphen
// Licensed under the MIT License
// https://github.com/Lichiphen/NagiMemo/blob/main/LICENSE
(function() {
    'use strict';

    function initHeatmap(heatmap) {
        if (!heatmap || heatmap.dataset.monthOverlayReady === '1') {
            return;
        }

        var weeks = Array.prototype.slice.call(heatmap.querySelectorAll('.chmWeek'));
        if (!weeks.length) {
            return;
        }

        var layer = document.createElement('div');
        layer.className = 'calendarheatmap-months';
        heatmap.appendChild(layer);
        heatmap.classList.add('is-enhanced');
        heatmap.dataset.monthOverlayReady = '1';

        var frameId = 0;

        function renderMonthLabels() {
            frameId = 0;
            layer.textContent = '';

            var previousRight = -Infinity;
            var minimumGap = 4;
            var renderedLabels = [];

            weeks.forEach(function(week) {
                var head = week.querySelector('.weekhead');
                if (!head) {
                    return;
                }

                var text = head.textContent.replace(/\s+/g, ' ').trim();
                if (text === '') {
                    return;
                }

                var label = document.createElement('span');
                label.textContent = text;
                layer.appendChild(label);

                var left = week.offsetLeft;
                var width = label.offsetWidth;

                if (left < previousRight + minimumGap) {
                    left = previousRight + minimumGap;
                }

                label.style.left = left + 'px';
                previousRight = left + width;
                renderedLabels.push(label);
            });

            var layerWidth = heatmap.scrollWidth;
            if (renderedLabels.length > 0) {
                var lastLabel = renderedLabels[renderedLabels.length - 1];
                var lastLeft = parseFloat(lastLabel.style.left) || 0;
                layerWidth = Math.max(layerWidth, lastLeft + lastLabel.offsetWidth + minimumGap);
            }

            layer.style.width = layerWidth + 'px';
        }

        function scheduleRender() {
            if (frameId !== 0) {
                window.cancelAnimationFrame(frameId);
            }
            frameId = window.requestAnimationFrame(renderMonthLabels);
        }

        if (window.ResizeObserver) {
            var observer = new ResizeObserver(scheduleRender);
            observer.observe(heatmap);
        }

        window.addEventListener('resize', scheduleRender, { passive: true });
        scheduleRender();
    }

    function initAllHeatmaps() {
        var heatmaps = document.querySelectorAll('.footer-heatmap .calendarheatmap');
        Array.prototype.forEach.call(heatmaps, initHeatmap);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllHeatmaps);
    } else {
        initAllHeatmaps();
    }
})();
