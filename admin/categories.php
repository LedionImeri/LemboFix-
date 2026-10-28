<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_login(ROLE_ADMIN);
$iconChoices = ['droplet', 'zap', 'sparkles', 'car', 'monitor', 'plug', 'brush', 'truck', 'leaf', 'scissors', 'camera', 'wrench', 'home', 'shield', 'briefcase', 'layers'];

if (is_post()) {
    require_csrf();
    $id = input_int('id');
    switch (input('action')) {
        case 'save':
            $v = new Validator($_POST);
            $v->required('name', 'Name')->length('name', 'Name', 2, 80)
              ->length('description', 'Description', 0, 255)
              ->in('icon', $iconChoices, 'Please choose an icon.')
              ->number('sort_order', 'Sort order', 0, 999);
            $slug = slugify($v->value('name'));
            if (!$v->fails() && db_value('SELECT id FROM categories WHERE (name = ? OR slug = ?) AND id <> ?', [$v->value('name'), $slug, $id])) {
                $v->custom(true, 'name', 'A category with this name already exists.');
            }
            if ($v->fails()) {
                api_respond(false, $v->firstError(), ['errors' => $v->errors()]);
            }
            $values = [$v->value('name'), $slug, $v->value('icon'), $v->value('description') ?: null, (int) $v->value('sort_order'), empty($_POST['is_active']) ? 0 : 1];
            if ($id) {
                db_query('UPDATE categories SET name = ?, slug = ?, icon = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?', array_merge($values, [$id]));
            } else {
                db_query('INSERT INTO categories (name, slug, icon, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)', $values);
            }
            log_activity((int) $admin['id'], 'category_save', ($id ? 'Updated' : 'Created') . ' category ' . $v->value('name'), 'category', $id ?: null);
            api_respond(true, $id ? 'Category updated.' : 'Category created.');
        case 'delete':
            $inUse = (int) db_value('SELECT COUNT(*) FROM services WHERE category_id = ?', [$id]);
            if ($inUse) {
                api_respond(false, "This category still has $inUse service type(s). Deactivate it instead, or move its services first.");
            }
            db_query('DELETE FROM categories WHERE id = ?', [$id]);
            log_activity((int) $admin['id'], 'category_delete', 'Deleted a category', 'category', $id);
            api_respond(true, 'Category deleted.');
    }
    api_respond(false, 'Unknown action.', [], 400);
}

$categories = db_all(
    "SELECT c.*, (SELECT COUNT(*) FROM services s WHERE s.category_id = c.id) AS service_types,
            (SELECT COUNT(DISTINCT pc.provider_id) FROM provider_categories pc WHERE pc.category_id = c.id) AS providers
     FROM categories c ORDER BY c.sort_order, c.name"
);

dashboard_start([
    'title'    => 'Categories',
    'subtitle' => 'Organise the marketplace. Inactive categories are hidden from customers.',
    'active'   => 'categories',
    'actions'  => '<button type="button" class="btn btn-primary" data-modal-open="#category-modal" data-fill-id="" data-fill-name="" data-fill-description="" data-fill-icon="wrench" data-fill-sort-order="' . (count($categories) + 1) . '" data-fill-is-active="1" data-fill-modal-title="New category">' . icon('plus', 18) . ' New category</button>',
]);
?>
<div class="card">
    <?php if ($categories): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Category</th><th>Slug</th><th>Service types</th><th>Providers</th><th>Order</th><th>Status</th><th class="right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td><div class="cell-user"><span class="category-icon" style="width:38px;height:38px"><?= icon($c['icon'], 18) ?></span><div><strong><?= e($c['name']) ?></strong><span><?= e(excerpt((string) $c['description'], 60)) ?></span></div></div></td>
                    <td class="text-sm text-muted"><?= e($c['slug']) ?></td>
                    <td><?= (int) $c['service_types'] ?></td>
                    <td><?= (int) $c['providers'] ?></td>
                    <td><?= (int) $c['sort_order'] ?></td>
                    <td><?= $c['is_active'] ? status_badge('active') : '<span class="badge badge-neutral"><span class="badge-dot"></span>Inactive</span>' ?></td>
                    <td>
                        <div class="actions">
                            <button type="button" class="btn btn-xs btn-secondary" data-modal-open="#category-modal" data-fill-id="<?= (int) $c['id'] ?>" data-fill-name="<?= e($c['name']) ?>"
                                    data-fill-description="<?= e($c['description']) ?>" data-fill-icon="<?= e($c['icon']) ?>" data-fill-sort-order="<?= (int) $c['sort_order'] ?>"
                                    data-fill-is-active="<?= $c['is_active'] ? '1' : '0' ?>" data-fill-modal-title="Edit category"><?= icon('edit', 14) ?> Edit</button>
                            <form method="post" action="<?= e(url('admin/categories.php')) ?>" data-ajax data-on-success="reload">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger-soft" data-confirm="Delete <?= e($c['name']) ?>?" data-confirm-text="Only empty categories can be deleted." data-confirm-ok="Delete"><?= icon('trash', 14) ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <?= empty_state('No categories yet', 'Create the first category to organise services.', 'grid') ?>
    <?php endif; ?>
</div>

<div class="modal" id="category-modal" role="dialog" aria-modal="true" aria-labelledby="category-modal-title" hidden>
    <div class="modal-backdrop" data-modal-close></div>
    <form class="modal-dialog" method="post" action="<?= e(url('admin/categories.php')) ?>" data-ajax data-on-success="reload" data-validate novalidate>
        <div class="modal-head"><h3 id="category-modal-title" data-fill-text="modal_title">Category</h3><button type="button" class="icon-btn" aria-label="Close" data-modal-close><?= icon('x', 20) ?></button></div>
        <div class="modal-body">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="">
            <label class="field"><span class="field-label">Name</span><input class="input" name="name" required minlength="2" maxlength="80"></label>
            <label class="field"><span class="field-label">Description</span><input class="input" name="description" maxlength="255"></label>
            <div class="form-row">
                <label class="field"><span class="field-label">Icon</span>
                    <select class="select" name="icon"><?php foreach ($iconChoices as $ic): ?><option value="<?= e($ic) ?>"><?= e(ucfirst($ic)) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span class="field-label">Sort order</span><input class="input" type="number" name="sort_order" min="0" max="999" required></label>
            </div>
            <label class="switch"><input type="checkbox" name="is_active" value="1" checked><span class="switch-track"></span> Visible to customers</label>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-secondary" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary"><?= icon('check', 18) ?> Save</button></div>
    </form>
</div>
<?php dashboard_end(); ?>
