// Population data points
const data = [
    [-300000, 0.2], [-200000, 0.3], [-100000, 0.5], [-70000, 0.8],
    [-50000, 1.5], [-30000, 3], [-10000, 6], [-8000, 8],
    [-5000, 20], [-3000, 45], [-1000, 72], [-500, 100],
    [1, 190], [500, 210], [1000, 310], [1200, 400],
    [1350, 370], [1500, 500], [1600, 580], [1700, 680],
    [1800, 990], [1850, 1260], [1900, 1650], [1927, 2000],
    [1950, 2500], [1960, 3000], [1975, 4000], [1987, 5000],
    [1999, 6000], [2011, 7000], [2022, 8000], [2026, 8200]
];

const markers = [
    { year: -70000, label: "Toba", color: "#c85050" },
    { year: -10000, label: "Agriculture", color: "#c8b450" },
    { year: 1, label: "Roman Peak", color: "#c8b450" },
    { year: 1350, label: "Black Death", color: "#c85050" },
    { year: 1760, label: "Industrial Rev", color: "#5078c8" },
    { year: 1804, label: "1 Billion", color: "#50c878" },
    { year: 1918, label: "Spanish Flu", color: "#c85050" },
    { year: 1927, label: "2 Billion", color: "#50c878" },
    { year: 1945, label: "Nuclear Age", color: "#c85050" },
    { year: 1975, label: "4 Billion", color: "#50c878" },
    { year: 2022, label: "8 Billion", color: "#50c878" }
];

const eras = [
    [-300000, -10000, "Paleolithic"],
    [-10000, -3000, "Neolithic"],
    [-3000, 500, "Ancient Era"],
    [500, 1500, "Medieval"],
    [1500, 1800, "Early Modern"],
    [1800, 1950, "Industrial Age"],
    [1950, 2100, "The Great Acceleration"]
];

function getEra(year) {
    for (const [start, end, name] of eras) {
        if (year >= start && year < end) return name;
    }
    return "Present";
}

function getPop(year) {
    for (let i = 0; i < data.length - 1; i++) {
        if (year >= data[i][0] && year <= data[i + 1][0]) {
            const t = (year - data[i][0]) / (data[i + 1][0] - data[i][0]);
            return data[i][1] + t * (data[i + 1][1] - data[i][1]);
        }
    }
    return data[data.length - 1][1];
}

function formatYear(y) {
    y = Math.round(y);
    if (y < -1000) return Math.round(Math.abs(y) / 1000) + "k BCE";
    if (y < 0) return Math.abs(y) + " BCE";
    return y + " CE";
}

function formatPop(p) {
    if (p < 1) return Math.round(p * 1000) + " thousand";
    if (p < 1000) return p.toFixed(1) + " million";
    return (p / 1000).toFixed(2) + " billion";
}

// Canvas setup
const canvas = document.getElementById('chart');
const ctx = canvas.getContext('2d');
const container = document.getElementById('chartContainer');

let W, H;
const padding = { top: 30, right: 40, bottom: 40, left: 70 };

function resize() {
    const rect = container.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    W = rect.width;
    H = rect.height;
    canvas.width = W * dpr;
    canvas.height = H * dpr;
    canvas.style.width = W + 'px';
    canvas.style.height = H + 'px';
    ctx.scale(dpr, dpr);
}

resize();
window.addEventListener('resize', () => { resize(); draw(); });

// View state
let viewXMin = -300000, viewXMax = 2100;
let viewYMin = 0, viewYMax = 9000;
let currentYear = -300000;
let animationComplete = false;
let animationId = null;
let animationStarted = false;
let animationPaused = false;
let pausedAt = 0;
let pauseOffset = 0;

function xToCanvas(x) {
    return padding.left + (x - viewXMin) / (viewXMax - viewXMin) * (W - padding.left - padding.right);
}

function yToCanvas(y) {
    return H - padding.bottom - (y - viewYMin) / (viewYMax - viewYMin) * (H - padding.top - padding.bottom);
}

function canvasToX(cx) {
    return viewXMin + (cx - padding.left) / (W - padding.left - padding.right) * (viewXMax - viewXMin);
}

function draw() {
    ctx.clearRect(0, 0, W, H);

    // Grid
    ctx.strokeStyle = 'rgba(255,255,255,0.03)';
    ctx.lineWidth = 1;

    // Y grid
    const ySteps = [0, 1000, 2000, 3000, 4000, 5000, 6000, 7000, 8000, 9000];
    for (const y of ySteps) {
        if (y >= viewYMin && y <= viewYMax) {
            const cy = yToCanvas(y);
            ctx.beginPath();
            ctx.moveTo(padding.left, cy);
            ctx.lineTo(W - padding.right, cy);
            ctx.stroke();
        }
    }

    // Y axis labels
    ctx.fillStyle = '#444';
    ctx.font = '11px system-ui';
    ctx.textAlign = 'right';
    for (const y of ySteps) {
        if (y >= viewYMin && y <= viewYMax) {
            const cy = yToCanvas(y);
            const label = y >= 1000 ? (y/1000) + 'B' : y + 'M';
            ctx.fillText(label, padding.left - 10, cy + 4);
        }
    }

    // X axis labels
    ctx.textAlign = 'center';
    const xRange = viewXMax - viewXMin;
    let xStep;
    if (xRange > 100000) xStep = 50000;
    else if (xRange > 10000) xStep = 5000;
    else if (xRange > 1000) xStep = 500;
    else xStep = 100;

    for (let x = Math.ceil(viewXMin / xStep) * xStep; x <= viewXMax; x += xStep) {
        const cx = xToCanvas(x);
        if (cx > padding.left && cx < W - padding.right) {
            ctx.fillText(formatYear(x), cx, H - padding.bottom + 20);
        }
    }

    // Draw markers in view
    for (const m of markers) {
        if (m.year >= viewXMin && m.year <= viewXMax && m.year <= currentYear) {
            const cx = xToCanvas(m.year);
            ctx.strokeStyle = m.color + '60';
            ctx.setLineDash([4, 4]);
            ctx.beginPath();
            ctx.moveTo(cx, padding.top);
            ctx.lineTo(cx, H - padding.bottom);
            ctx.stroke();
            ctx.setLineDash([]);

            ctx.fillStyle = m.color;
            ctx.font = '10px system-ui';
            ctx.save();
            ctx.translate(cx, padding.top - 5);
            ctx.rotate(-Math.PI / 4);
            ctx.fillText(m.label, 0, 0);
            ctx.restore();
        }
    }

    // Draw population line
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = 2;
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
    ctx.beginPath();

    let started = false;
    // Generate smooth points
    const step = (viewXMax - viewXMin) / 500;
    for (let x = viewXMin; x <= Math.min(viewXMax, currentYear); x += step) {
        const y = getPop(x);
        const cx = xToCanvas(x);
        const cy = yToCanvas(y);
        if (!started) {
            ctx.moveTo(cx, cy);
            started = true;
        } else {
            ctx.lineTo(cx, cy);
        }
    }
    // Make sure we end exactly at currentYear
    if (currentYear >= viewXMin && currentYear <= viewXMax) {
        const y = getPop(currentYear);
        ctx.lineTo(xToCanvas(currentYear), yToCanvas(y));
    }
    ctx.stroke();

    // Fill under curve
    if (started) {
        ctx.lineTo(xToCanvas(Math.min(currentYear, viewXMax)), yToCanvas(0));
        ctx.lineTo(xToCanvas(viewXMin), yToCanvas(0));
        ctx.closePath();
        ctx.fillStyle = 'rgba(255,255,255,0.02)';
        ctx.fill();
    }
}

function updateDisplay() {
    document.getElementById('yearDisplay').textContent = formatYear(currentYear);
    document.getElementById('popDisplay').textContent = formatPop(getPop(currentYear));
    document.getElementById('eraDisplay').textContent = getEra(currentYear);
}

// Animation with fixed viewports
const segments = [
    [-300000, -10000, 4000, -300000, 2100, 50],
    [-10000, 1000, 5000, -12000, 2100, 500],
    [1000, 1800, 5000, -1000, 2100, 1500],
    [1800, 1950, 4000, 1600, 2100, 3000],
    [1950, 2026, 7000, 1900, 2100, 9000]
];

let segmentIndex = 0;
let segmentStart = 0;

function setPlayBtn(label) {
    const btn = document.getElementById('playBtn');
    if (btn) btn.textContent = label;
}

function startAnimation() {
    if (animationId) cancelAnimationFrame(animationId);

    animationComplete = false;
    animationStarted = true;
    animationPaused = false;
    pauseOffset = 0;
    currentYear = -300000;
    segmentIndex = 0;
    document.getElementById('questionMark').style.opacity = '0';
    document.getElementById('timelineHint').style.opacity = '0.3';
    setPlayBtn('⏸ Pause');

    segmentStart = performance.now();

    viewXMin = -300000;
    viewXMax = 2100;
    viewYMax = 50;

    const bgVideo = document.getElementById('bgVideo');
    if (bgVideo) {
        bgVideo.currentTime = 0;
        bgVideo.play().catch(() => {});
    }

    animationId = requestAnimationFrame(animate);
}

function animate(now) {
    const seg = segments[segmentIndex];
    const segElapsed = now - segmentStart - pauseOffset;
    const segProgress = Math.min(segElapsed / seg[2], 1);

    const eased = segProgress < 0.5
        ? 2 * segProgress * segProgress
        : 1 - Math.pow(-2 * segProgress + 2, 2) / 2;

    currentYear = Math.round(seg[0] + eased * (seg[1] - seg[0]));

    viewXMin += (seg[3] - viewXMin) * 0.08;
    viewXMax += (seg[4] - viewXMax) * 0.08;
    viewYMax += (seg[5] - viewYMax) * 0.08;

    updateDisplay();
    draw();

    if (segProgress >= 1) {
        segmentIndex++;
        if (segmentIndex < segments.length) {
            segmentStart = now;
            pauseOffset = 0;
            animationId = requestAnimationFrame(animate);
        } else {
            animationComplete = true;
            animationStarted = false;
            currentYear = 2026;
            viewXMin = 1700;
            viewXMax = 2100;
            viewYMax = 9000;
            updateDisplay();
            draw();
            setPlayBtn('▶ Play');
            document.getElementById('timelineHint').style.opacity = '1';
            setTimeout(() => {
                document.getElementById('questionMark').style.opacity = '1';
            }, 500);
        }
    } else {
        animationId = requestAnimationFrame(animate);
    }
}

function toggleAnimation() {
    const bgVideo = document.getElementById('bgVideo');

    if (!animationStarted) {
        startAnimation();
        return;
    }

    if (animationPaused) {
        // Resume
        animationPaused = false;
        pauseOffset += performance.now() - pausedAt;
        setPlayBtn('⏸ Pause');
        if (bgVideo) bgVideo.play().catch(() => {});
        animationId = requestAnimationFrame(animate);
    } else {
        // Pause
        animationPaused = true;
        pausedAt = performance.now();
        if (animationId) cancelAnimationFrame(animationId);
        animationId = null;
        setPlayBtn('▶ Play');
        if (bgVideo) bgVideo.pause();
    }
}

// Dragging
let isDragging = false;
let dragStartX = 0;
let dragViewXMin = 0;
let dragViewXMax = 0;

container.addEventListener('mousedown', (e) => {
    if (!animationComplete) return;
    isDragging = true;
    container.classList.add('dragging');
    dragStartX = e.clientX;
    dragViewXMin = viewXMin;
    dragViewXMax = viewXMax;
});

document.addEventListener('mousemove', (e) => {
    if (!isDragging) return;
    const dx = e.clientX - dragStartX;
    const xRange = dragViewXMax - dragViewXMin;
    const pxToYear = xRange / (W - padding.left - padding.right);
    const shift = -dx * pxToYear;

    let newMin = Math.round(dragViewXMin + shift);
    let newMax = Math.round(dragViewXMax + shift);

    // Clamp
    if (newMin < -300000) {
        newMin = -300000;
        newMax = newMin + xRange;
    }
    if (newMax > 2100) {
        newMax = 2100;
        newMin = newMax - xRange;
    }

    viewXMin = newMin;
    viewXMax = newMax;

    // Update display for center
    const centerYear = Math.round((viewXMin + viewXMax) / 2);
    currentYear = 2026; // Keep full line visible
    document.getElementById('yearDisplay').textContent = formatYear(centerYear);
    document.getElementById('popDisplay').textContent = formatPop(getPop(centerYear));
    document.getElementById('eraDisplay').textContent = getEra(centerYear);

    // Question mark visibility
    document.getElementById('questionMark').style.opacity = viewXMax >= 2050 ? '1' : '0';

    draw();
});

document.addEventListener('mouseup', () => {
    isDragging = false;
    container.classList.remove('dragging');
});

// Touch support
container.addEventListener('touchstart', (e) => {
    if (!animationComplete) return;
    isDragging = true;
    dragStartX = e.touches[0].clientX;
    dragViewXMin = viewXMin;
    dragViewXMax = viewXMax;
});

document.addEventListener('touchmove', (e) => {
    if (!isDragging) return;
    const dx = e.touches[0].clientX - dragStartX;
    const xRange = dragViewXMax - dragViewXMin;
    const pxToYear = xRange / (W - padding.left - padding.right);
    const shift = -dx * pxToYear;

    let newMin = Math.round(dragViewXMin + shift);
    let newMax = Math.round(dragViewXMax + shift);

    if (newMin < -300000) { newMin = -300000; newMax = newMin + xRange; }
    if (newMax > 2100) { newMax = 2100; newMin = newMax - xRange; }

    viewXMin = newMin;
    viewXMax = newMax;

    const centerYear = Math.round((viewXMin + viewXMax) / 2);
    currentYear = 2026;
    document.getElementById('yearDisplay').textContent = formatYear(centerYear);
    document.getElementById('popDisplay').textContent = formatPop(getPop(centerYear));
    document.getElementById('eraDisplay').textContent = getEra(centerYear);

    document.getElementById('questionMark').style.opacity = viewXMax >= 2050 ? '1' : '0';

    draw();
});

document.addEventListener('touchend', () => { isDragging = false; });

// Initial draw
draw();

// ============================================
// AI Views - Tabbed Interface
// ============================================

let aiViewsData = null;
let currentAiIndex = 0;
let currentVersionIndex = 0;

function renderTabs() {
    const tabsContainer = document.getElementById('aiTabs');
    tabsContainer.innerHTML = '';

    aiViewsData.ais.forEach((ai, index) => {
        const tab = document.createElement('button');
        tab.className = 'ai-tab' + (index === currentAiIndex ? ' active' : '');
        tab.textContent = ai.name;
        tab.onclick = () => {
            currentAiIndex = index;
            currentVersionIndex = 0;
            renderTabs();
            renderContent();
            updateVersionNav();
        };
        tabsContainer.appendChild(tab);
    });
}

function renderContent() {
    const contentContainer = document.getElementById('aiContent');
    const ai = aiViewsData.ais[currentAiIndex];
    const version = ai.versions[currentVersionIndex];

    contentContainer.innerHTML = version.content
        .map(p => `<p>${p}</p>`)
        .join('');
}

function updateVersionNav() {
    const ai = aiViewsData.ais[currentAiIndex];
    const version = ai.versions[currentVersionIndex];
    const totalVersions = ai.versions.length;

    document.getElementById('versionInfo').textContent =
        `${version.model} · ${version.date}`;

    document.getElementById('prevVersion').disabled = currentVersionIndex >= totalVersions - 1;
    document.getElementById('nextVersion').disabled = currentVersionIndex <= 0;
}

// Load AI views from external JSON
fetch('ai_views.json')
    .then(response => response.json())
    .then(data => {
        aiViewsData = data;

        // Version navigation
        document.getElementById('prevVersion').onclick = () => {
            const ai = aiViewsData.ais[currentAiIndex];
            if (currentVersionIndex < ai.versions.length - 1) {
                currentVersionIndex++;
                renderContent();
                updateVersionNav();
            }
        };

        document.getElementById('nextVersion').onclick = () => {
            if (currentVersionIndex > 0) {
                currentVersionIndex--;
                renderContent();
                updateVersionNav();
            }
        };

        // Initialize
        renderTabs();
        renderContent();
        updateVersionNav();
    })
    .catch(err => {
        document.getElementById('aiContent').innerHTML =
            '<p style="color: #885555;">Failed to load AI views data.</p>';
        console.error('Error loading ai_views.json:', err);
    });

// === Scroll-reveal animations ===
// Fade + slide up on first viewport entry. Targets sections from .stats-row down.
// Bails on reduced-motion; bails on browsers without IntersectionObserver
// (no class added, content stays visible).
(function () {
    var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReduced || !('IntersectionObserver' in window)) return;

    var blockSelectors = [
        '.section-title',
        '.malthus-section',
        '.polycrisis-section',
        '.polycrisis-footer',
        '.robot-section',
        '.energy-slaves-svg',
        '.references .ref-category',
        '.footnote'
    ];

    var itemGroups = [
        { container: '.stats-row',       items: '.stat-box',         step: 0.06 },
        { container: '.perspective-box', items: '.perspective-stat', step: 0.08 },
        { container: '.info-grid',       items: '.info-card',        step: 0.09 },
        { container: '.polycrisis-grid', items: '.loop-card',        step: 0.07 }
    ];

    var targets = [];

    blockSelectors.forEach(function (sel) {
        document.querySelectorAll(sel).forEach(function (el) {
            el.classList.add('scroll-reveal');
            targets.push(el);
        });
    });

    itemGroups.forEach(function (group) {
        document.querySelectorAll(group.container).forEach(function (container) {
            var items = container.querySelectorAll(group.items);
            items.forEach(function (item, i) {
                item.classList.add('scroll-reveal');
                item.style.transitionDelay = (i * group.step).toFixed(2) + 's';
                targets.push(item);
            });
        });
    });

    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    targets.forEach(function (t) { io.observe(t); });
})();
