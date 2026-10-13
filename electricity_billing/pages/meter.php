<?php
require_once __DIR__ . '/../config/app.php';
require_login();

$editing = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId) {
    $stmt = $conn->prepare('SELECT id, customer_id, meter_number, unit_rate FROM meters WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if ($action === 'delete' && $id) {
        try {
            $stmt = $conn->prepare('DELETE FROM meters WHERE id = ?');
            $stmt->execute([$id]);
            set_flash('success', 'Meter deleted.');
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            set_flash('error', 'This meter has bills and cannot be deleted.');
        }
        header('Location: meter.php');
        exit;
    }

    $customerId = filter_var($_POST['customer_id'] ?? null, FILTER_VALIDATE_INT);
    $meterNumber = trim((string) ($_POST['meter_number'] ?? ''));
    $rateInput = $_POST['unit_rate'] ?? '';
    if (!$customerId || $meterNumber === '' || mb_strlen($meterNumber) > 100 || !is_numeric($rateInput) || (float) $rateInput <= 0 || (float) $rateInput > 99999999) {
        set_flash('error', 'Choose a customer and enter a meter number and positive unit rate.');
        header('Location: meter.php' . ($id ? '?edit=' . $id : ''));
        exit;
    }

    try {
        if ($id) {
            $stmt = $conn->prepare('UPDATE meters SET customer_id = ?, meter_number = ?, unit_rate = ? WHERE id = ?');
            $stmt->execute([$customerId, $meterNumber, $rateInput, $id]);
            set_flash('success', 'Meter details updated.');
        } else {
            $stmt = $conn->prepare('INSERT INTO meters (customer_id, meter_number, unit_rate) VALUES (?, ?, ?)');
            $stmt->execute([$customerId, $meterNumber, $rateInput]);
            set_flash('success', 'Meter added successfully.');
        }
    } catch (PDOException $exception) {
        if ($exception->getCode() !== '23000') {
            throw $exception;
        }
        set_flash('error', 'That meter number is already in use, or the selected customer no longer exists.');
        header('Location: meter.php' . ($id ? '?edit=' . $id : ''));
        exit;
    }
    header('Location: meter.php');
    exit;
}

$customers = $conn->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
$meters = $conn->query('SELECT m.id, m.customer_id, m.meter_number, m.unit_rate, c.name AS customer_name FROM meters m JOIN customers c ON c.id = m.customer_id ORDER BY m.id DESC')->fetchAll();
page_header('Meters', 'meters');
?>
<div class="page-intro"><div><h2>Meter management</h2><p>Connect each electricity meter to a customer and set its unit rate.</p></div><span class="count-pill"><?= count($meters) ?> total</span></div>
<div class="management-grid">
    <section class="panel form-panel"><div class="panel-heading"><div><h3><?= $editing ? 'Edit meter' : 'Register a meter' ?></h3><p>Meter numbers must be unique.</p></div></div>
        <?php if (!$customers): ?><div class="notice">Add a customer before registering a meter.</div><a class="button button-primary" href="customer.php">Add customer</a><?php else: ?>
        <form method="post" class="stack-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
            <label for="customer_id">Customer</label><select id="customer_id" name="customer_id" required><option value="">Select a customer</option><?php foreach ($customers as $customer): ?><option value="<?= (int) $customer['id'] ?>" <?= (int) ($editing['customer_id'] ?? 0) === (int) $customer['id'] ? 'selected' : '' ?>><?= e($customer['name']) ?></option><?php endforeach; ?></select>
            <label for="meter_number">Meter number</label><input id="meter_number" name="meter_number" maxlength="100" value="<?= e($editing['meter_number'] ?? '') ?>" placeholder="e.g. MTR-000125" required>
            <label for="unit_rate">Price per unit (Rs.)</label><input id="unit_rate" name="unit_rate" type="number" min="0.01" step="0.01" value="<?= e($editing['unit_rate'] ?? '') ?>" placeholder="e.g. 45.00" required>
            <div class="form-actions"><button class="button button-primary" name="action" value="save" type="submit"><?= $editing ? 'Save changes' : 'Register meter' ?></button><?php if ($editing): ?><a class="button button-quiet" href="meter.php">Cancel</a><?php endif; ?></div>
        </form><?php endif; ?>
    </section>
    <section class="panel directory-panel"><div class="panel-heading"><div><h3>Registered meters</h3><p>Each meter is linked to one customer.</p></div></div>
        <?php if (!$meters): ?><div class="empty-state compact"><strong>No meters registered</strong><p>Register a meter to start generating bills.</p></div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Meter</th><th>Customer</th><th>Rate / unit</th><th>Actions</th></tr></thead><tbody><?php foreach ($meters as $meter): ?>
            <tr><td class="table-strong"><?= e($meter['meter_number']) ?></td><td><?= e($meter['customer_name']) ?></td><td>Rs. <?= number_format((float) $meter['unit_rate'], 2) ?></td><td class="actions"><a class="row-action" href="meter.php?edit=<?= (int) $meter['id'] ?>">Edit</a><form method="post" class="inline-form" data-confirm="Delete this meter? Meters with bills cannot be deleted."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $meter['id'] ?>"><button class="row-action row-danger" name="action" value="delete" type="submit">Delete</button></form></td></tr>
        <?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
</div>
<?php page_footer(); ?>
