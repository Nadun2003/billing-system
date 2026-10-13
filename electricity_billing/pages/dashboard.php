<?php
require_once __DIR__ . '/../config/app.php';
require_login();

$customerCount = (int) $conn->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$meterCount = (int) $conn->query('SELECT COUNT(*) FROM meters')->fetchColumn();
$billCount = (int) $conn->query('SELECT COUNT(*) FROM bills')->fetchColumn();
$paymentTotal = (float) $conn->query('SELECT COALESCE(SUM(amount), 0) FROM payments')->fetchColumn();
$outstanding = (float) $conn->query('SELECT COALESCE(SUM(b.total_amount - COALESCE(p.paid, 0)), 0) FROM bills b LEFT JOIN (SELECT bill_id, SUM(amount) AS paid FROM payments GROUP BY bill_id) p ON p.bill_id = b.id WHERE b.total_amount > COALESCE(p.paid, 0)')->fetchColumn();
$recentBills = $conn->query('SELECT b.id, b.bill_date, b.total_amount, m.meter_number, c.name AS customer_name, COALESCE(p.paid, 0) AS paid FROM bills b JOIN meters m ON m.id = b.meter_id JOIN customers c ON c.id = m.customer_id LEFT JOIN (SELECT bill_id, SUM(amount) AS paid FROM payments GROUP BY bill_id) p ON p.bill_id = b.id ORDER BY b.id DESC LIMIT 6')->fetchAll();

page_header('Dashboard', 'dashboard');
?>
<div class="welcome-row"><div><h2>Good to see you, <?= e($_SESSION['user']) ?> <span class="wave">✦</span></h2><p>Here is what is happening with your billing system today.</p></div><a class="button button-primary" href="bill.php">＋ Create a bill</a></div>
<div class="stats-grid">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total customers</span><span class="stat-icon icon-violet">C</span></div><div class="stat-value"><?= number_format($customerCount) ?></div><a href="customer.php" class="stat-link">View customers <span>↗</span></a></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Active meters</span><span class="stat-icon icon-blue">M</span></div><div class="stat-value"><?= number_format($meterCount) ?></div><a href="meter.php" class="stat-link">Manage meters <span>↗</span></a></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Bills generated</span><span class="stat-icon icon-amber">B</span></div><div class="stat-value"><?= number_format($billCount) ?></div><a href="bill.php" class="stat-link">View all bills <span>↗</span></a></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Collected payments</span><span class="stat-icon icon-green">₨</span></div><div class="stat-value stat-money">Rs. <?= number_format($paymentTotal, 2) ?></div><span class="stat-foot">Outstanding: Rs. <?= number_format($outstanding, 2) ?></span></article>
</div>
<section class="panel">
    <div class="panel-heading"><div><h3>Recent bills</h3><p>The latest billing activity across your customers.</p></div><a class="text-link" href="bill.php">All bills <span>→</span></a></div>
    <?php if (!$recentBills): ?>
        <div class="empty-state"><span class="empty-icon">▤</span><strong>No bills yet</strong><p>Add a customer and meter, then create your first bill.</p><a class="button button-secondary" href="customer.php">Add a customer</a></div>
    <?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Bill</th><th>Customer</th><th>Meter</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($recentBills as $bill): $isPaid = (float) $bill['paid'] >= (float) $bill['total_amount']; ?>
            <tr><td class="table-strong">#<?= (int) $bill['id'] ?></td><td><?= e($bill['customer_name']) ?></td><td><?= e($bill['meter_number']) ?></td><td><?= e($bill['bill_date']) ?></td><td>Rs. <?= number_format((float) $bill['total_amount'], 2) ?></td><td><span class="badge <?= $isPaid ? 'badge-paid' : 'badge-due' ?>"><?= $isPaid ? 'Paid' : 'Due' ?></span></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
