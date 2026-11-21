// Asal: resources/views/kasir/dashboard_kasir.blade.php
document.addEventListener('DOMContentLoaded', function () {
  const d = window.kasirData, font = 'Plus Jakarta Sans, sans-serif';

  // Combo: bar = penjualan, line = transaksi
  new ApexCharts(document.querySelector('#revenue-chart'), {
    series: [
      { name: 'Penjualan', type: 'column', data: d.dataPenjualan },
      { name: 'Jumlah Transaksi', type: 'line', data: d.dataTransaksi }
    ],
    chart: { height: 320, type: 'line', toolbar: { show: false }, zoom: { enabled: false }, fontFamily: font },
    colors: ['#9c1d1d', '#e8a33d'],
    stroke: { width: [0, 3], curve: 'smooth' },
    plotOptions: { bar: { columnWidth: '45%', borderRadius: 6 } },
    dataLabels: { enabled: false }, legend: { show: false },
    grid: { borderColor: '#F0E4D3', strokeDashArray: 4, xaxis: { lines: { show: false } } },
    xaxis: { categories: d.labelHari, axisBorder: { show: false }, axisTicks: { show: false },
             labels: { style: { colors: '#8a7267', fontSize: '11px' } } },
    yaxis: [
      { labels: { style: { colors: '#8a7267', fontSize: '11px' },
                  formatter: v => v >= 1000 ? 'Rp ' + (v / 1000).toFixed(0) + 'rb' : 'Rp ' + v } },
      { opposite: true, labels: { show: false } }
    ],
    tooltip: { theme: 'dark', y: { formatter: (v, o) => o.seriesIndex === 0 ? 'Rp ' + v.toLocaleString('id-ID') : v + ' transaksi' } }
  }).render();

  // Donut metode bayar
  const totalMetode = d.metode.reduce((a, b) => a + b, 0);
  new ApexCharts(document.querySelector('#metode-chart'), {
    series: totalMetode ? d.metode : [1],
    labels: totalMetode ? ['Cash', 'QRIS', 'PayLater'] : ['Belum ada'],
    chart: { type: 'donut', height: 220, fontFamily: font },
    colors: totalMetode ? ['#9c1d1d', '#22C55E', '#e8a33d'] : ['#F0E4D3'],
    legend: { show: false }, dataLabels: { enabled: false }, stroke: { width: 0 },
    tooltip: { enabled: totalMetode > 0, theme: 'dark', y: { formatter: v => v + ' transaksi' } },
    plotOptions: { pie: { donut: { size: '68%', labels: { show: true, name: { show: false },
      value: { fontSize: '22px', fontWeight: 800, color: '#3b1f1a', offsetY: 8 },
      total: { show: true, label: 'Transaksi', fontSize: '11px', color: '#8a7267', formatter: () => totalMetode } } } } }
  }).render();

  // Sparkline
  const spark = (sel, data, color, name) => new ApexCharts(document.querySelector(sel), {
    series: [{ name, data }],
    chart: { type: 'area', height: 45, sparkline: { enabled: true }, fontFamily: font },
    stroke: { curve: 'smooth', width: 2 }, fill: { opacity: 0.12, type: 'solid' }, colors: [color],
    tooltip: { fixed: { enabled: false }, x: { show: false }, marker: { show: false } }
  }).render();
  spark('#transaksi-sparkline', d.dataTransaksi, '#22C55E', 'Transaksi');
  spark('#item-sparkline', d.dataItem, '#e8a33d', 'Item');
});
