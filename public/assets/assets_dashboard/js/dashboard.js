document.addEventListener('DOMContentLoaded', function () {
    const revenueChartEl = document.querySelector('#revenue-chart');
    if (revenueChartEl && window.dashboardData) {
        const days = window.dashboardData.labelHari;
        const penjualan = window.dashboardData.dataPenjualan;
        const transaksi = window.dashboardData.dataTransaksi;

        const revenueChartOptions = {
            series: [
                { name: 'Penjualan', type: 'column', data: penjualan },
                { name: 'Jumlah Transaksi', type: 'line', data: transaksi }
            ],
            chart: {
                height: 320,
                type: 'line',
                toolbar: { show: false },
                zoom: { enabled: false },
                fontFamily: 'Plus Jakarta Sans, sans-serif'
            },
            colors: ['#9c1d1d', '#e8a33d'],
            states: {
                hover: { filter: { type: 'none' } }
            },
            stroke: {
                width: [0, 3],
                curve: 'smooth'
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '45%',
                    borderRadius: 6
                }
            },
            dataLabels: { enabled: false },
            legend: {
                show: false
            },
            grid: {
                borderColor: '#F0E4D3',
                strokeDashArray: 4,
                yaxis: { lines: { show: true } },
                xaxis: { lines: { show: false } },
                padding: { top: 0, right: 0, bottom: 0, left: 0 }
            },
            xaxis: {
                categories: days,
                labels: {
                    style: { colors: '#8a7267', fontSize: '11px', fontWeight: 500 }
                },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: [
                {
                    labels: {
                        style: { colors: '#8a7267', fontSize: '11px' },
                        formatter: function (val) {
                            return 'Rp ' + (val / 1000000).toFixed(1) + 'jt';
                        }
                    }
                },
                {
                    opposite: true,
                    labels: { show: false }
                }
            ],
            fill: { opacity: 1 },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function (val, opts) {
                        if (opts.seriesIndex === 0) return 'Rp ' + val.toLocaleString('id-ID');
                        return val + ' transaksi';
                    }
                }
            }
        };

        const revenueChart = new ApexCharts(revenueChartEl, revenueChartOptions);
        revenueChart.render();
    }

    // -----------------------------------------------------------------
    // 2. Status Stok (Donut Chart: Aman / Menipis / Habis)
    //    Data: window.dashboardData.stok = [aman, menipis, habis]
    // -----------------------------------------------------------------
    const stockChartEl = document.querySelector('#stock-status-chart');
    if (stockChartEl && window.dashboardData) {
        const stockChartOptions = {
            series: window.dashboardData.stok,
            chart: {
                type: 'donut',
                height: 220,
                fontFamily: 'Plus Jakarta Sans, sans-serif'
            },
            labels: ['Aman', 'Menipis', 'Habis'],
            colors: ['#22C55E', '#e8a33d', '#EF4444'],
            states: {
                hover: { filter: { type: 'none' } }
            },
            legend: {
                show: false
            },
            dataLabels: {
                enabled: true,
                formatter: function (val, opts) {
                    if (opts.w.globals.series[opts.seriesIndex] === 0) return '';
                    return Math.round(val) + '%';
                },
                style: {
                    fontSize: '11px',
                    fontWeight: 700,
                    colors: ['#FFFFFF']
                },
                dropShadow: { enabled: false }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        background: 'transparent',
                        labels: {
                            show: true,
                            name: { show: false },
                            value: {
                                show: true,
                                fontSize: '22px',
                                fontWeight: 800,
                                color: '#3b1f1a',
                                offsetY: 8
                            },
                            total: {
                                show: true,
                                label: 'Total Item',
                                fontSize: '11px',
                                fontWeight: 500,
                                color: '#8a7267',
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0);
                                }
                            }
                        }
                    }
                }
            },
            stroke: { width: 0 },
            tooltip: {
                theme: 'dark',
                y: { formatter: function (val) { return val + ' item'; } }
            }
        };

        const stockChart = new ApexCharts(stockChartEl, stockChartOptions);
        stockChart.render();
    }

    // -----------------------------------------------------------------
    // 2b. Mini Sparkline Charts (Jumlah Transaksi & Porsi Terjual)
    //     - Transaksi: pakai deret 7 hari asli (dataTransaksi), jadi
    //       kalau memang belum ada transaksi, garisnya datar di 0 —
    //       itu representasi jujur, bukan bug.
    //     - Porsi: deret 7 hari asli dari item_pesanan (1 baris = 1 porsi).
    // -----------------------------------------------------------------
    const sparklineBaseOptions = {
        chart: {
            type: 'area',
            height: 45,
            sparkline: { enabled: true },
            fontFamily: 'Plus Jakarta Sans, sans-serif'
        },
        stroke: { curve: 'smooth', width: 2 },
        fill: { opacity: 0.12, type: 'solid' },
        tooltip: {
            fixed: { enabled: false },
            x: { show: false },
            y: { title: { formatter: function () { return ''; } } },
            marker: { show: false }
        }
    };

    const transaksiSparkEl = document.querySelector('#transaksi-sparkline');
    if (transaksiSparkEl && window.dashboardData) {
        const transaksiSpark = new ApexCharts(transaksiSparkEl, Object.assign({}, sparklineBaseOptions, {
            series: [{ name: 'Jumlah Transaksi', data: window.dashboardData.dataTransaksi }],
            colors: ['#22C55E']
        }));
        transaksiSpark.render();
    }

    const porsiSparkEl = document.querySelector('#porsi-sparkline');
    if (porsiSparkEl && window.dashboardData) {
        const porsiSpark = new ApexCharts(porsiSparkEl, Object.assign({}, sparklineBaseOptions, {
            series: [{ name: 'Porsi Terjual', data: window.dashboardData.dataPorsi }],
            colors: ['#e8a33d']
        }));
        porsiSpark.render();
    }

    // Date picker dihapus: tanggal di header sekarang label biasa (dashboard selalu hari ini).
});
