<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
json_response(['ok' => false, 'message' => 'Not found.'], 404);
