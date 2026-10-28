<?php
/**
 * LemboBot endpoint.
 *   GET  ?action=status            → { mode: 'ai' | 'basic' }
 *   POST { message }               → { reply, providers, links, mode }
 *   POST { action: 'clear' }       → clears the server-side conversation
 *
 * Guests may chat (with stricter limits); every POST requires the CSRF token.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/chatbot.php';

$user = current_user();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    if (input('action') !== 'status') {
        api_respond(false, 'Unknown action.', [], 400);
    }
    json_response(['ok' => true, 'mode' => chatbot_ai_enabled() ? 'ai' : 'basic']);
}

require_method('POST');
require_csrf();
$payload = json_input() ?: $_POST;

if (($payload['action'] ?? '') === 'clear') {
    unset($_SESSION['lembobot_history']);
    json_response(['ok' => true]);
}

// ---- Validate input ----
$message = chatbot_clean_message(is_string($payload['message'] ?? null) ? $payload['message'] : '');
$maxLength = (int) config('chatbot.max_message_length');
if ($message === '') {
    json_response(['ok' => false, 'message' => 'Please type a message.'], 422);
}
if (mb_strlen($message) > $maxLength) {
    json_response(['ok' => false, 'message' => "Messages can be up to $maxLength characters."], 422);
}

// ---- Rate limiting (per user, or per IP for guests) ----
$identifier = $user ? 'u:' . $user['id'] : 'ip:' . client_ip();
$per10 = (int) config($user ? 'chatbot.user_per_10min' : 'chatbot.guest_per_10min');
$perDay = (int) config($user ? 'chatbot.user_per_day' : 'chatbot.guest_per_day');
$last = (int) ($_SESSION['lembobot_last'] ?? 0);
if (time() - $last < (int) config('chatbot.min_interval')) {
    json_response(['ok' => false, 'message' => 'You\'re typing fast! Please wait a second before sending another message.'], 429);
}
if (rate_limit_count('chatbot', $identifier, 600) >= $per10) {
    json_response(['ok' => false, 'message' => 'You\'ve reached the message limit for now. Please try again in a few minutes.'
        . ($user ? '' : ' Signing in gives you a higher limit.')], 429);
}
if (rate_limit_count('chatbot', $identifier, 86400) >= $perDay) {
    json_response(['ok' => false, 'message' => 'You\'ve reached today\'s LemboBot limit. Please come back tomorrow.'], 429);
}
rate_limit_hit('chatbot', $identifier);
$_SESSION['lembobot_last'] = time();

// ---- Build context from public data only ----
$intent = chatbot_detect_intent($message);
$found = chatbot_find_providers($intent);
$providers = $found['items'];
$links = chatbot_links($intent, $user);

$history = array_slice($_SESSION['lembobot_history'] ?? [], -((int) config('chatbot.history_limit')));
$mode = 'basic';
$notice = null;
$reply = null;

if (chatbot_ai_enabled()) {
    $messages = array_merge(
        [['role' => 'system', 'content' => chatbot_system_prompt($user, $intent, $providers, $found['city_matched'])]],
        $history,
        [['role' => 'user', 'content' => $message]]
    );
    $result = groq_chat($messages);
    if ($result['ok']) {
        $mode = 'ai';
        $reply = mb_substr(strip_tags($result['content']), 0, 2500);
    } else {
        error_log('[LemboBot] Groq error: ' . $result['error']);
        if (in_array($result['status'], [401, 403], true)) {
            $_SESSION['lembobot_ai_down_until'] = time() + 600;
        }
        $notice = $result['status'] === 429
            ? 'The AI is busy right now, so I answered in basic mode.'
            : 'The AI service is temporarily unavailable, so I answered in basic mode.';
    }
}
$reply ??= chatbot_fallback_reply($intent, $providers, $found['city_matched'], $user);

$history[] = ['role' => 'user', 'content' => $message];
$history[] = ['role' => 'assistant', 'content' => $reply];
$_SESSION['lembobot_history'] = array_slice($history, -((int) config('chatbot.history_limit')));

json_response([
    'ok'        => true,
    'reply'     => $reply,
    'providers' => $providers,
    'links'     => $links,
    'mode'      => $mode,
    'notice'    => $notice,
]);
