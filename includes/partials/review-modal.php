<div class="modal" id="review-modal" role="dialog" aria-modal="true" aria-labelledby="review-title" hidden>
    <div class="modal-backdrop" data-modal-close></div>
    <form class="modal-dialog" method="post" action="<?= e(url('api/reviews.php')) ?>" data-ajax data-on-success="reload" data-validate novalidate>
        <div class="modal-head">
            <h3 id="review-title">Rate <span data-fill-text="provider_name">your provider</span></h3>
            <button type="button" class="icon-btn" aria-label="Close" data-modal-close><?= icon('x', 20) ?></button>
        </div>
        <div class="modal-body">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="booking_id" value="">
            <fieldset class="field" style="border:0;padding:0">
                <legend class="field-label">Your rating</legend>
                <div class="star-input">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="star-<?= $i ?>" name="rating" value="<?= $i ?>" required<?= $i === 5 ? ' checked' : '' ?>>
                        <label for="star-<?= $i ?>" aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"><?= icon('star', 34) ?></label>
                    <?php endfor; ?>
                </div>
            </fieldset>
            <label class="field mb-0">
                <span class="field-label">Your review</span>
                <textarea class="textarea" name="comment" rows="4" minlength="10" maxlength="1000" required
                          placeholder="What went well? Was the pro on time, friendly and tidy?" data-count="#review-count"></textarea>
                <span class="field-hint" id="review-count"></span>
            </label>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
            <button type="submit" class="btn btn-primary"><?= icon('star', 16) ?> Publish review</button>
        </div>
    </form>
</div>
