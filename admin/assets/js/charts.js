// FitFood Admin — chart configs
// Trích từ inapp-1.0.0/src/assets/js/chart.js, bỏ module imports
// và phần event listener cho btn-random/btn-update (chưa cần ở dashboard).
// ApexCharts được load qua CDN ở index.php.

document.addEventListener('DOMContentLoaded', () => {

  // ============================================================
  // 1. SALES OVERVIEW — area/line chart (lấy từ reports.html)
  // ============================================================
  const salesChartEl = document.getElementById('salesChart');
  if (salesChartEl && typeof ApexCharts !== 'undefined') {
    const salesThisYear = [42000, 53000, 48000, 61000, 72000, 69000, 74000, 82000, 78000, 86000, 91000, 97000];
    const salesLastYear = [38000, 45000, 47000, 56000, 65000, 63000, 68000, 70000, 69000, 75000, 80000, 84000];
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    const salesOptions = {
      chart: {
        id: 'sales-overview',
        type: 'area',
        height: 360,
        zoom:    { enabled: false },
        toolbar: { show: false },
      },
      colors: ['#E66239', '#198754'],
      stroke:  { width: [3, 2.5], curve: 'smooth' },
      markers: { size: 4, hover: { sizeOffset: 2 } },
      series: [
        { name: 'This Year', data: salesThisYear },
        { name: 'Last Year', data: salesLastYear },
      ],
      fill: {
        type: 'gradient',
        gradient: {
          shadeIntensity: 1,
          inverseColors:  false,
          opacityFrom:    0.45,
          opacityTo:      0.05,
          stops: [20, 60, 100],
        },
      },
      yaxis: {
        labels: { formatter: (val) => formatVnd(val) },
        title:  { text: 'Doanh thu (VNĐ)' },
      },
      xaxis: {
        categories: months,
        tickPlacement: 'on',
      },
      tooltip: {
        shared: true,
        y: { formatter: (val) => formatVnd(val) },
      },
      legend: {
        position: 'top',
        horizontalAlign: 'right',
      },
      responsive: [{
        breakpoint: 640,
        options: {
          chart:  { height: 280 },
          legend: { position: 'bottom', horizontalAlign: 'center' },
        },
      }],
    };

    new ApexCharts(salesChartEl, salesOptions).render();

    function formatVnd(value) {
      if (value == null) return '-';
      return Number(value).toLocaleString('vi-VN', { maximumFractionDigits: 0 }) + '₫';
    }
  }

  // ============================================================
  // 2. CUSTOMERS OVERVIEW — radial/donut chart (từ index.html)
  // ============================================================
  const customerChartEl = document.getElementById('customerChart');
  if (customerChartEl && typeof ApexCharts !== 'undefined') {
    const customerOptions = {
      series: [44, 55],
      chart: {
        height: 240,
        type:   'radialBar',
      },
      colors: ['#5BE49B', '#E66239'],
      plotOptions: {
        radialBar: {
          dataLabels: {
            name:  { fontSize: '20px' },
            value: { fontSize: '14px' },
            total: { show: false },
          },
          hollow: {
            margin:     3,
            size:       '40%',
            background: 'transparent',
            position:   'front',
          },
          track: {
            show:        true,
            background:  '#f0f0f0',
            strokeWidth: '45%',
            opacity:     1,
            margin:      5,
          },
        },
      },
      fill: {
        type: 'gradient',
        gradient: {
          shade: 'dark',
          type:  'vertical',
          gradientToColors: ['#007867', '#FFAC82'],
          stops: [0, 100],
        },
      },
      stroke: { lineCap: 'round' },
      labels: ['First Time', 'Return'],
    };

    new ApexCharts(customerChartEl, customerOptions).render();
  }
});
