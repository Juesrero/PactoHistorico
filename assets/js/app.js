(() => {
    'use strict';

    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                const firstInvalid = form.querySelector(':invalid');
                if (firstInvalid && typeof firstInvalid.focus === 'function') {
                    firstInvalid.focus();
                }
            }
            form.classList.add('was-validated');
        }, false);
    });

    document.querySelectorAll('input[type="text"], textarea').forEach((element) => {
        element.addEventListener('blur', () => {
            element.value = element.value.trim();
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm') || 'Confirma continuar con esta accion?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    if (window.location.hash === '#agregar-asistencia') {
        const searchInput = document.getElementById('person_q');
        if (searchInput) {
            setTimeout(() => {
                searchInput.focus({ preventScroll: true });
                searchInput.select();
            }, 0);
        }
    }

    const parseJsonDataset = (value, fallback = []) => {
        try {
            const parsed = JSON.parse(value || '[]');
            return Array.isArray(parsed) ? parsed : fallback;
        } catch (error) {
            return fallback;
        }
    };

    const defaultBarColors = ['#1c3587', '#ff9c00', '#00a93f', '#ff1a1f', '#a52e94'];
    const defaultDonutColors = ['#1c3587', '#ff1a1f', '#ff9c00', '#00a93f', '#a52e94'];

    const resolvePalette = (canvas, fallback) => {
        const palette = parseJsonDataset(canvas.dataset.chartPalette, fallback).filter((value) => typeof value === 'string' && value.trim() !== '');
        return palette.length > 0 ? palette : fallback;
    };

    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

    const hexToRgb = (hex) => {
        const clean = String(hex || '').replace('#', '').trim();
        if (!/^[0-9a-fA-F]{6}$/.test(clean)) {
            return null;
        }

        return {
            r: parseInt(clean.slice(0, 2), 16),
            g: parseInt(clean.slice(2, 4), 16),
            b: parseInt(clean.slice(4, 6), 16),
        };
    };

    const rgbToHex = ({ r, g, b }) => {
        const toHex = (value) => clamp(Math.round(value), 0, 255).toString(16).padStart(2, '0');
        return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
    };

    const mixColor = (hex, targetHex, ratio) => {
        const source = hexToRgb(hex);
        const target = hexToRgb(targetHex);
        if (!source || !target) {
            return hex;
        }

        const safeRatio = clamp(ratio, 0, 1);
        return rgbToHex({
            r: source.r + (target.r - source.r) * safeRatio,
            g: source.g + (target.g - source.g) * safeRatio,
            b: source.b + (target.b - source.b) * safeRatio,
        });
    };

    const ensurePaletteSize = (palette, desiredSize) => {
        if (!Array.isArray(palette) || palette.length === 0) {
            return [];
        }

        const result = [...palette];
        const variants = [0.18, 0.32, 0.14, 0.26];
        let round = 0;

        while (result.length < desiredSize) {
            palette.forEach((color, index) => {
                if (result.length >= desiredSize) {
                    return;
                }

                const ratio = variants[(round + index) % variants.length];
                const target = round % 2 === 0 ? '#ffffff' : '#0f172a';
                result.push(mixColor(color, target, ratio));
            });

            round += 1;
        }

        return result;
    };

    const setupCanvas = (canvas) => {
        const rect = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        const width = Math.max(Math.floor(rect.width), 280);
        const height = Math.max(Math.floor(rect.height), 220);

        canvas.width = Math.floor(width * ratio);
        canvas.height = Math.floor(height * ratio);

        const ctx = canvas.getContext('2d');
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

        return { ctx, width, height };
    };

    const drawEmptyState = (ctx, width, height, text) => {
        ctx.clearRect(0, 0, width, height);
        ctx.fillStyle = '#6b7280';
        ctx.font = '14px Segoe UI';
        ctx.textAlign = 'center';
        ctx.fillText(text, width / 2, height / 2);
        ctx.canvas.__chartHoverData = { type: 'none', regions: [] };
    };

    const getChartTooltip = () => {
        let tooltip = document.querySelector('.chart-hover-tooltip');
        if (tooltip) {
            return tooltip;
        }

        tooltip = document.createElement('div');
        tooltip.className = 'chart-hover-tooltip';
        document.body.appendChild(tooltip);
        return tooltip;
    };

    const hideChartTooltip = () => {
        const tooltip = document.querySelector('.chart-hover-tooltip');
        if (!tooltip) {
            return;
        }

        tooltip.classList.remove('is-visible');
    };

    const showChartTooltip = (event, region) => {
        const tooltip = getChartTooltip();
        const valueText = Number.isFinite(region.value) ? `${region.value} personas` : '';
        const percentageText = Number.isFinite(region.percentage) ? `${region.percentage.toFixed(1)}%` : '';
        const metaText = [valueText, percentageText].filter(Boolean).join(' | ');

        tooltip.innerHTML = `
            <div class="chart-hover-tooltip-title">${region.label || 'Dato'}</div>
            <div class="chart-hover-tooltip-meta">${metaText}</div>
        `;

        tooltip.classList.add('is-visible');

        const offset = 14;
        const rect = tooltip.getBoundingClientRect();
        const left = Math.min(window.innerWidth - rect.width - 12, event.clientX + offset);
        const top = Math.max(12, event.clientY - rect.height - offset);

        tooltip.style.left = `${Math.max(12, left)}px`;
        tooltip.style.top = `${top}px`;
    };

    const roundedRectPath = (ctx, x, y, width, height, radius) => {
        const safeRadius = Math.max(0, Math.min(radius, width / 2, height / 2));

        ctx.beginPath();
        ctx.moveTo(x + safeRadius, y);
        ctx.lineTo(x + width - safeRadius, y);
        ctx.arcTo(x + width, y, x + width, y + safeRadius, safeRadius);
        ctx.lineTo(x + width, y + height - safeRadius);
        ctx.arcTo(x + width, y + height, x + width - safeRadius, y + height, safeRadius);
        ctx.lineTo(x + safeRadius, y + height);
        ctx.arcTo(x, y + height, x, y + height - safeRadius, safeRadius);
        ctx.lineTo(x, y + safeRadius);
        ctx.arcTo(x, y, x + safeRadius, y, safeRadius);
        ctx.closePath();
    };

    const drawBarChart = (canvas, labels, values, emptyText = 'Sin datos disponibles.') => {
        const { ctx, width, height } = setupCanvas(canvas);
        const palette = ensurePaletteSize(resolvePalette(canvas, defaultBarColors), labels.length);
        const total = values.reduce((acc, current) => acc + Math.max(0, Number(current || 0)), 0);

        if (labels.length === 0 || values.length === 0) {
            drawEmptyState(ctx, width, height, emptyText);
            return;
        }

        const top = 20;
        const right = 14;
        const bottom = 52;
        const left = 38;
        const innerWidth = width - left - right;
        const innerHeight = height - top - bottom;

        const maxValue = Math.max(1, ...values);
        const tickCount = 4;

        ctx.clearRect(0, 0, width, height);

        // Grid + y ticks
        ctx.strokeStyle = '#e5e7eb';
        ctx.fillStyle = '#6b7280';
        ctx.font = '11px Segoe UI';
        ctx.textAlign = 'right';

        for (let i = 0; i <= tickCount; i += 1) {
            const y = top + (innerHeight / tickCount) * i;
            ctx.beginPath();
            ctx.moveTo(left, y);
            ctx.lineTo(width - right, y);
            ctx.stroke();

            const valueTick = Math.round(maxValue - (maxValue / tickCount) * i);
            ctx.fillText(String(valueTick), left - 6, y + 3);
        }

        const barCount = labels.length;
        const gap = 10;
        const barWidth = Math.max(16, (innerWidth - gap * (barCount - 1)) / barCount);
        const hoverRegions = [];

        for (let i = 0; i < barCount; i += 1) {
            const value = Math.max(0, Number(values[i] || 0));
            const x = left + i * (barWidth + gap);
            const barHeight = (value / maxValue) * innerHeight;
            const y = top + innerHeight - barHeight;

            ctx.fillStyle = palette[i % palette.length];
            ctx.fillRect(x, y, barWidth, barHeight);

            ctx.fillStyle = '#1f2937';
            ctx.textAlign = 'center';
            ctx.font = '11px Segoe UI';
            ctx.fillText(String(value), x + barWidth / 2, y - 6);

            const rawLabel = String(labels[i] || '');
            const label = rawLabel.length > 14 ? `${rawLabel.slice(0, 14)}...` : rawLabel;
            ctx.fillStyle = '#4b5563';
            ctx.font = '10px Segoe UI';
            ctx.fillText(label, x + barWidth / 2, height - 16);

            hoverRegions.push({
                shape: 'rect',
                x,
                y,
                width: barWidth,
                height: barHeight,
                label: rawLabel,
                value,
                percentage: total > 0 ? (value / total) * 100 : 0,
            });
        }

        // axis line
        ctx.strokeStyle = '#cbd5e1';
        ctx.beginPath();
        ctx.moveTo(left, top + innerHeight);
        ctx.lineTo(width - right, top + innerHeight);
        ctx.stroke();
        canvas.__chartHoverData = { type: 'rects', regions: hoverRegions };
    };

    const drawHorizontalBarChart = (canvas, labels, values, emptyText = 'Sin datos disponibles.') => {
        const { ctx, width, height } = setupCanvas(canvas);
        const palette = ensurePaletteSize(resolvePalette(canvas, defaultBarColors), labels.length);
        const total = values.reduce((acc, current) => acc + Math.max(0, Number(current || 0)), 0);

        if (labels.length === 0 || values.length === 0) {
            drawEmptyState(ctx, width, height, emptyText);
            return;
        }

        const maxLabelLength = labels.reduce((max, label) => Math.max(max, String(label || '').length), 0);
        const left = Math.min(170, Math.max(92, maxLabelLength * 6.2));
        const right = 34;
        const top = 16;
        const bottom = 12;
        const innerWidth = width - left - right;
        const innerHeight = height - top - bottom;
        const barCount = labels.length;
        const rowHeight = innerHeight / Math.max(barCount, 1);
        const barThickness = Math.min(28, Math.max(14, rowHeight * 0.58));
        const maxValue = Math.max(1, ...values.map((item) => Math.max(0, Number(item || 0))));
        const hoverRegions = [];

        ctx.clearRect(0, 0, width, height);

        for (let i = 0; i < barCount; i += 1) {
            const value = Math.max(0, Number(values[i] || 0));
            const yCenter = top + rowHeight * i + rowHeight / 2;
            const barWidth = (value / maxValue) * innerWidth;
            const y = yCenter - barThickness / 2;
            const radius = Math.min(8, barThickness / 2);
            const rawLabel = String(labels[i] || '');
            const label = rawLabel.length > 24 ? `${rawLabel.slice(0, 24)}...` : rawLabel;

            ctx.fillStyle = '#eef3f8';
            roundedRectPath(ctx, left, y, innerWidth, barThickness, radius);
            ctx.fill();

            ctx.fillStyle = palette[i % palette.length];
            roundedRectPath(ctx, left, y, Math.max(barWidth, 2), barThickness, radius);
            ctx.fill();

            ctx.fillStyle = '#334155';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            ctx.font = '11px Segoe UI';
            ctx.fillText(label, left - 10, yCenter);

            ctx.fillStyle = '#0f172a';
            ctx.textAlign = 'left';
            ctx.font = '600 11px Segoe UI';
            ctx.fillText(String(value), left + Math.min(barWidth + 8, innerWidth + 6), yCenter);

            hoverRegions.push({
                shape: 'rect',
                x: left,
                y,
                width: Math.max(barWidth, 2),
                height: barThickness,
                label: rawLabel,
                value,
                percentage: total > 0 ? (value / total) * 100 : 0,
            });
        }

        canvas.__chartHoverData = { type: 'rects', regions: hoverRegions };
    };

    const drawDonutChart = (canvas, labels, values, emptyText = 'Sin datos disponibles.') => {
        const { ctx, width, height } = setupCanvas(canvas);
        const numericValues = values.map((item) => Math.max(0, Number(item || 0)));
        const total = numericValues.reduce((acc, current) => acc + current, 0);
        const colors = ensurePaletteSize(resolvePalette(canvas, defaultDonutColors), labels.length);

        if (labels.length === 0 || numericValues.length === 0 || total === 0) {
            drawEmptyState(ctx, width, height, emptyText);
            return;
        }

        ctx.clearRect(0, 0, width, height);

        const centerX = width / 2;
        const centerY = height / 2;
        const radius = Math.min(width, height) * 0.34;
        const innerRadius = radius * 0.58;
        const hoverRegions = [];
        let start = -Math.PI / 2;
        for (let i = 0; i < numericValues.length; i += 1) {
            const sliceValue = numericValues[i];
            if (sliceValue <= 0) {
                continue;
            }

            const angle = (sliceValue / total) * Math.PI * 2;
            const end = start + angle;

            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, start, end);
            ctx.closePath();
            ctx.fillStyle = colors[i % colors.length];
            ctx.fill();

            hoverRegions.push({
                shape: 'arc',
                centerX,
                centerY,
                innerRadius,
                radius,
                start,
                end,
                label: String(labels[i] || ''),
                value: sliceValue,
                percentage: total > 0 ? (sliceValue / total) * 100 : 0,
            });

            start = end;
        }

        ctx.beginPath();
        ctx.arc(centerX, centerY, innerRadius, 0, Math.PI * 2);
        ctx.fillStyle = '#ffffff';
        ctx.fill();

        ctx.fillStyle = '#0f172a';
        ctx.textAlign = 'center';
        ctx.font = '600 17px Segoe UI';
        ctx.fillText(String(total), centerX, centerY - 4);

        ctx.fillStyle = '#64748b';
        ctx.font = '12px Segoe UI';
        ctx.fillText('personas', centerX, centerY + 14);
        canvas.__chartHoverData = { type: 'arcs', regions: hoverRegions };
    };

    const normalizeAngle = (angle) => {
        const fullTurn = Math.PI * 2;
        let normalized = angle % fullTurn;
        if (normalized < 0) {
            normalized += fullTurn;
        }
        return normalized;
    };

    const pointInArc = (x, y, region) => {
        const dx = x - region.centerX;
        const dy = y - region.centerY;
        const distance = Math.sqrt((dx * dx) + (dy * dy));

        if (distance < region.innerRadius || distance > region.radius) {
            return false;
        }

        const angle = normalizeAngle(Math.atan2(dy, dx));
        const start = normalizeAngle(region.start);
        const end = normalizeAngle(region.end);

        if (start <= end) {
            return angle >= start && angle <= end;
        }

        return angle >= start || angle <= end;
    };

    const bindChartHover = (canvas) => {
        if (canvas.dataset.hoverBound === '1') {
            return;
        }

        canvas.dataset.hoverBound = '1';

        canvas.addEventListener('mousemove', (event) => {
            const hoverData = canvas.__chartHoverData;
            if (!hoverData || !Array.isArray(hoverData.regions) || hoverData.regions.length === 0) {
                canvas.style.cursor = 'default';
                hideChartTooltip();
                return;
            }

            const rect = canvas.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;
            let region = null;

            if (hoverData.type === 'rects') {
                region = hoverData.regions.find((item) => x >= item.x && x <= item.x + item.width && y >= item.y && y <= item.y + item.height) || null;
            } else if (hoverData.type === 'arcs') {
                region = hoverData.regions.find((item) => pointInArc(x, y, item)) || null;
            }

            if (!region) {
                canvas.style.cursor = 'default';
                hideChartTooltip();
                return;
            }

            canvas.style.cursor = 'pointer';
            showChartTooltip(event, region);
        });

        canvas.addEventListener('mouseleave', () => {
            canvas.style.cursor = 'default';
            hideChartTooltip();
        });
    };

    const initCharts = () => {
        const canvases = Array.from(document.querySelectorAll('.chart-canvas')).filter((canvas) => {
            if (canvas.dataset.chartType) {
                return true;
            }

            return ['chartAsistenciasReunion', 'chartPersonasDistribucion'].includes(canvas.id);
        });

        if (canvases.length === 0) {
            return;
        }

        const resolveChartType = (canvas) => {
            if (canvas.dataset.chartType) {
                return canvas.dataset.chartType;
            }

            if (canvas.id === 'chartPersonasDistribucion') {
                return 'donut';
            }

            return 'bar';
        };

        const drawAll = () => {
            canvases.forEach((canvas) => {
                const labels = parseJsonDataset(canvas.dataset.labels);
                const values = parseJsonDataset(canvas.dataset.values);
                const emptyText = canvas.dataset.emptyText || 'Sin datos disponibles.';
                const chartType = resolveChartType(canvas);

                if (chartType === 'donut') {
                    drawDonutChart(canvas, labels, values, emptyText);
                    bindChartHover(canvas);
                    return;
                }

                if (chartType === 'bar-horizontal') {
                    drawHorizontalBarChart(canvas, labels, values, emptyText);
                    bindChartHover(canvas);
                    return;
                }

                drawBarChart(canvas, labels, values, emptyText);
                bindChartHover(canvas);
            });
        };

        drawAll();

        let resizeTimeout = null;
        window.addEventListener('resize', () => {
            window.clearTimeout(resizeTimeout);
            resizeTimeout = window.setTimeout(drawAll, 120);
        });
    };

    initCharts();
})();
