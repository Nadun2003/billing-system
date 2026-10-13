<?php
require_once __DIR__ . '/../config/app.php';
require_login();

$editing = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId) {
    $stmt = $conn->prepare('SELECT id, name, address, phone FROM customers WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'delete' && $id) {
        try {
            $stmt = $conn->prepare('DELETE FROM customers WHERE id = ?');
            $stmt->execute([$id]);
            set_flash('success', 'Customer deleted.');
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            set_flash('error', 'This customer has meters or billing records and cannot be deleted.');
        }
        header('Location: customer.php');
        exit;
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    if ($name === '' || mb_strlen($name) > 150 || $address === '' || mb_strlen($address) > 255 || $phone === '' || mb_strlen($phone) > 30) {
        set_flash('error', 'Enter a name, address, and phone number within the allowed lengths.');
        header('Location: customer.php' . ($id ? '?edit=' . $id : ''));
        exit;
    }

    if ($id) {
        $stmt = $conn->prepare('UPDATE customers SET name = ?, address = ?, phone = ? WHERE id = ?');
        $stmt->execute([$name, $address, $phone, $id]);
        set_flash('success', 'Customer details updated.');
    } else {
        $stmt = $conn->prepare('INSERT INTO customers (name, address, phone) VALUES (?, ?, ?)');
        $stmt->execute([$name, $address, $phone]);
        set_flash('success', 'Customer added successfully.');
    }
    header('Location: customer.php');
    exit;
}

$customers = $conn->query('SELECT c.id, c.name, c.address, c.phone, COUNT(m.id) AS meter_count FROM customers c LEFT JOIN meters m ON m.customer_id = c.id GROUP BY c.id ORDER BY c.id DESC')->fetchAll();
page_header('Customers', 'customers');
?>
<div class="page-intro"><div><h2>Customer directory</h2><p>Keep customer contact details organised in one place.</p></div><span class="count-pill"><?= count($customers) ?> total</span></div>
<div class="management-grid">
    <section class="panel form-panel"><div class="panel-heading"><div><h3><?= $editing ? 'Edit customer' : 'Add a customer' ?></h3><p><?= $editing ? 'Update this customer’s information.' : 'Enter the customer contact details.' ?></p></div></div>
        <form method="post" class="stack-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
            <label for="name">Full name</label><input id="name" name="name" maxlength="150" value="<?= e($editing['name'] ?? '') ?>" placeholder="e.g. Nimal Perera" required>
            <label for="address">Address</label><textarea id="address" name="address" maxlength="255" rows="3" placeholder="Street, town, city" required><?= e($editing['address'] ?? '') ?></textarea>
            <label for="phone">Phone number</label><input id="phone" name="phone" type="tel" maxlength="30" value="<?= e($editing['phone'] ?? '') ?>" placeholder="e.g. 077 123 4567" required>
            <div class="form-actions"><button class="button button-primary" type="submit" name="action" value="save"><?= $editing ? 'Save changes' : 'Add customer' ?></button><?php if ($editing): ?><a class="button button-quiet" href="customer.php">Cancel</a><?php endif; ?></div>
        </form>
    </section>
    <section class="panel directory-panel"><div class="panel-heading"><div><h3>All customers</h3><p>Browse and maintain your customer list.</p></div></div>
        <?php if (!$customers): ?><div class="empty-state compact"><strong>No customers added</strong><p>Your new customers will appear here.</p></div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Customer</th><th>Phone</th><th>Meters</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($customers as $customer): ?>
            <tr><td><span class="table-strong"><?= e($customer['name']) ?></span><small class="cell-sub"><?= e($customer['address']) ?></small></td><td><?= e($customer['phone']) ?></td><td><?= (int) $customer['meter_count'] ?></td><td class="actions"><a class="row-action" href="customer.php?edit=<?= (int) $customer['id'] ?>">Edit</a><form method="post" class="inline-form" data-confirm="Delete this customer? Customers with meters cannot be deleted."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $customer['id'] ?>"><button class="row-action row-danger" name="action" value="delete" type="submit">Delete</button></form></td></tr>
        <?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>
</div>
<?php page_footer(); ?>
