window.addEventListener('load', function(){
  const data = window.chartData;
  if (!data || !window.Chart) return;

  Chart.defaults.font.family = 'Inter, Arial, sans-serif';
  Chart.defaults.color = '#667085';

  const grid = { color: 'rgba(16,24,40,.06)' };
  const blue = '#2563eb', orange = '#f97316', green = '#16a34a', red = '#dc2626', cyan = '#06b6d4';

  const monthly = new Chart(document.getElementById('monthlyChart'), {
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
        legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } },
        tooltip: { backgroundColor: '#101828', padding: 12, cornerRadius: 8 }
      },
      scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid, ticks: { precision: 0 } } }
    }
  });

  const statusColors = { 'Available': green, 'Low Stock': orange, 'Out of Stock': red, 'Issued': blue };
  new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
      labels: data.status.map(s => s.s),
      datasets: [{ data: data.status.map(s => s.c), backgroundColor: data.status.map(s => statusColors[s.s] || '#94a3b8'), borderWidth: 2, borderColor: '#fff' }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '62%',
      plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 14 } },
        tooltip: { backgroundColor: '#101828', padding: 12, cornerRadius: 8 }
      }
    }
  });

  function horizontal(canvasId, rows, color) {
    return new Chart(document.getElementById(canvasId), {
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
          tooltip: { backgroundColor: '#101828', padding: 12, cornerRadius: 8, callbacks: { label: c => ` ${c.parsed.x} qty` } }
        },
        scales: { x: { beginAtZero: true, grid, ticks: { precision: 0 } }, y: { grid: { display: false } } }
      }
    });
  }

  horizontal('issuedChart', data.topIssued, orange);
  horizontal('receivedChart', data.topReceived, green);

  window.dashboardCharts = { monthly };
});
