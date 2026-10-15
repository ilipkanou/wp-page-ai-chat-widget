<?php
// TEST HARNESS ONLY - never deploy. Fake Gemini, no mail, test page 741.
define('GEMINI_API_KEY', 'test-key-not-real');

add_filter('pre_http_request', function ($pre, $args, $url) {
    if (strpos($url, 'generativelanguage.googleapis.com') === false) return $pre;
    $req = json_decode($args['body'], true);
    $system = $req['systemInstruction']['parts'][0]['text'] ?? '';
    $contents = $req['contents'] ?? [];
    $last = end($contents);
    $last_text = $last['parts'][0]['text'] ?? '';
    if (stripos($last_text, 'WHICHMODEL') !== false) {
        preg_match('#/models/([^:]+):#', $url, $m);
        $text = 'MODEL=' . ($m[1] ?? 'unknown');
    } elseif (strpos($system, 'extracting first names') !== false) {
        $text = json_encode(['name' => 'Bob', 'greeting' => 'FAKE-GREETING Bob']);
    } elseif (strpos($system, 'You are Maggie') !== false) {
        $text = stripos($last_text, 'bye') !== false ? 'FAKE-GOODBYE [END_OF_CONVERSATION]' : 'FAKE-REPLY';
    } else {
        $text = 'FAKE-SUMMARY';
    }
    return [
        'headers' => [], 'cookies' => [], 'filename' => null,
        'response' => ['code' => 200, 'message' => 'OK'],
        'body' => json_encode(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]]),
    ];
}, 10, 3);

add_filter('pre_wp_mail', '__return_true');

add_action('init', function () {
    if (!get_post(741)) {
        wp_insert_post([
            'import_id' => 741, 'post_type' => 'page', 'post_status' => 'publish',
            'post_title' => 'Test sales page',
            'post_content' => str_repeat('This is the test sales page. It sells a book about small business profit. ', 5),
        ]);
    }
});
