// StoreTrack — Main JS

// ── Modal helpers ──
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

// ── Flash message auto-dismiss ──
const flash = document.querySelector('.flash-msg');
if (flash) {
    setTimeout(() => {
        flash.style.transition = 'opacity 0.4s';
        flash.style.opacity = '0';
        setTimeout(() => flash.remove(), 400);
    }, 4000);
}

// ── Table search filter ──
const searchInput = document.getElementById('tableSearch');
if (searchInput) {
    searchInput.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.data-table tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}

// ── Confirm delete ──
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function (e) {
        if (!confirm('Are you sure you want to delete this item?')) {
            e.preventDefault();
        }
    });
});

// ── Record Sale: populate price from selection ──
const itemSelect = document.getElementById('item_select');
const salePriceInput = document.getElementById('sale_price');
const costPriceInput = document.getElementById('cost_price_hidden');
const stockInfo = document.getElementById('stock_info');

if (itemSelect) {
    itemSelect.addEventListener('change', function () {
        const opt = this.selectedOptions[0];
        if (opt && opt.dataset.price) {
            salePriceInput.value = opt.dataset.price;
            if (costPriceInput) costPriceInput.value = opt.dataset.cost;
            if (stockInfo) stockInfo.textContent = `Stock available: ${opt.dataset.stock}`;
        } else {
            if (salePriceInput) salePriceInput.value = '';
            if (stockInfo) stockInfo.textContent = '';
        }
    });
}

// ── Profit preview on record sale ──
function updateProfitPreview() {
    const opt = itemSelect ? itemSelect.selectedOptions[0] : null;
    if (!opt || !opt.dataset.cost) return;
    const qty = parseInt(document.getElementById('quantity')?.value) || 0;
    const price = parseFloat(salePriceInput?.value) || 0;
    const cost = parseFloat(opt.dataset.cost) || 0;
    const profit = (price - cost) * qty;
    const el = document.getElementById('profit_preview');
    if (el) {
        el.textContent = `Estimated Profit: ₹${profit.toFixed(0)}`;
        el.style.color = profit >= 0 ? 'var(--green)' : '#DC2626';
    }
}

document.getElementById('quantity')?.addEventListener('input', updateProfitPreview);
salePriceInput?.addEventListener('input', updateProfitPreview);

// ── Bar chart (Profit Report) ──
function drawBarChart(canvasId, labels, data, color = '#E5521A') {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.width = canvas.offsetWidth;
    const H = canvas.height = canvas.offsetHeight;
    const padding = { top: 20, right: 20, bottom: 40, left: 60 };
    const chartW = W - padding.left - padding.right;
    const chartH = H - padding.top - padding.bottom;
    const max = Math.max(...data, 1);
    const barW = (chartW / data.length) * 0.55;
    const gap = chartW / data.length;

    ctx.clearRect(0, 0, W, H);

    // Grid lines
    ctx.strokeStyle = '#E8E2DB';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
        const y = padding.top + (chartH / 4) * i;
        ctx.beginPath();
        ctx.moveTo(padding.left, y);
        ctx.lineTo(W - padding.right, y);
        ctx.stroke();
        ctx.fillStyle = '#9A9A9A';
        ctx.font = '11px DM Sans, sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText('₹' + Math.round(max - (max / 4) * i).toLocaleString(), padding.left - 6, y + 4);
    }

    // Bars
    data.forEach((val, i) => {
        const barH = (val / max) * chartH;
        const x = padding.left + gap * i + (gap - barW) / 2;
        const y = padding.top + chartH - barH;

        // Bar gradient
        const grad = ctx.createLinearGradient(0, y, 0, y + barH);
        grad.addColorStop(0, color);
        grad.addColorStop(1, color + '99');
        ctx.fillStyle = grad;

        // Rounded top
        const r = Math.min(6, barW / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + barW - r, y);
        ctx.quadraticCurveTo(x + barW, y, x + barW, y + r);
        ctx.lineTo(x + barW, y + barH);
        ctx.lineTo(x, y + barH);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.fill();

        // Value label
        ctx.fillStyle = '#1A1A1A';
        ctx.font = '600 11px DM Sans, sans-serif';
        ctx.textAlign = 'center';
        if (barH > 20) ctx.fillText('₹' + val.toLocaleString(), x + barW / 2, y - 6);

        // X label
        ctx.fillStyle = '#6B6B6B';
        ctx.font = '11px DM Sans, sans-serif';
        ctx.fillText(labels[i], x + barW / 2, H - 6);
    });
}

// Init charts if on profit page
window.addEventListener('load', () => {
    if (typeof chartData !== 'undefined') {
        drawBarChart('revenueChart', chartData.labels, chartData.revenue, '#E5521A');
        drawBarChart('profitChart', chartData.labels, chartData.profit, '#22C55E');
    }
});