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
    };

    const drawBarChart = (canvas, labels, values, emptyText = 'Sin datos disponibles.') => {
        const { ctx, width, height } = setupCanvas(canvas);

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

        for (let i = 0; i < barCount; i += 1) {
            const value = Math.max(0, Number(values[i] || 0));
            const x = left + i * (barWidth + gap);
            const barHeight = (value / maxValue) * innerHeight;
            const y = top + innerHeight - barHeight;

            ctx.fillStyle = '#0f4c81';
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
        }

        // axis line
        ctx.strokeStyle = '#cbd5e1';
        ctx.beginPath();
        ctx.moveTo(left, top + innerHeight);
        ctx.lineTo(width - right, top + innerHeight);
        ctx.stroke();
    };

    const drawDonutChart = (canvas, labels, values, emptyText = 'Sin datos disponibles.') => {
        const { ctx, width, height } = setupCanvas(canvas);
        const numericValues = values.map((item) => Math.max(0, Number(item || 0)));
        const total = numericValues.reduce((acc, current) => acc + current, 0);

        if (labels.length === 0 || numericValues.length === 0 || total === 0) {
            drawEmptyState(ctx, width, height, emptyText);
            return;
        }

        ctx.clearRect(0, 0, width, height);

        const centerX = width / 2;
        const centerY = height / 2;
        const radius = Math.min(width, height) * 0.34;
        const innerRadius = radius * 0.58;
        const colors = ['#198754', '#94a3b8', '#0f4c81', '#f59e0b'];

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
                    return;
                }

                drawBarChart(canvas, labels, values, emptyText);
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
