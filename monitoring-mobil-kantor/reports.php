<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$from     = $_GET['from']      ?? date('Y-m-01');
$to       = $_GET['to']        ?? date('Y-m-t');
$carId    = $_GET['car_id']    ?? '';
$driverId = $_GET['driver_id'] ?? '';
$status   = $_GET['status']    ?? '';
$type     = $_GET['type']      ?? '';
$keyword  = trim($_GET['q']    ?? '');
$show     = $_GET['show']      ?? '10';
$export   = $_GET['export']    ?? '';

if (!valid_date_value($from)) $from = date('Y-m-01');
if (!valid_date_value($to))   $to   = date('Y-m-t');
if ($from > $to) { [$from, $to] = [$to, $from]; }

function build_report_transactions(string $from, string $to, string $carId, string $driverId, string $status, string $type, string $keyword): array
{
    $transactions = [];
    if (($type === '' || $type === 'dropping') && $carId === '' && $driverId === '' && $status === '') {
        $where = ['entry_date BETWEEN ? AND ?'];
        $params = [$from, $to];
        if ($keyword !== '') { $where[] = '(reference_no LIKE ? OR description LIKE ?)'; $params[] = '%'.$keyword.'%'; $params[] = '%'.$keyword.'%'; }
        $stmt = db()->prepare('SELECT * FROM budget_entries WHERE ' . implode(' AND ', $where) . ' ORDER BY entry_date ASC, id ASC');
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $transactions[] = ['date'=>$row['entry_date'],'sort_order'=>1,'type'=>'Dropping Anggaran','remark'=>$row['description']?:'Dropping anggaran operasional kendaraan','reference_no'=>$row['reference_no'],'debit'=>(float)$row['amount'],'credit'=>0,'car_name'=>'-','driver_name'=>'-','booking_id'=>null];
        }
    }
    if ($type === '' || $type === 'uang_jalan') {
        $where = ['b.date BETWEEN ? AND ?', 'b.status <> ?', 'b.allowance > 0'];
        $params = [$from, $to, 'rejected'];
        if ($carId !== '')    { $where[] = 'b.car_id = ?';    $params[] = $carId; }
        if ($driverId !== '') { $where[] = 'b.driver_id = ?'; $params[] = $driverId; }
        if ($status !== '')   { $where[] = 'b.status = ?';    $params[] = $status; }
        if ($keyword !== '')  { $where[] = '(b.code LIKE ? OR b.destination LIKE ? OR c.name LIKE ? OR d.name LIKE ?)'; for ($i=0;$i<4;$i++) $params[]='%'.$keyword.'%'; }
        $stmt = db()->prepare("SELECT b.*, c.name car_name, c.plate_number, d.name driver_name FROM bookings b LEFT JOIN cars c ON c.id=b.car_id LEFT JOIN drivers d ON d.id=b.driver_id WHERE ".implode(' AND ',$where)." ORDER BY b.date ASC, b.id ASC");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $transactions[] = ['date'=>$row['date'],'sort_order'=>2,'type'=>'Uang Jalan Driver','remark'=>'Uang jalan '.$row['destination'].' - '.($row['driver_name']?:'Driver belum ditentukan').' ('.($row['car_name']?:'Mobil belum ditentukan').')','reference_no'=>$row['code'],'debit'=>0,'credit'=>(float)$row['allowance'],'car_name'=>$row['car_name']?:'-','driver_name'=>$row['driver_name']?:'-','booking_id'=>(int)$row['id']];
        }
    }
    usort($transactions, fn($a,$b) => [$a['date'],$a['sort_order'],$a['reference_no']] <=> [$b['date'],$b['sort_order'],$b['reference_no']]);
    return $transactions;
}

function opening_balance_before(string $from): float
{
    try { $stmt=db()->prepare('SELECT COALESCE(SUM(amount),0) FROM budget_entries WHERE entry_date < ?'); $stmt->execute([$from]); $debit=(float)$stmt->fetchColumn(); } catch(Throwable $e){$debit=0;}
    try { $stmt=db()->prepare("SELECT COALESCE(SUM(allowance),0) FROM bookings WHERE status<>'rejected' AND allowance>0 AND date < ?"); $stmt->execute([$from]); $credit=(float)$stmt->fetchColumn(); } catch(Throwable $e){$credit=0;}
    return $debit - $credit;
}

$openingBalance = opening_balance_before($from);
$transactions   = build_report_transactions($from, $to, (string)$carId, (string)$driverId, (string)$status, (string)$type, $keyword);
$runningBalance = $openingBalance;
$totalDebit = $totalCredit = $debitCount = $creditCount = 0;
foreach ($transactions as $idx => $trx) {
    $totalDebit  += $trx['debit'];
    $totalCredit += $trx['credit'];
    if ($trx['debit']  > 0) $debitCount++;
    if ($trx['credit'] > 0) $creditCount++;
    $runningBalance = $runningBalance + $trx['debit'] - $trx['credit'];
    $transactions[$idx]['balance'] = $runningBalance;
}
$closingBalance = $runningBalance;

if ($export === 'excel') {
    $filename = 'report_keuangan_' . $from . '_sd_' . $to . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF";
    ?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
<x:Name>Report Keuangan</x:Name>
<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
<style>
 body, table, td, th { font-family: Calibri, Arial, sans-serif; font-size: 10pt; }
 table { border-collapse: collapse; }

 col.c-tanggal      { mso-column-width: 82pt; }
 col.c-jenis        { mso-column-width: 120pt; }
 col.c-remark       { mso-column-width: 200pt; }
 col.c-refno        { mso-column-width: 100pt; }
 col.c-mobil        { mso-column-width: 110pt; }
 col.c-driver       { mso-column-width: 110pt; }
 col.c-debit        { mso-column-width: 120pt; }
 col.c-credit       { mso-column-width: 120pt; }
 col.c-saldo        { mso-column-width: 130pt; }

 .title-cell {
  font-size: 14pt; font-weight: bold; color: #0F2F52;
  background: #EFF6FF; padding: 10px 14px;
 }
 .subtitle-cell {
  font-size: 10pt; color: #475569; padding: 4px 14px;
 }

 .meta-label {
  font-weight: bold; background: #F1F5F9; color: #334155;
  border: 1px solid #CBD5E1; padding: 6px 12px; width: 160pt;
 }
 .meta-val-text {
  color: #0F2F52; border: 1px solid #CBD5E1; padding: 6px 12px;
 }
 .meta-val-num {
  color: #0F2F52; font-weight: bold; border: 1px solid #CBD5E1;
  padding: 6px 12px; text-align: right;
  mso-number-format: "#,##0";
 }
 .meta-val-debit  { color: #059669; font-weight: bold; border: 1px solid #CBD5E1; padding: 6px 12px; text-align: right; mso-number-format: "#,##0"; }
 .meta-val-credit { color: #DC2626; font-weight: bold; border: 1px solid #CBD5E1; padding: 6px 12px; text-align: right; mso-number-format: "#,##0"; }
 .meta-val-balance-pos { color: #059669; font-weight: bold; border: 1px solid #CBD5E1; padding: 6px 12px; text-align: right; mso-number-format: "#,##0"; }
 .meta-val-balance-neg { color: #DC2626; font-weight: bold; border: 1px solid #CBD5E1; padding: 6px 12px; text-align: right; mso-number-format: "#,##0"; }

 .th { background: #0F2F52; color: #FFFFFF; font-weight: bold; font-size: 10pt;
  border: 1px solid #0B1E3D; padding: 9px 10px; text-align: center;
  vertical-align: middle; white-space: nowrap;
 }
 .td-center { border: 1px solid #D1D5DB; padding: 7px 10px; text-align: center; vertical-align: middle; white-space: nowrap; }
 .td-left   { border: 1px solid #D1D5DB; padding: 7px 10px; text-align: left;   vertical-align: middle; }
 .td-num    { border: 1px solid #D1D5DB; padding: 7px 10px; text-align: right;  vertical-align: middle; white-space: nowrap; mso-number-format: "#,##0"; }
 .td-debit  { border: 1px solid #D1D5DB; padding: 7px 10px; text-align: right;  vertical-align: middle; white-space: nowrap; mso-number-format: "#,##0"; color: #059669; font-weight: bold; }
 .td-credit { border: 1px solid #D1D5DB; padding: 7px 10px; text-align: right;  vertical-align: middle; white-space: nowrap; mso-number-format: "#,##0"; color: #DC2626; font-weight: bold; }
 .td-saldo-pos { border: 1px solid #D1D5DB; padding: 7px 10px; text-align: right; vertical-align: middle; white-space: nowrap; mso-number-format: "#,##0"; color: #1E293B; font-weight: bold; }
 .td-saldo-neg { border: 1px solid #D1D5DB; padding: 7px 10px; text-align: right; vertical-align: middle; white-space: nowrap; mso-number-format: "#,##0"; color: #DC2626; font-weight: bold; }

 .row-odd  { background: #F8FAFC; }
 .row-even { background: #FFFFFF; }
 .row-opening { background: #EFF6FF; font-style: italic; }

 .total-label { background: #1E3A5F; color: #FFFFFF; font-weight: bold; border: 2px solid #0F2F52; padding: 9px 10px; text-align: left; vertical-align: middle; }
 .total-debit  { background: #F0FDF4; color: #059669; font-weight: bold; border: 2px solid #0F2F52; padding: 9px 10px; text-align: right; white-space: nowrap; mso-number-format: "#,##0"; }
 .total-credit { background: #FEF2F2; color: #DC2626; font-weight: bold; border: 2px solid #0F2F52; padding: 9px 10px; text-align: right; white-space: nowrap; mso-number-format: "#,##0"; }
 .total-saldo  { background: #EFF6FF; color: #0F2F52; font-weight: bold; border: 2px solid #0F2F52; padding: 9px 10px; text-align: right; white-space: nowrap; mso-number-format: "#,##0"; }
</style>
</head>
<body>

<table style="width:100%;margin-bottom:4px">
 <tr><td class="title-cell" colspan="9">REPORT KEUANGAN OPERASIONAL MOBIL KANTOR</td></tr>
 <tr><td class="subtitle-cell" colspan="9">Periode : <?= e(tanggal_id($from)) ?> s/d <?= e(tanggal_id($to)) ?></td></tr>
</table>

<table style="margin-bottom:14px;border-collapse:collapse">
 <tr>
  <td class="meta-label">Nama Akun</td>
  <td class="meta-val-text" colspan="2">Dropping Anggaran Operasional Mobil</td>
 </tr>
 <tr>
  <td class="meta-label">Periode Laporan</td>
  <td class="meta-val-text" colspan="2"><?= e(tanggal_id($from)) ?> s/d <?= e(tanggal_id($to)) ?></td>
 </tr>
 <tr>
  <td class="meta-label">Saldo Awal Periode</td>
  <td class="meta-val-num" colspan="2"><?= (float)$openingBalance ?></td>
 </tr>
 <tr>
  <td class="meta-label">Total Dropping (Debit / Masuk)</td>
  <td class="meta-val-debit" colspan="2"><?= (float)$totalDebit ?></td>
 </tr>
 <tr>
  <td class="meta-label">Total Uang Jalan (Kredit / Keluar)</td>
  <td class="meta-val-credit" colspan="2"><?= (float)$totalCredit ?></td>
 </tr>
 <tr>
  <td class="meta-label">Saldo Akhir Periode</td>
  <td class="<?= $closingBalance >= 0 ? 'meta-val-balance-pos' : 'meta-val-balance-neg' ?>" colspan="2"><?= (float)$closingBalance ?></td>
 </tr>
</table>

<table style="width:100%;border-collapse:collapse">
 <colgroup>
  <col class="c-tanggal">
  <col class="c-jenis">
  <col class="c-remark">
  <col class="c-refno">
  <col class="c-mobil">
  <col class="c-driver">
  <col class="c-debit">
  <col class="c-credit">
  <col class="c-saldo">
 </colgroup>
 <thead>
  <tr>
   <th class="th">Tanggal</th>
   <th class="th">Jenis Transaksi</th>
   <th class="th">Keterangan / Remark</th>
   <th class="th">No. Referensi</th>
   <th class="th">Mobil</th>
   <th class="th">Driver</th>
   <th class="th">Debit (Masuk)</th>
   <th class="th">Kredit (Keluar)</th>
   <th class="th">Saldo Berjalan</th>
  </tr>
 </thead>
 <tbody>
 <?php if ($openingBalance != 0): ?>
 <tr class="row-opening">
  <td class="td-center"><?= e(tanggal_id($from)) ?></td>
  <td class="td-center">Saldo Awal</td>
  <td class="td-left">Saldo awal periode sebelum <?= e(tanggal_id($from)) ?></td>
  <td class="td-center">-</td>
  <td class="td-left">-</td>
  <td class="td-left">-</td>
  <td class="td-num">0</td>
  <td class="td-num">0</td>
  <td class="td-num" style="color:#2563EB"><?= (float)$openingBalance ?></td>
 </tr>
 <?php endif; ?>
 <?php foreach ($transactions as $i => $trx): ?>
 <tr class="<?= $i % 2 === 0 ? 'row-even' : 'row-odd' ?>">
  <td class="td-center"><?= e(tanggal_id($trx['date'])) ?></td>
  <td class="td-center"><?= e($trx['type']) ?></td>
  <td class="td-left"><?= e($trx['remark']) ?></td>
  <td class="td-center"><?= e($trx['reference_no']) ?></td>
  <td class="td-left"><?= e($trx['car_name']) ?></td>
  <td class="td-left"><?= e($trx['driver_name']) ?></td>
  <td class="<?= $trx['debit'] > 0 ? 'td-debit' : 'td-num' ?>"><?= (float)$trx['debit'] ?></td>
  <td class="<?= $trx['credit'] > 0 ? 'td-credit' : 'td-num' ?>"><?= (float)$trx['credit'] ?></td>
  <td class="<?= $trx['balance'] >= 0 ? 'td-saldo-pos' : 'td-saldo-neg' ?>"><?= (float)$trx['balance'] ?></td>
 </tr>
 <?php endforeach; ?>
 <?php if (!$transactions): ?>
 <tr>
  <td colspan="9" style="text-align:center;padding:20px;color:#94A3B8;border:1px solid #D1D5DB">Tidak ada transaksi pada periode ini.</td>
 </tr>
 <?php endif; ?>
 </tbody>
 <tfoot>
  <tr>
   <td class="total-label" colspan="6">TOTAL PERIODE</td>
   <td class="total-debit"><?= (float)$totalDebit ?></td>
   <td class="total-credit"><?= (float)$totalCredit ?></td>
   <td class="total-saldo"><?= (float)$closingBalance ?></td>
  </tr>
 </tfoot>
</table>

</body>
</html>
    <?php
    exit;
}

if ($export === 'csv') {
    $filename = 'report_keuangan_' . $from . '_sd_' . $to . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Tanggal','Jenis Transaksi','Keterangan','No. Referensi','Mobil','Driver','Debit (Masuk)','Kredit (Keluar)','Saldo Berjalan'], ';');
    foreach ($transactions as $trx) {
        fputcsv($out, [tanggal_id($trx['date']),$trx['type'],$trx['remark'],$trx['reference_no'],$trx['car_name'],$trx['driver_name'],$trx['debit'],$trx['credit'],$trx['balance']], ';');
    }
    fclose($out); exit;
}

$cars    = db()->query('SELECT id, name, plate_number FROM cars ORDER BY name')->fetchAll();
$drivers = db()->query('SELECT id, name FROM drivers ORDER BY name')->fetchAll();
$displayTransactions = $show !== 'all' ? array_slice($transactions, 0, max(1,(int)$show)) : $transactions;

$title        = 'Report Keuangan - Monitoring Mobil Kantor';
$page_title   = 'Report Keuangan';
$page_subtitle = 'Mutasi dropping anggaran dan uang jalan driver dengan saldo berjalan.';
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>
<main class="main">
<?php include __DIR__ . '/templates/topbar.php'; ?>

<section class="grid grid-4" style="margin-bottom:22px">
    <div class="card stat card-purple">
        <div>
            <span>Saldo Awal</span>
            <strong style="font-size:18px"><?= e(rupiah($openingBalance)) ?></strong>
            <small>sebelum periode</small>
        </div>
        <div class="icon">📂</div>
    </div>
    <div class="card stat card-green">
        <div>
            <span>Total Dropping</span>
            <strong style="font-size:18px"><?= e(rupiah($totalDebit)) ?></strong>
            <small><?= $debitCount ?> transaksi masuk</small>
        </div>
        <div class="icon">📥</div>
    </div>
    <div class="card stat card-orange">
        <div>
            <span>Total Uang Jalan</span>
            <strong style="font-size:18px"><?= e(rupiah($totalCredit)) ?></strong>
            <small><?= $creditCount ?> transaksi keluar</small>
        </div>
        <div class="icon">📤</div>
    </div>
    <div class="card stat <?= $closingBalance >= 0 ? 'card-blue' : 'card-red' ?>">
        <div>
            <span>Saldo Akhir</span>
            <strong style="font-size:18px"><?= e(rupiah($closingBalance)) ?></strong>
            <small><?= $closingBalance >= 0 ? 'tersedia' : '⚠ melebihi budget' ?></small>
        </div>
        <div class="icon">💼</div>
    </div>
</section>

<section class="card" style="margin-bottom:18px">
    <div class="report-info-grid">
        <div>
            <h2 style="margin-bottom:14px">Report Information</h2>
            <div class="report-row"><span>Nama Akun</span><strong>Dropping Anggaran Operasional Mobil</strong></div>
            <div class="report-row"><span>Opening Balance</span><strong style="color:var(--blue)"><?= e(rupiah($openingBalance)) ?></strong></div>
            <div class="report-row"><span>Closing Balance</span><strong style="color:<?= $closingBalance >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= e(rupiah($closingBalance)) ?></strong></div>
        </div>
        <div>
            <h2 style="margin-bottom:14px">Ringkasan Periode</h2>
            <div class="report-row"><span>Periode</span><strong><?= e(tanggal_id($from) . ' s/d ' . tanggal_id($to)) ?></strong></div>
            <div class="report-row"><span>Jumlah Debit (Masuk)</span><strong style="color:var(--green)"><?= $debitCount ?> transaksi — <?= e(rupiah($totalDebit)) ?></strong></div>
            <div class="report-row"><span>Jumlah Kredit (Keluar)</span><strong style="color:var(--red)"><?= $creditCount ?> transaksi — <?= e(rupiah($totalCredit)) ?></strong></div>
        </div>
    </div>
</section>

<section class="card" style="margin-bottom:18px">
    <h2 style="margin-bottom:14px">Filter Transaksi</h2>
    <form class="filter-row" method="get">
        <div class="form-group">
            <label>Dari</label>
            <input class="input" type="date" name="from" value="<?= e($from) ?>">
        </div>
        <div class="form-group">
            <label>Sampai</label>
            <input class="input" type="date" name="to" value="<?= e($to) ?>">
        </div>
        <div class="form-group">
            <label>Jenis</label>
            <select name="type" class="input">
                <option value="" <?= $type===''?'selected':'' ?>>Semua</option>
                <option value="dropping" <?= $type==='dropping'?'selected':'' ?>>Dropping Anggaran</option>
                <option value="uang_jalan" <?= $type==='uang_jalan'?'selected':'' ?>>Uang Jalan Driver</option>
            </select>
        </div>
        <div class="form-group">
            <label>Mobil</label>
            <select name="car_id" class="input">
                <option value="">Semua</option>
                <?php foreach($cars as $car): ?>
                    <option value="<?= (int)$car['id'] ?>" <?= (string)$carId===(string)$car['id']?'selected':'' ?>><?= e($car['name'].' - '.$car['plate_number']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Driver</label>
            <select name="driver_id" class="input">
                <option value="">Semua</option>
                <?php foreach($drivers as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= (string)$driverId===(string)$d['id']?'selected':'' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Search</label>
            <input class="input" name="q" value="<?= e($keyword) ?>" placeholder="Remark / Referensi…">
        </div>
        <div class="form-group">
            <label>Tampilkan</label>
            <select name="show" class="input">
                <option value="10" <?= $show==='10'?'selected':'' ?>>10 baris</option>
                <option value="25" <?= $show==='25'?'selected':'' ?>>25 baris</option>
                <option value="50" <?= $show==='50'?'selected':'' ?>>50 baris</option>
                <option value="all" <?= $show==='all'?'selected':'' ?>>Semua</option>
            </select>
        </div>
        <div class="form-group" style="align-self:flex-end">
            <button class="btn btn-primary" type="submit">Filter</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="calendar-toolbar" style="margin-bottom:16px">
        <div>
            <h2>Tabel Mutasi Keuangan</h2>
            <p class="text-muted">Menampilkan mutasi dropping anggaran dan uang jalan driver</p>
        </div>
        <div class="actions">
            <button class="btn btn-outline btn-sm no-print" onclick="copyReportTable()">📋 Copy</button>
            <a class="btn btn-outline btn-sm no-print" href="<?= e(base_path('reports.php?'.http_build_query(array_merge($_GET,['export'=>'excel'])))) ?>">⬇ Excel</a>
            <button class="btn btn-primary btn-sm no-print" onclick="window.print()">🖨 Print</button>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table report-table" id="reportTable">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jenis</th>
                    <th>Remark</th>
                    <th>Reference No.</th>
                    <th>Mobil</th>
                    <th>Driver</th>
                    <th style="text-align:right;color:var(--green)">Debit (Masuk)</th>
                    <th style="text-align:right;color:var(--red)">Kredit (Keluar)</th>
                    <th style="text-align:right">Saldo</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($openingBalance != 0): ?>
                <tr style="background:var(--surface-2);font-style:italic">
                    <td colspan="6" style="color:var(--muted);font-size:12px;padding-left:16px">Saldo Awal Periode</td>
                    <td></td><td></td>
                    <td style="text-align:right;font-weight:800;color:var(--blue)"><?= e(rupiah($openingBalance)) ?></td>
                </tr>
            <?php endif; ?>
            <?php foreach ($displayTransactions as $trx): ?>
                <tr>
                    <td style="white-space:nowrap"><strong><?= e(tanggal_id($trx['date'])) ?></strong></td>
                    <td>
                        <span class="badge <?= $trx['type'] === 'Dropping Anggaran' ? 'badge-success' : 'badge-warning' ?>">
                            <?= $trx['type'] === 'Dropping Anggaran' ? '📥' : '📤' ?> <?= e($trx['type']) ?>
                        </span>
                    </td>
                    <td style="font-size:12.5px;max-width:240px"><?= e($trx['remark']) ?></td>
                    <td>
                        <?php if ($trx['booking_id']): ?>
                            <a href="<?= e(base_path('booking_detail.php?id='.$trx['booking_id'])) ?>"
                               style="color:var(--blue);font-weight:700"><?= e($trx['reference_no']) ?></a>
                        <?php else: ?>
                            <span class="plate-tag"><?= e($trx['reference_no']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12.5px"><?= e($trx['car_name']) ?></td>
                    <td style="font-size:12.5px"><?= e($trx['driver_name']) ?></td>
                    <td style="text-align:right;color:<?= $trx['debit'] > 0 ? 'var(--green)' : 'var(--muted-2)' ?>;font-weight:<?= $trx['debit'] > 0 ? '800' : '400' ?>">
                        <?= $trx['debit'] > 0 ? e(rupiah($trx['debit'])) : '—' ?>
                    </td>
                    <td style="text-align:right;color:<?= $trx['credit'] > 0 ? 'var(--red)' : 'var(--muted-2)' ?>;font-weight:<?= $trx['credit'] > 0 ? '800' : '400' ?>">
                        <?= $trx['credit'] > 0 ? e(rupiah($trx['credit'])) : '—' ?>
                    </td>
                    <td style="text-align:right">
                        <strong style="color:<?= $trx['balance'] >= 0 ? 'var(--text)' : 'var(--red)' ?>"><?= e(rupiah($trx['balance'])) ?></strong>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$displayTransactions): ?>
                <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--muted)">
                    📭 Tidak ada transaksi sesuai filter.
                </td></tr>
            <?php endif; ?>
            </tbody>
            <?php if ($displayTransactions): ?>
            <tfoot>
                <tr style="background:var(--surface-2);font-weight:900">
                    <td colspan="6" style="padding:12px 16px;color:var(--muted);font-size:12px">TOTAL PERIODE</td>
                    <td style="text-align:right;color:var(--green)"><?= e(rupiah($totalDebit)) ?></td>
                    <td style="text-align:right;color:var(--red)"><?= e(rupiah($totalCredit)) ?></td>
                    <td style="text-align:right;color:<?= $closingBalance >= 0 ? 'var(--blue)' : 'var(--red)' ?>"><?= e(rupiah($closingBalance)) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php if ($show !== 'all' && count($transactions) > count($displayTransactions)): ?>
        <p class="text-muted no-print" style="margin-top:12px;text-align:center;font-size:13px">
            Menampilkan <?= count($displayTransactions) ?> dari <?= count($transactions) ?> transaksi —
            <a href="<?= e(base_path('reports.php?'.http_build_query(array_merge($_GET,['show'=>'all'])))) ?>" style="color:var(--blue);font-weight:700">Tampilkan Semua</a>
        </p>
    <?php endif; ?>
</section>
</main>

<script>
function copyReportTable(){
    const table = document.getElementById('reportTable');
    if (!table) return;
    const rows = [...table.querySelectorAll('tr')].map(r=>[...r.children].map(c=>c.innerText.trim()).join('\t')).join('\n');
    navigator.clipboard.writeText(rows).then(()=>alert('Tabel berhasil disalin ke clipboard.'));
}
</script>
<?php include __DIR__ . '/templates/footer.php'; ?>
