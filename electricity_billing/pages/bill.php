<?php
require_once __DIR__ . '/../config/app.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $meterId = filter_var($_POST['meter_id'] ?? null, FILTER_VALIDATE_INT);
    $units = $_POST['units'] ?? '';
    if (!$meterId || !is_numeric($units) || (float) $units <= 0 || (float) $units > 99999999) {
        set_flash('error', 'Select a meter and enter a positive number of units.');
        header('Location: bill.php');
        exit;
    }

    $stmt = $conn->prepare('SELECT unit_rate FROM meters WHERE id = ?');
    $stmt->execute([$meterId]);
    $rate = $stmt->fetchColumn();
    if ($rate === false) {
        set_flash('error', 'The selected meter could not be found.');
        header('Location: bill.php');
        exit;
    }

    $amount = round((float) $units * (float) $rate, 2);
    $stmt = $conn->prepare('INSERT INTO bills (meter_id, units, total_amount, bill_date) VALUES (?, ?, ?, CURDATE())');
    $stmt->execute([$meterId, $units, $amount]);
    set_flash('success', 'Bill #' . $conn->lastInsertId() . ' generated for Rs. ' . number_format($amount, 2) . '.');
    header('Location: bill.php');
    exit;
}

$meters = $conn->query('SELECT m.id, m.meter_number, m.unit_rate, c.name AS customer_name FROM meters m JOIN customers c ON c.id = m.customer_id ORDER BY c.name, m.meter_number')->fetchAll();
$bills = $conn->query('SELECT b.id, b.bill_date, b.units, b.total_amount, m.meter_number, c.name AS customer_name, COALESCE(p.paid, 0) AS paid FROM bills b JOIN meters m ON m.id = b.meter_id JOIN customers c ON c.id = m.customer_id LEFT JOIN (SELECT bill_id, SUM(amount) AS paid FROM payments GROUP BY bill_id) p ON p.bill_id = b.id ORDER BY b.id DESC')->fetchAll();
page_header('Bills', 'bills');
?>
<div class="page-intro"><div><h2>Billing centre</h2><p>Generate charges from meter usage and track what is still due.</p></div><span class="count-pill"><?= count($bills) ?> total bills</span></div>
<section class="panel bill-create-panel"><div class="panel-heading"><div><h3>Generate a new bill</h3><p>The total is calculated automatically using the meter’s unit rate.</p></div><span class="panel-symbol">↗</span></div>
    <?php if (!$meters): ?><div class="notice">You need at least one registered meter before you can create a bill.</div><a class="button button-primary" href="meter.php">Register a meter</a><?php else: ?>
    <form method="post" class="inline-fields">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="field-grow"><label for="meter_id">Meter and customer</label><select id="meter_id" name="meter_id" required><option value="">Choose a meter</option><?php foreach ($meters as $meter): ?><option value="<?= (int) $meter['id'] ?>"><?= e($meter['meter_number']) ?> · <?= e($meter['customer_name']) ?> (Rs. <?= number_format((float) $meter['unit_rate'], 2) ?>/unit)</option><?php endforeach; ?></select></div>
        <div class="field-units"><label for="units">Units used</label><input id="units" name="units" type="number" min="0.01" step="0.01" placeholder="e.g. 120" required></div>
        <button class="button button-primary align-end" type="submit">Generate bill <span>→</span></button>
    </form><?php endif; ?>
</section>
<section class="panel"><div class="panel-heading"><div><h3>All bills</h3><p>Bill totals and payment status.</p></div></div>
    <?php if (!$bills): ?><div class="empty-state compact"><strong>No bills generated yet</strong><p>Generated bills will be listed here.</p></div><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Bill</th><th>Customer / meter</th><th>Date</th><th>Units</th><th>Total</th><th>Balance</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($bills as $bill): $balance = max(0, (float) $bill['total_amount'] - (float) $bill['paid']); $isPaid = $balance <= 0.009; ?>
        <tr><td class="table-strong">#<?= (int) $bill['id'] ?></td><td><?= e($bill['customer_name']) ?><small class="cell-sub"><?= e($bill['meter_number']) ?></small></td><td><?= e($bill['bill_date']) ?></td><td><?= number_format((float) $bill['units'], 2) ?></td><td>Rs. <?= number_format((float) $bill['total_amount'], 2) ?></td><td>Rs. <?= number_format($balance, 2) ?></td><td><span class="badge <?= $isPaid ? 'badge-paid' : 'badge-due' ?>"><?= $isPaid ? 'Paid' : 'Due' ?></span></td></tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php page_footer(); ?>
