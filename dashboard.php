<?php
// dashboard.php
$DB_HOST = 'localhost'; 
$DB_USER = 's67160313'; 
$DB_PASS = 'NAfVFzfH'; 
$DB_NAME = 's67160313';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) { die('DB error: '.$mysqli->connect_error); }
$mysqli->set_charset('utf8mb4');

function fetch_all($mysqli, $sql) {
  $res = $mysqli->query($sql);
  if (!$res) return [];
  $rows = [];
  while ($row = $res->fetch_assoc()) $rows[] = $row;
  $res->free();
  return $rows;
}

// Fetch data
$monthly = fetch_all($mysqli, "SELECT ym, net_sales FROM v_monthly_sales");
$category = fetch_all($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
$region = fetch_all($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
$topProducts = fetch_all($mysqli, "SELECT product_name, qty_sold FROM v_top_products");
$payment = fetch_all($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
$hourly = fetch_all($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales");
$newReturning = fetch_all($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");

$kpi = fetch_all($mysqli, "
  SELECT
    COALESCE(SUM(net_amount),0) AS sales_30d,
    COALESCE(SUM(quantity),0) AS qty_30d,
    COALESCE(COUNT(DISTINCT customer_id),0) AS buyers_30d
  FROM fact_sales
  WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
");
$kpi = $kpi[0] ?? ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0];

function nf($n){ return number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Retail DW Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
body { background: #0f172a; color: #e2e8f0; }
.card { background: #111827; border-radius: 1rem; border:1px solid rgba(255,255,255,0.06);}
.card h5 { color: #e5e7eb; }
.kpi { font-size:1.4rem; font-weight:700; }
.sub { color:#93c5fd; font-size:.9rem; }
.grid { display:grid; gap:1rem; grid-template-columns:repeat(12,1fr);}
.col-12{grid-column:span 12;}
.col-6{grid-column:span 6;}
.col-4{grid-column:span 4;}
.col-8{grid-column:span 8;}
@media(max-width:991px){.col-6,.col-4,.col-8{grid-column:span 12;}}
canvas{max-height:360px;}
</style>
</head>
<body class="p-3 p-md-4">
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0">ยอดขาย (Retail DW) — Dashboard</h2>
    <span class="sub">แหล่งข้อมูล: MySQL (mysqli)</span>
  </div>

  <!-- KPI -->
  <div class="grid mb-3">
    <div class="card p-3 col-4">
      <h5>ยอดขาย 30 วัน</h5>
      <div class="kpi">฿<?= nf($kpi['sales_30d']) ?></div>
    </div>
    <div class="card p-3 col-4">
      <h5>จำนวนชิ้นขาย 30 วัน</h5>
      <div class="kpi"><?= number_format((int)$kpi['qty_30d']) ?> ชิ้น</div>
    </div>
    <div class="card p-3 col-4">
      <h5>จำนวนผู้ซื้อ 30 วัน</h5>
      <div class="kpi"><?= number_format((int)$kpi['buyers_30d']) ?> คน</div>
    </div>
  </div>

  <!-- Charts -->
  <div class="grid">
    <div class="card p-3 col-8"><h5 class="mb-2">ยอดขายรายเดือน (2 ปี)</h5><canvas id="chartMonthly"></canvas></div>
    <div class="card p-3 col-4"><h5 class="mb-2">สัดส่วนยอดขายตามหมวด</h5><canvas id="chartCategory"></canvas></div>
    <div class="card p-3 col-6"><h5 class="mb-2">Top 10 สินค้าขายดี</h5><canvas id="chartTopProducts"></canvas></div>
    <div class="card p-3 col-6"><h5 class="mb-2">ยอดขายตามภูมิภาค</h5><canvas id="chartRegion"></canvas></div>
    <div class="card p-3 col-6"><h5 class="mb-2">วิธีการชำระเงิน</h5><canvas id="chartPayment"></canvas></div>
    <div class="card p-3 col-6"><h5 class="mb-2">ยอดขายรายชั่วโมง</h5><canvas id="chartHourly"></canvas></div>
    <div class="card p-3 col-12"><h5 class="mb-2">ลูกค้าใหม่ vs ลูกค้าเดิม (รายวัน)</h5><canvas id="chartNewReturning"></canvas></div>
  </div>
</div>

<script>
// PHP -> JS
const monthly = <?= json_encode($monthly) ?>;
const category = <?= json_encode($category) ?>;
const region = <?= json_encode($region) ?>;
const topProducts = <?= json_encode($topProducts) ?>;
const payment = <?= json_encode($payment) ?>;
const hourly = <?= json_encode($hourly) ?>;
const newReturning = <?= json_encode($newReturning) ?>;

const toXY = (arr, x, y) => ({labels: arr.map(o=>o[x]||'N/A'), values: arr.map(o=>parseFloat(o[y]||0))});

// Fallback data if empty
function fallback(labels, values, defaultLabel='ไม่มีข้อมูล') {
  if(labels.length===0) return {labels:[defaultLabel], values:[0]};
  return {labels, values};
}

// Monthly
(() => {
  let {labels, values} = toXY(monthly,'ym','net_sales');
  ({labels, values} = fallback(labels, values));
  new Chart(document.getElementById('chartMonthly'),{
    type:'line',
    data:{labels,datasets:[{label:'ยอดขาย (฿)',data:values,tension:.3,fill:true,backgroundColor:'rgba(99,102,241,0.2)',borderColor:'#6366F1'}]},
    options:{plugins:{legend:{labels:{color:'#e5e7eb'}}},scales:{x:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}},y:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}}}}
  });
})();

// Category
(() => {
  let {labels, values} = toXY(category,'category','net_sales');
  ({labels, values} = fallback(labels, values));
  new Chart(document.getElementById('chartCategory'),{
    type:'doughnut',
    data:{labels,datasets:[{data:values,backgroundColor:['#6366F1','#F43F5E','#10B981','#FBBF24','#8B5CF6']}]},
    options:{plugins:{legend:{position:'bottom',labels:{color:'#e5e7eb'}}}}
  });
})();

// Top Products
(() => {
  let labels = topProducts.map(o=>o.product_name||'N/A');
  let values = topProducts.map(o=>parseInt(o.qty_sold||0));
  ({labels, values} = fallback(labels, values));
  new Chart(document.getElementById('chartTopProducts'),{
    type:'bar',
    data:{labels,datasets:[{label:'ชิ้นที่ขาย',data:values,backgroundColor:'#3B82F6'}]},
    options:{indexAxis:'y',plugins:{legend:{labels:{color:'#e5e7eb'}}},scales:{x:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}},y:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}}}}
  });
})();

// Region
(() => {
  let {labels, values} = toXY(region,'region','net_sales');
  ({labels, values} = fallback(labels, values));
  new Chart(document.getElementById('chartRegion'),{
    type:'bar',
    data:{labels,datasets:[{label:'ยอดขาย (฿)',data:values,backgroundColor:'#10B981'}]},
    options:{plugins:{legend:{labels:{color:'#e5e7eb'}}},scales:{x:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}},y:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}}}}
  });
})();

// Payment
(() => {
  let {labels, values} = toXY(payment,'payment_method','net_sales');
  ({labels, values} = fallback(labels, values));
  new Chart(document.getElementById('chartPayment'),{
    type:'pie',
    data:{labels,datasets:[{data:values,backgroundColor:['#6366F1','#F43F5E','#10B981','#FBBF24','#8B5CF6']}]},
    options:{plugins:{legend:{position:'bottom',labels:{color:'#e5e7eb'}}}}
  });
})();

// Hourly
(() => {
  let {labels, values} = toXY(hourly,'hour_of_day','net_sales');
  ({labels, values} = fallback(labels, values));
  new Chart(document.getElementById('chartHourly'),{
    type:'bar',
    data:{labels,datasets:[{label:'ยอดขาย (฿)',data:values,backgroundColor:'#F59E0B'}]},
    options:{plugins:{legend:{labels:{color:'#e5e7eb'}}},scales:{x:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}},y:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}}}}
  });
})();

// New vs Returning (Stacked)
(() => {
  let labels = newReturning.map(o=>o.date_key||'N/A');
  let newC = newReturning.map(o=>parseFloat(o.new_customer_sales||0));
  let retC = newReturning.map(o=>parseFloat(o.returning_sales||0));
  ({labels, values} = fallback(labels, newC));
  new Chart(document.getElementById('chartNewReturning'),{
    type:'line',
    data:{labels,datasets:[
      {label:'ลูกค้าใหม่ (฿)',data:newC,tension:.3,fill:true,backgroundColor:'rgba(59,130,246,0.2)',borderColor:'#3B82F6'},
      {label:'ลูกค้าเดิม (฿)',data:retC,tension:.3,fill:true,backgroundColor:'rgba(16,185,129,0.2)',borderColor:'#10B981'}
    ]},
    options:{plugins:{legend:{labels:{color:'#e5e7eb'}}},scales:{x:{ticks:{color:'#c7d2fe',maxTicksLimit:12},grid:{color:'rgba(255,255,255,.08)'}},y:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}}}}
  });
})();
</script>
</body>
</html>
