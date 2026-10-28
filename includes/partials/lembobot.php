<?php
/** LemboBot chat widget (rendered on every page). */
$botUser = current_user();
$botRole = $botUser['role'] ?? 'guest';
$botSuggestions = [
    'guest'    => ['I need a plumber in Prishtina', 'How does booking work?', 'What services are available?', 'I need an electrician urgently'],
    'customer' => ['What should I book for a leaking sink?', 'How do I cancel a booking?', 'Find a cleaner in Prishtina', 'How do reviews work?'],
    'provider' => ['How do I accept a booking?', 'Tips for a great profile', 'How does availability work?', 'How are platform fees calculated?'],
    'admin'    => ['How does provider approval work?', 'What are the booking statuses?', 'How are reports handled?'],
][$botRole] ?? [];
?>
<div class="lb-root" data-lembobot data-endpoint="<?= e(url('api/chatbot.php')) ?>" data-user="<?= e($botUser ? (string) $botUser['id'] : 'guest') ?>">
    <button type="button" class="lb-launcher" aria-label="Open LemboBot assistant" aria-expanded="false" data-lb-toggle>
        <span class="lb-launcher-icon"><?= icon('bot', 26) ?></span>
        <span class="lb-launcher-close"><?= icon('x', 24) ?></span>
        <span class="lb-launcher-hint">Need help? Ask LemboBot</span>
    </button>

    <section class="lb-window" role="dialog" aria-label="LemboBot assistant" hidden>
        <header class="lb-header">
            <div class="lb-avatar"><?= icon('bot', 22) ?></div>
            <div class="lb-title">
                <strong>LemboBot</strong>
                <small><span class="lb-dot" data-lb-dot></span> <span data-lb-status>Checking status…</span></small>
            </div>
            <button type="button" class="lb-icon-btn" title="Clear conversation" aria-label="Clear conversation" data-lb-clear><?= icon('refresh', 16) ?></button>
            <button type="button" class="lb-icon-btn" title="Close" aria-label="Close LemboBot" data-lb-toggle><?= icon('x', 18) ?></button>
        </header>

        <div class="lb-messages" data-lb-messages aria-live="polite">
            <div class="lb-msg lb-bot" data-lb-welcome>
                <div class="lb-bubble">
                    Hi<?= $botUser ? ' ' . e($botUser['first_name']) : '' ?>! 👋 I'm <strong>LemboBot</strong>, your LemboFix assistant.
                    Tell me what needs fixing and I'll suggest the right service and professionals near you.
                </div>
            </div>
        </div>

        <div class="lb-suggestions" data-lb-suggestions>
            <?php foreach ($botSuggestions as $s): ?>
                <button type="button" class="lb-chip" data-lb-suggest><?= e($s) ?></button>
            <?php endforeach; ?>
        </div>

        <form class="lb-input" data-lb-form autocomplete="off">
            <textarea rows="1" maxlength="<?= (int) config('chatbot.max_message_length') ?>" placeholder="Describe what you need…" aria-label="Message LemboBot" data-lb-text></textarea>
            <button type="submit" class="lb-send" aria-label="Send message" data-lb-send><?= icon('send', 18) ?></button>
        </form>
        <div class="lb-footnote">LemboBot can make mistakes. Never share passwords or payment details.</div>
    </section>
</div>
