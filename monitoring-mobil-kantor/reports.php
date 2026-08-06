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
            $transactions[] = [
                'date'         => $row['entry_date'],
                'sort_order'   => 1,
                'type'         => 'Dropping Anggaran',
                'remark'       => $row['description'] ?: 'Dropping anggaran operasional kendaraan',
                'reference_no' => $row['reference_no'],
                'debit'        => (float)$row['amount'],
                'credit'       => 0,
                'car_name'     => '-',
                'plate_number' => '-',
                'driver_name'  => '-',
                'booking_id'   => null,
            ];
        }
    }
    if ($type === '' || $type === 'uang_jalan') {
        $where = ['b.date BETWEEN ? AND ?', "b.status <> 'rejected'"];
        $params = [$from, $to];
        if ($carId !== '')    { $where[] = 'b.car_id = ?';    $params[] = $carId; }
        if ($driverId !== '') { $where[] = 'b.driver_id = ?'; $params[] = $driverId; }
        if ($status !== '')   { $where[] = 'b.status = ?';    $params[] = $status; }
        if ($keyword !== '')  { $where[] = '(b.code LIKE ? OR b.destination LIKE ? OR c.name LIKE ? OR d.name LIKE ?)'; for ($i=0;$i<4;$i++) $params[]='%'.$keyword.'%'; }
        $stmt = db()->prepare("SELECT b.*, u.name requester, COALESCE(NULLIF(b.department, ''), u.department) AS department, c.name car_name, c.plate_number, d.name driver_name,
            COALESCE((SELECT SUM(e.amount) FROM expenses e WHERE e.booking_id = b.id), 0) AS nota_total
            FROM bookings b JOIN users u ON u.id=b.user_id LEFT JOIN cars c ON c.id=b.car_id LEFT JOIN drivers d ON d.id=b.driver_id
            WHERE ".implode(' AND ',$where)." ORDER BY b.date ASC, b.id ASC");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $umk        = (float)($row['advance_amount'] ?? 0);
            $realisasi  = (float)($row['nota_total'] ?? 0);
            $lebihKurang = $umk - $realisasi;
            $transactions[] = [
                'date'         => $row['date'],
                'sort_order'   => 2,
                'type'         => 'Uang Jalan Driver',
                'remark'       => 'Uang jalan '.$row['destination'].' - '.($row['driver_name'] ?: 'Driver belum ditentukan').' ('.($row['car_name'] ?: 'Mobil belum ditentukan').')',
                'reference_no' => $row['code'],
                'debit'        => 0,
                'credit'       => (float)($row['allowance'] ?? 0),
                'car_name'     => $row['car_name']     ?: '-',
                'plate_number' => $row['plate_number'] ?: '-',
                'driver_name'  => $row['driver_name']  ?: '-',
                'booking_id'   => (int)$row['id'],
                'umk'          => $umk,
                'realisasi'    => $realisasi,
                'lebih_kurang' => $lebihKurang,
            ];
        }
    }
    usort($transactions, fn($a,$b) => [$a['date'],$a['sort_order'],$a['reference_no']] <=> [$b['date'],$b['sort_order'],$b['reference_no']]);
    return $transactions;
}

function opening_balance_before(string $from, string $carId = '', string $driverId = '', string $status = '', string $type = ''): float
{
    if ($carId !== '' || $driverId !== '') {
        return 0;
    }

    $debit = 0;
    if ($type !== 'uang_jalan') {
        try {
            $stmt = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM budget_entries WHERE entry_date < ?');
            $stmt->execute([$from]);
            $debit = (float)$stmt->fetchColumn();
        } catch (Throwable $e) { $debit = 0; }
    }

    $credit = 0;
    if ($type === '' || $type === 'uang_jalan') {
        $where = ["status<>'rejected'", 'allowance>0', 'date < ?'];
        $params = [$from];
        if ($status !== '') { $where[] = 'status = ?'; $params[] = $status; }
        try {
            $stmt = db()->prepare('SELECT COALESCE(SUM(allowance),0) FROM bookings WHERE ' . implode(' AND ', $where));
            $stmt->execute($params);
            $credit = (float)$stmt->fetchColumn();
        } catch (Throwable $e) { $credit = 0; }
    }

    return $debit - $credit;
}

$transactions   = build_report_transactions($from, $to, (string)$carId, (string)$driverId, (string)$status, (string)$type, $keyword);
$totalUmk = $totalRealisasi = 0;
foreach ($transactions as $trx) {
    $totalUmk       += (float)($trx['umk'] ?? 0);
    $totalRealisasi += (float)($trx['realisasi'] ?? 0);
}
$totalLebihKurang = $totalUmk - $totalRealisasi;

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
 col.c-refno        { mso-column-width: 110pt; }
 col.c-mobil        { mso-column-width: 110pt; }
 col.c-plat         { mso-column-width: 80pt; }
 col.c-driver       { mso-column-width: 110pt; }
 col.c-umk          { mso-column-width: 120pt; }
 col.c-realisasi    { mso-column-width: 120pt; }
 col.c-lk           { mso-column-width: 120pt; }

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
 <tr><td class="title-cell" colspan="7">REPORT KEUANGAN OPERASIONAL MOBIL KANTOR</td></tr>
 <tr><td class="subtitle-cell" colspan="7">Periode : <?= e(tanggal_id($from)) ?> s/d <?= e(tanggal_id($to)) ?></td></tr>
</table>

<table style="margin-bottom:14px;border-collapse:collapse">
 <tr>
  <td class="meta-label">Periode Laporan</td>
  <td class="meta-val-text" colspan="2"><?= e(tanggal_id($from)) ?> s/d <?= e(tanggal_id($to)) ?></td>
 </tr>
 <tr>
  <td class="meta-label">Total UMK Periode</td>
  <td class="meta-val-num" colspan="2"><?= (float)$totalUmk ?></td>
 </tr>
 <tr>
  <td class="meta-label">Total Realisasi</td>
  <td class="meta-val-num" colspan="2"><?= (float)$totalRealisasi ?></td>
 </tr>
 <tr>
  <td class="meta-label">Total Lebih / Kurang</td>
  <td class="<?= $totalLebihKurang >= 0 ? 'meta-val-balance-pos' : 'meta-val-balance-neg' ?>" colspan="2"><?= (float)$totalLebihKurang ?></td>
 </tr>
</table>

<table style="width:100%;border-collapse:collapse">
 <colgroup>
  <col class="c-tanggal">
  <col class="c-jenis">
  <col class="c-refno">
  <col class="c-mobil">
  <col class="c-plat">
  <col class="c-driver">
  <col class="c-umk">
  <col class="c-realisasi">
  <col class="c-lk">
 </colgroup>
 <thead>
  <tr>
   <th class="th">Tanggal</th>
   <th class="th">Jenis Transaksi</th>
   <th class="th">No. Referensi</th>
   <th class="th">Mobil</th>
   <th class="th">Plat Nomor</th>
   <th class="th">Driver</th>
   <th class="th">UMK (Uang Muka)</th>
   <th class="th">Realisasi</th>
   <th class="th">Lebih / Kurang</th>
  </tr>
 </thead>
 <tbody>
 <?php foreach ($transactions as $i => $trx): ?>
 <tr class="<?= $i % 2 === 0 ? 'row-even' : 'row-odd' ?>">
  <td class="td-center"><?= e(tanggal_id($trx['date'])) ?></td>
  <td class="td-center"><?= e($trx['type']) ?></td>
  <td class="td-left" style="color:#2563EB;font-weight:700"><?= e($trx['reference_no']) ?></td>
  <td class="td-left"><?= e($trx['car_name']) ?></td>
  <td class="td-left"><?= e($trx['plate_number'] ?? '-') ?></td>
  <td class="td-left"><?= e($trx['driver_name']) ?></td>
  <?php if ($trx['type'] === 'Uang Jalan Driver'): ?>
  <td class="td-num" style="color:#2563EB"><?= (float)($trx['umk'] ?? 0) ?></td>
  <td class="td-num"><?= (float)($trx['realisasi'] ?? 0) ?></td>
  <td class="<?= (float)($trx['lebih_kurang'] ?? 0) >= 0 ? 'td-debit' : 'td-credit' ?>"><?= (float)($trx['lebih_kurang'] ?? 0) ?></td>
  <?php else: ?>
  <td class="td-center">—</td>
  <td class="td-center">—</td>
  <td class="td-center">—</td>
  <?php endif; ?>
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
   <td class="total-saldo" style="color:#2563EB"><?= (float)$totalUmk ?></td>
   <td class="total-saldo"><?= (float)$totalRealisasi ?></td>
   <td class="<?= $totalLebihKurang >= 0 ? 'total-debit' : 'total-credit' ?>"><?= (float)$totalLebihKurang ?></td>
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
    fputcsv($out, ['Tanggal','Jenis Transaksi','Keterangan','No. Referensi','Mobil','Plat Nomor','Driver','UMK (Uang Muka)','Realisasi','Lebih / Kurang'], ';');
    foreach ($transactions as $trx) {
        $umk = number_format((float)($trx['umk'] ?? 0), 0, ',', '.');
        $realisasi = number_format((float)($trx['realisasi'] ?? 0), 0, ',', '.');
        $lk = number_format((float)($trx['lebih_kurang'] ?? 0), 0, ',', '.');
        fputcsv($out, [
            tanggal_id($trx['date']),
            $trx['type'],
            $trx['remark'],
            $trx['reference_no'],
            $trx['car_name'],
            $trx['plate_number'] ?? '-',
            $trx['driver_name'],
            $trx['type'] === 'Uang Jalan Driver' ? $umk : '-',
            $trx['type'] === 'Uang Jalan Driver' ? $realisasi : '-',
            $trx['type'] === 'Uang Jalan Driver' ? $lk : '-',
        ], ';');
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
                    <th>Reference No.</th>
                    <th>Mobil</th>
                    <th>Plat Nomor</th>
                    <th>Driver</th>
                    <th style="text-align:right">UMK (Uang Muka)</th>
                    <th style="text-align:right">Realisasi</th>
                    <th style="text-align:right">Lebih / Kurang</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($displayTransactions as $trx): ?>
                <tr>
                    <td style="white-space:nowrap"><strong><?= e(tanggal_id($trx['date'])) ?></strong></td>
                    <td>
                        <span class="badge <?= $trx['type'] === 'Dropping Anggaran' ? 'badge-success' : 'badge-warning' ?>">
                            <?= $trx['type'] === 'Dropping Anggaran' ? '📥' : '📤' ?> <?= e($trx['type']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($trx['booking_id']): ?>
                            <a href="<?= e(base_path('booking_detail.php?id='.$trx['booking_id'])) ?>"
                               style="color:var(--blue);font-weight:700;text-decoration:none"><?= e($trx['reference_no']) ?></a>
                        <?php else: ?>
                            <span style="color:var(--blue);font-weight:700"><?= e($trx['reference_no']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12.5px"><?= e($trx['car_name']) ?></td>
                    <td style="font-size:12.5px">
                        <?php if (($trx['plate_number'] ?? '-') !== '-'): ?>
                            <span style="font-size:12px;color:var(--text);font-weight:600"><?= e($trx['plate_number']) ?></span>
                        <?php else: ?>
                            <span style="color:var(--muted)">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12.5px"><?= e($trx['driver_name']) ?></td>
                    <?php if ($trx['type'] === 'Uang Jalan Driver'): ?>
                        <td style="text-align:right">
                            <strong style="color:var(--blue)"><?= e(rupiah($trx['umk'] ?? 0)) ?></strong>
                        </td>
                        <td style="text-align:right">
                            <strong style="color:var(--text)"><?= e(rupiah($trx['realisasi'] ?? 0)) ?></strong>
                        </td>
                        <td style="text-align:right">
                            <?php $lk = (float)($trx['lebih_kurang'] ?? 0); ?>
                            <strong style="color:<?= $lk >= 0 ? 'var(--green)' : 'var(--red)' ?>">
                                <?= e(rupiah($lk)) ?>
                            </strong>
                        </td>
                    <?php else: ?>
                        <td style="text-align:center;color:var(--muted);font-size:12px">—</td>
                        <td style="text-align:center;color:var(--muted);font-size:12px">—</td>
                        <td style="text-align:center;color:var(--muted);font-size:12px">—</td>
                    <?php endif; ?>
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
                    <td style="text-align:right;color:var(--blue)"><?= e(rupiah($totalUmk)) ?></td>
                    <td style="text-align:right;color:var(--text)"><?= e(rupiah($totalRealisasi)) ?></td>
                    <td style="text-align:right;color:<?= $totalLebihKurang >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= e(rupiah($totalLebihKurang)) ?></td>
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
