<?php
require_once __DIR__ . '/../config/app.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $billId = filter_var($_POST['bill_id'] ?? null, FILTER_VALIDATE_INT);
    $method = $_POST['method'] ?? '';
    $amountInput = $_POST['amount'] ?? '';
    $allowedMethods = ['Cash', 'Card', 'Online', 'Bank transfer'];

    if (!$billId || !in_array($method, $allowedMethods, true) || !is_numeric($amountInput) || (float) $amountInput <= 0) {
        set_flash('error', 'Select an unpaid bill, payment method, and a valid payment amount.');
        header('Location: payment.php');
        exit;
    }

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare('SELECT total_amount FROM bills WHERE id = ? FOR UPDATE');
        $stmt->execute([$billId]);
        $total = $stmt->fetchColumn();
        if ($total === false) {
            throw new DomainException('The selected bill could not be found.');
        }

        $stmt = $conn->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE bill_id = ?');
        $stmt->execute([$billId]);
        $paid = (float) $stmt->fetchColumn();
        $balance = round((float) $total - $paid, 2);
        $amount = round((float) $amountInput, 2);
        if ($balance <= 0) {
            throw new DomainException('That bill has already been paid.');
        }
        if ($amount > $balance) {
            throw new DomainException('Payment cannot be greater than the remaining balance of Rs. ' . number_format($balance, 2) . '.');
        }

        $stmt = $conn->prepare('INSERT INTO payments (bill_id, method, amount, payment_date) VALUES (?, ?, ?, CURDATE())');
        $stmt->execute([$billId, $method, $amount]);
        $conn->commit();
        set_flash('success', 'Payment of Rs. ' . number_format($amount, 2) . ' recorded successfully.');
    } catch (DomainException $exception) {
        $conn->rollBack();
        set_flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        $conn->rollBack();
        throw $exception;
    }
    header('Location: payment.php');
    exit;
}

$unpaidBills = $conn->query('SELECT b.id, b.total_amount, b.bill_date, m.meter_number, c.name AS customer_name, ROUND(b.total_amount - COALESCE(p.paid, 0), 2) AS balance FROM bills b JOIN meters m ON m.id = b.meter_id JOIN customers c ON c.id = m.customer_id LEFT JOIN (SELECT bill_id, SUM(amount) AS paid FROM payments GROUP BY bill_id) p ON p.bill_id = b.id WHERE b.total_amount > COALESCE(p.paid, 0) ORDER BY b.id')->fetchAll();
$payments = $conn->query('SELECT p.id, p.amount, p.method, p.payment_date, b.id AS bill_id, c.name AS customer_name, m.meter_number FROM payments p JOIN bills b ON b.id = p.bill_id JOIN meters m ON m.id = b.meter_id JOIN customers c ON c.id = m.customer_id ORDER BY p.id DESC')->fetchAll();
$collectedToday = (float) $conn->query('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_date = CURDATE()')->fetchColumn();
$collectedTotal = (float) $conn->query('SELECT COALESCE(SUM(amount), 0) FROM payments')->fetchColumn();
page_header('Payments', 'payments');
?>
<div class="page-intro"><div><h2>Payment collection</h2><p>Record full or partial payments against outstanding bills.</p></div><span class="count-pill"><?= count($unpaidBills) ?> bills due</span></div>
<div class="stats-grid payment-stats">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Collected today</span><span class="stat-icon icon-green">₨</span></div><div class="stat-value stat-money">Rs. <?= number_format($collectedToday, 2) ?></div><span class="stat-foot">Payments recorded today</span></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">All-time collections</span><span class="stat-icon icon-blue">↗</span></div><div class="stat-value stat-money">Rs. <?= number_format($collectedTotal, 2) ?></div><span class="stat-foot">Total recorded payments</span></article>
</div>
<section class="panel"><div class="panel-heading"><div><h3>Record a payment</h3><p>Choose a bill and enter the amount received.</p></div></div>
    <?php if (!$unpaidBills): ?><div class="notice notice-success">There are no unpaid bills at the moment.</div><?php else: ?>
    <form method="post" class="inline-fields payment-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="field-grow"><label for="bill_id">Outstanding bill</label><select id="bill_id" name="bill_id" data-bill-select required><option value="">Choose a bill</option><?php foreach ($unpaidBills as $bill): ?><option value="<?= (int) $bill['id'] ?>" data-balance="<?= e($bill['balance']) ?>">#<?= (int) $bill['id'] ?> · <?= e($bill['customer_name']) ?> · <?= e($bill['meter_number']) ?> · Due Rs. <?= number_format((float) $bill['balance'], 2) ?></option><?php endforeach; ?></select></div>
        <div class="field-amount"><label for="amount">Amount received (Rs.)</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" data-payment-amount placeholder="Enter amount" required><small data-balance-hint>Select a bill to see the remaining balance.</small></div>
        <div class="field-method"><label for="method">Method</label><select id="method" name="method" required><option value="Cash">Cash</option><option value="Card">Card</option><option value="Online">Online</option><option value="Bank transfer">Bank transfer</option></select></div>
        <button class="button button-primary align-end" type="submit">Record payment <span>→</span></button>
    </form><?php endif; ?>
</section>
<section class="panel"><div class="panel-heading"><div><h3>Payment history</h3><p>Latest payments recorded in the system.</p></div></div>
    <?php if (!$payments): ?><div class="empty-state compact"><strong>No payments recorded</strong><p>Payments will appear here after they are entered.</p></div><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Receipt</th><th>Customer / meter</th><th>Bill</th><th>Date</th><th>Method</th><th>Amount</th></tr></thead><tbody><?php foreach ($payments as $payment): ?>
        <tr><td class="table-strong">#<?= (int) $payment['id'] ?></td><td><?= e($payment['customer_name']) ?><small class="cell-sub"><?= e($payment['meter_number']) ?></small></td><td>#<?= (int) $payment['bill_id'] ?></td><td><?= e($payment['payment_date']) ?></td><td><span class="method-chip"><?= e($payment['method']) ?></span></td><td class="table-strong">Rs. <?= number_format((float) $payment['amount'], 2) ?></td></tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php page_footer(); ?>
