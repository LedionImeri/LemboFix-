<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_login(ROLE_ADMIN);
$categories = db_all('SELECT id, name FROM categories ORDER BY sort_order, name');

if (is_post()) {
    require_csrf();
    $id = input_int('id');
    switch (input('action')) {
        case 'save':
            $v = new Validator($_POST);
            $v->required('name', 'Name')->length('name', 'Name', 2, 120)
              ->required('category_id', 'Category')
              ->in('category_id', array_map('strval', array_column($categories, 'id')), 'Please choose a category.')
              ->length('description', 'Description', 0, 255);
            $slug = slugify($v->value('name'));
            if (!$v->fails() && db_value('SELECT id FROM services WHERE slug = ? AND id <> ?', [$slug, $id])) {
                $v->custom(true, 'name', 'A service type with this name already exists.');
            }
            if ($v->fails()) {
                api_respond(false, $v->firstError(), ['errors' => $v->errors()]);
            }
            $values = [(int) $v->value('category_id'), $v->value('name'), $slug, $v->value('description') ?: null, empty($_POST['is_active']) ? 0 : 1];
            if ($id) {
                db_query('UPDATE services SET category_id = ?, name = ?, slug = ?, description = ?, is_active = ? WHERE id = ?', array_merge($values, [$id]));
            } else {
                db_query('INSERT INTO services (category_id, name, slug, description, is_active) VALUES (?, ?, ?, ?, ?)', $values);
            }
            log_activity((int) $admin['id'], 'service_type_save', ($id ? 'Updated' : 'Created') . ' service type ' . $v->value('name'), 'service', $id ?: null);
            api_respond(true, $id ? 'Service type updated.' : 'Service type created.');
        case 'delete':
            $inUse = (int) db_value('SELECT COUNT(*) FROM provider_services WHERE service_id = ?', [$id]);
            if ($inUse) {
                api_respond(false, "$inUse provider service(s) use this type. Deactivate it instead.");
            }
            db_query('DELETE FROM services WHERE id = ?', [$id]);
            api_respond(true, 'Service type deleted.');
    }
    api_respond(false, 'Unknown action.', [], 400);
}

$categoryFilter = input_int('category');
$params = [];
$where = '1=1';
if ($categoryFilter) {
    $where = 's.category_id = ?';
    $params[] = $categoryFilter;
}
$services = db_all(
    "SELECT s.*, c.name AS category, c.icon,
            (SELECT COUNT(*) FROM provider_services ps WHERE ps.service_id = s.id) AS offers,
            (SELECT MIN(ps.price) FROM provider_services ps WHERE ps.service_id = s.id AND ps.is_active = 1) AS min_price
     FROM services s JOIN categories c ON c.id = s.category_id WHERE $where ORDER BY c.sort_order, s.name",
    $params
);

dashboard_start([
    'title'    => 'Service catalog',
    'subtitle' => 'Service types providers can offer. Providers set their own title, price and duration.',
    'active'   => 'services',
    'actions'  => '<button type="button" class="btn btn-primary" data-modal-open="#service-type-modal" data-fill-id="" data-fill-name="" data-fill-description="" data-fill-category-id="" data-fill-is-active="1" data-fill-modal-title="New service type">' . icon('plus', 18) . ' New service type</button>',
]);
?>
<form class="toolbar" method="get" action="<?= e(url('admin/services.php')) ?>" data-auto-submit>
    <select class="select" name="category" aria-label="Category">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], $categoryFilter) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <span class="text-sm text-muted"><?= plural(count($services), 'service type') ?></span>
</form>
<div class="card">
    <?php if ($services): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Service type</th><th>Category</th><th>Provider offers</th><th>From</th><th>Status</th><th class="right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($services as $s): ?>
                <tr>
                    <td><strong><?= e($s['name']) ?></strong><br><span class="text-xs text-muted"><?= e($s['description']) ?></span></td>
                    <td><span class="chip"><?= icon($s['icon'], 14) ?> <?= e($s['category']) ?></span></td>
                    <td><?= (int) $s['offers'] ?></td>
                    <td><?= $s['min_price'] !== null ? money($s['min_price']) : '—' ?></td>
                    <td><?= $s['is_active'] ? status_badge('active') : '<span class="badge badge-neutral"><span class="badge-dot"></span>Inactive</span>' ?></td>
                    <td>
                        <div class="actions">
                            <button type="button" class="btn btn-xs btn-secondary" data-modal-open="#service-type-modal" data-fill-id="<?= (int) $s['id'] ?>" data-fill-name="<?= e($s['name']) ?>"
                                    data-fill-description="<?= e($s['description']) ?>" data-fill-category-id="<?= (int) $s['category_id'] ?>" data-fill-is-active="<?= $s['is_active'] ? '1' : '0' ?>"
                                    data-fill-modal-title="Edit service type"><?= icon('edit', 14) ?> Edit</button>
                            <form method="post" action="<?= e(url('admin/services.php')) ?>" data-ajax data-on-success="reload">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger-soft" data-confirm="Delete <?= e($s['name']) ?>?" data-confirm-text="Only unused service types can be deleted." data-confirm-ok="Delete"><?= icon('trash', 14) ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <?= empty_state('No service types', 'Add service types so providers can list what they offer.', 'layers') ?>
    <?php endif; ?>
</div>

<div class="modal" id="service-type-modal" role="dialog" aria-modal="true" aria-labelledby="st-title" hidden>
    <div class="modal-backdrop" data-modal-close></div>
    <form class="modal-dialog" method="post" action="<?= e(url('admin/services.php')) ?>" data-ajax data-on-success="reload" data-validate novalidate>
        <div class="modal-head"><h3 id="st-title" data-fill-text="modal_title">Service type</h3><button type="button" class="icon-btn" aria-label="Close" data-modal-close><?= icon('x', 20) ?></button></div>
        <div class="modal-body">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="">
            <label class="field"><span class="field-label">Name</span><input class="input" name="name" required minlength="2" maxlength="120"></label>
            <label class="field"><span class="field-label">Category</span>
                <select class="select" name="category_id" required><option value="">Choose…</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span class="field-label">Short description</span><input class="input" name="description" maxlength="255"></label>
            <label class="switch"><input type="checkbox" name="is_active" value="1" checked><span class="switch-track"></span> Available to providers</label>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-secondary" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary"><?= icon('check', 18) ?> Save</button></div>
    </form>
</div>
<?php dashboard_end(); ?>
