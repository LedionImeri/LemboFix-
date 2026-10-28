<?php
/** @var array $page */
$isDashboard = str_contains($page['body_class'] ?? '', 'is-dashboard');
?>
</main>

<?php if (!$isDashboard): ?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a class="brand brand-light" href="<?= e(url('index.php')) ?>">
                <span class="brand-mark"><?= icon('wrench', 18) ?></span>
                <span class="brand-word">Lembo<span>Fix</span></span>
            </a>
            <p>Trusted local professionals, real-time availability and simple online booking — all in one place.</p>
            <div class="footer-trust">
                <span><?= icon('shield', 16) ?> Verified pros</span>
                <span><?= icon('lock', 16) ?> Secure booking</span>
            </div>
        </div>
        <div>
            <h4>Popular categories</h4>
            <ul>
                <?php foreach (array_slice(active_categories(), 0, 6) as $cat): ?>
                    <li><a href="<?= e(url('search.php?category=' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div>
            <h4>LemboFix</h4>
            <ul>
                <li><a href="<?= e(url('search.php')) ?>">Find a professional</a></li>
                <li><a href="<?= e(url('index.php#how-it-works')) ?>">How it works</a></li>
                <li><a href="<?= e(url('categories.php')) ?>">All categories</a></li>
                <li><a href="<?= e(url('for-professionals.php')) ?>">Become a pro</a></li>
            </ul>
        </div>
        <div>
            <h4>Support</h4>
            <ul>
                <li><a href="<?= e(url('login.php')) ?>">Log in</a></li>
                <li><a href="<?= e(url('register.php')) ?>">Create account</a></li>
                <li><a href="mailto:support@lembofix.com">support@lembofix.com</a></li>
                <li><button type="button" class="link-btn footer-link" data-lembobot-open>Ask LemboBot</button></li>
            </ul>
        </div>
    </div>
    <div class="container footer-bottom">
        <span>© <?= date('Y') ?> LemboFix. Portfolio project — payments are simulated.</span>
        <span>Made with care in Kosovo 🇽🇰</span>
    </div>
</footer>
<?php endif; ?>

<?php require APP_ROOT . '/includes/partials/lembobot.php'; ?>

<div class="modal" id="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title" hidden>
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-dialog modal-sm">
        <div class="modal-body">
            <div class="confirm-icon" data-confirm-icon><?= icon('alert', 24) ?></div>
            <h3 id="confirm-title" data-confirm-title>Are you sure?</h3>
            <p data-confirm-text></p>
            <label class="field confirm-note" data-confirm-note-wrap hidden>
                <span class="field-label" data-confirm-note-label>Add a note (optional)</span>
                <textarea rows="3" maxlength="255" data-confirm-note></textarea>
            </label>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary" data-modal-close>Go back</button>
            <button type="button" class="btn btn-danger" data-confirm-ok>Confirm</button>
        </div>
    </div>
</div>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<script src="<?= e(asset('js/lembobot.js')) ?>" defer></script>
<?php foreach ($page['scripts'] ?? [] as $js): ?>
<script src="<?= e(asset('js/' . $js)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
