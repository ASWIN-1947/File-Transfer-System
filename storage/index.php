<?php
http_response_code(403);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'status' => 'error',
    'message' => '403 Forbidden: Direct directory access is prohibited.'
]);
exit;
