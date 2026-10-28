<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login(ROLE_PROVIDER);
$provider = current_provider();
$services = provider_services_list((int) $provider['id'], false);
$catalog = db_all(
    'SELECT s.id, s.name, c.name AS category FROM services s JOIN categories c ON c.id = s.category_id
     WHERE s.is_active = 1 AND c.is_active = 1 ORDER BY c.sort_order, s.name'
);
$catalogByCategory = [];
foreach ($catalog as $s) {
    $catalogByCategory[$s['category']][] = $s;
}

dashboard_start([
    'title'    => 'Services',
    'subtitle' => 'What you offer, how long it takes and what it costs.',
    'active'   => 'services',
    'actions'  => '<button type="button" class="btn btn-primary" data-modal-open="#service-modal" data-fill-id="" data-fill-service-id="" data-fill-title="" data-fill-description="" data-fill-price="" data-fill-price-type="fixed" data-fill-duration-minutes="60" data-fill-location-type="customer" data-fill-is-active="1" data-fill-modal-title="Add service">'
        . icon('plus', 18) . ' Add service</button>',
]);
?>
<?php if ($services): ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Service</th><th>Category</th><th>Price</th><th>Duration</th><th>Location</th><th>Status</th><th class="right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($services as $s): ?>
                    <tr>
                        <td><strong><?= e($s['title']) ?></strong><br><span class="text-xs text-muted"><?= e(excerpt((string) $s['description'], 70)) ?></span></td>
                        <td><span class="chip"><?= icon($s['category_icon'], 14) ?> <?= e($s['service_name']) ?></span></td>
                        <td class="nowrap fw-600"><?= price_label($s['price'], $s['price_type']) ?></td>
                        <td class="nowrap"><?= e(duration_label((int) $s['duration_minutes'])) ?></td>
                        <td><?= e(['customer' => 'Customer\'s place', 'provider' => 'My location', 'remote' => 'Remote'][$s['location_type']]) ?></td>
                        <td><?= $s['is_active'] ? '<span class="badge badge-success"><span class="badge-dot"></span>Active</span>' : '<span class="badge badge-neutral"><span class="badge-dot"></span>Hidden</span>' ?></td>
                        <td>
                            <div class="actions">
                                <button type="button" class="btn btn-xs btn-secondary" data-modal-open="#service-modal"
                                        data-fill-id="<?= (int) $s['id'] ?>" data-fill-service-id="<?= (int) $s['service_id'] ?>"
                                        data-fill-title="<?= e($s['title']) ?>" data-fill-description="<?= e($s['description']) ?>"
                                        data-fill-price="<?= e($s['price']) ?>" data-fill-price-type="<?= e($s['price_type']) ?>"
                                        data-fill-duration-minutes="<?= (int) $s['duration_minutes'] ?>" data-fill-location-type="<?= e($s['location_type']) ?>"
                                        data-fill-is-active="<?= $s['is_active'] ? '1' : '0' ?>" data-fill-modal-title="Edit service">
                                    <?= icon('edit', 14) ?> Edit
                                </button>
                                <form method="post" action="<?= e(url('api/services.php')) ?>" data-ajax data-on-success="reload">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-danger-soft" data-confirm="Delete “<?= e($s['title']) ?>”?"
                                            data-confirm-text="Customers won't be able to book it anymore. Past bookings keep their details." data-confirm-ok="Delete service">
                                        <?= icon('trash', 14) ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card"><?= empty_state('No services yet', 'Add your first service so customers can book you. You can add as many as you like.', 'layers',
        '<button type="button" class="btn btn-primary" data-modal-open="#service-modal" data-fill-modal-title="Add service" data-fill-is-active="1">' . icon('plus', 18) . ' Add your first service</button>') ?></div>
<?php endif; ?>

<div class="modal" id="service-modal" role="dialog" aria-modal="true" aria-labelledby="service-modal-title" hidden>
    <div class="modal-backdrop" data-modal-close></div>
    <form class="modal-dialog modal-lg" method="post" action="<?= e(url('api/services.php')) ?>" data-ajax data-on-success="reload" data-validate novalidate>
        <div class="modal-head">
            <h3 id="service-modal-title" data-fill-text="modal_title">Add service</h3>
            <button type="button" class="icon-btn" aria-label="Close" data-modal-close><?= icon('x', 20) ?></button>
        </div>
        <div class="modal-body">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="">
            <label class="field">
                <span class="field-label">Service type</span>
                <select class="select" name="service_id" required>
                    <option value="">Choose from the catalog…</option>
                    <?php foreach ($catalogByCategory as $category => $items): ?>
                        <optgroup label="<?= e($category) ?>">
                            <?php foreach ($items as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e($item['name']) ?></option><?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <span class="field-hint">Helps customers find you in search. Missing a type? Contact support.</span>
            </label>
            <label class="field">
                <span class="field-label">Title shown to customers</span>
                <input class="input" name="title" required minlength="3" maxlength="140" placeholder="e.g. Kitchen sink leak repair">
            </label>
            <label class="field">
                <span class="field-label">Description</span>
                <textarea class="textarea" name="description" rows="3" maxlength="1000" placeholder="What's included? Any materials or limits?"></textarea>
            </label>
            <div class="form-row-3">
                <label class="field">
                    <span class="field-label">Price (€)</span>
                    <input class="input" type="number" name="price" min="0" max="100000" step="0.5" required>
                </label>
                <label class="field">
                    <span class="field-label">Pricing</span>
                    <select class="select" name="price_type">
                        <option value="fixed">Fixed price</option>
                        <option value="from">Starting from</option>
                        <option value="hourly">Per hour</option>
                    </select>
                </label>
                <label class="field">
                    <span class="field-label">Duration</span>
                    <select class="select" name="duration_minutes" required>
                        <?php foreach ([15, 30, 45, 60, 75, 90, 120, 150, 180, 240, 300, 360, 480, 600] as $m): ?>
                            <option value="<?= $m ?>"><?= e(duration_label($m)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="form-row">
                <label class="field">
                    <span class="field-label">Location</span>
                    <select class="select" name="location_type">
                        <option value="customer">At the customer's place</option>
                        <option value="provider">At my location</option>
                        <option value="remote">Remote / online</option>
                    </select>
                </label>
                <div class="field">
                    <span class="field-label">Visibility</span>
                    <label class="switch mt-1"><input type="checkbox" name="is_active" value="1" checked><span class="switch-track"></span> Bookable</label>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
            <button type="submit" class="btn btn-primary"><?= icon('check', 18) ?> Save service</button>
        </div>
    </form>
</div>
<?php dashboard_end(); ?>
