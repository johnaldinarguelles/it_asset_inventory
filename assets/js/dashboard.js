(function () {
  let charts = [];

  function isDark() {
    return document.documentElement.getAttribute('data-theme') === 'dark';
  }

  function theme() {
    return window.chartTheme ? window.chartTheme() : {
      text: isDark() ? '#94a3b8' : '#667085',
      grid: { color: isDark() ? 'rgba(148,163,184,.12)' : 'rgba(16,24,40,.06)' },
      tooltipBg: isDark() ? '#0f172a' : '#101828'
    };
  }

  function showEmptyState(canvas, show) {
    const box = canvas.closest('.chart-box');
    if (!box) return;
    let empty = box.querySelector('.chart-empty');
    if (show) {
      canvas.style.visibility = 'hidden';
      if (!empty) {
        empty = document.createElement('div');
        empty.className = 'chart-empty';
        empty.innerHTML = "<i class='bi bi-bar-chart'></i><span>No data yet</span>";
        box.appendChild(empty);
      }
    } else {
      canvas.style.visibility = 'visible';
      if (empty) empty.remove();
    }
  }

  function hasAny(...arrays) {
    return arrays.some(a => (a || []).some(v => Number(v) > 0));
  }

  function destroyAll() {
    charts.forEach(c => c && c.destroy());
    charts = [];
  }

  function buildCharts(data) {
    const t = theme();
    Chart.defaults.font.family = 'Inter, Arial, sans-serif';
    Chart.defaults.color = t.text;

    const blue = '#2563eb', orange = '#f97316', green = '#16a34a', red = '#dc2626', cyan = '#06b6d4';

    const monthlyCanvas = document.getElementById('monthlyChart');
    const monthlyHasData = hasAny(data.received, data.issued, data.returned);
    showEmptyState(monthlyCanvas, !monthlyHasData);
    if (monthlyHasData) {
      charts.push(new Chart(monthlyCanvas, {
        type: 'bar',
        data: {
          labels: data.labels,
          datasets: [
            { label: 'Received', data: data.received, backgroundColor: green, borderRadius: 6, maxBarThickness: 28 },
            { label: 'Issued', data: data.issued, backgroundColor: orange, borderRadius: 6, maxBarThickness: 28 },
            { label: 'Returned', data: data.returned, backgroundColor: cyan, borderRadius: 6, maxBarThickness: 28 }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8, color: t.text } },
            tooltip: {
              backgroundColor: t.tooltipBg, padding: 12, cornerRadius: 8,
              callbacks: { label: c => ` ${c.dataset.label}: ${c.parsed.y.toLocaleString()}` }
            }
          },
          scales: {
            x: { grid: { display: false }, ticks: { color: t.text } },
            y: { beginAtZero: true, grid: t.grid, ticks: { precision: 0, color: t.text, callback: v => v.toLocaleString() } }
          }
        }
      }));
    }

    const statusCanvas = document.getElementById('statusChart');
    const statusTotal = (data.status || []).reduce((sum, s) => sum + Number(s.c), 0);
    showEmptyState(statusCanvas, statusTotal === 0);
    if (statusTotal > 0) {
      const statusColors = { 'Available': green, 'Low Stock': orange, 'Out of Stock': red, 'Issued': blue };
      charts.push(new Chart(statusCanvas, {
        type: 'doughnut',
        data: {
          labels: data.status.map(s => s.s),
          datasets: [{ data: data.status.map(s => s.c), backgroundColor: data.status.map(s => statusColors[s.s] || '#94a3b8'), borderWidth: 2, borderColor: isDark() ? '#1e293b' : '#fff' }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '62%',
          plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 14, color: t.text } },
            tooltip: {
              backgroundColor: t.tooltipBg, padding: 12, cornerRadius: 8,
              callbacks: { label: c => ` ${c.label}: ${c.parsed.toLocaleString()} (${Math.round(c.parsed / statusTotal * 100)}%)` }
            }
          }
        }
      }));
    }

    function horizontal(canvasId, rows, color) {
      const canvas = document.getElementById(canvasId);
      const has = (rows || []).some(r => Number(r.q) > 0);
      showEmptyState(canvas, !has);
      if (!has) return;
      charts.push(new Chart(canvas, {
        type: 'bar',
        data: {
          labels: rows.map(r => r.item_description),
          datasets: [{ data: rows.map(r => r.q), backgroundColor: color, borderRadius: 6, maxBarThickness: 18 }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { backgroundColor: t.tooltipBg, padding: 12, cornerRadius: 8, callbacks: { label: c => ` ${c.parsed.x.toLocaleString()} qty` } }
          },
          scales: {
            x: { beginAtZero: true, grid: t.grid, ticks: { precision: 0, color: t.text, callback: v => v.toLocaleString() } },
            y: { grid: { display: false }, ticks: { color: t.text } }
          }
        }
      }));
    }

    horizontal('issuedChart', data.topIssued, orange);
    horizontal('receivedChart', data.topReceived, green);

    window.dashboardCharts = { instances: charts };
  }

  window.addEventListener('load', function () {
    const data = window.chartData;
    if (!data || !window.Chart) return;
    buildCharts(data);
    document.addEventListener('themechange', function () {
      destroyAll();
      buildCharts(data);
    });
  });
})();
