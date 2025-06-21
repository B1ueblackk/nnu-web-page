<?php
header('Content-Type: application/json');

echo json_encode([
    'status' => 'success',
    'message' => 'PHP is working',
    'time' => date('Y-m-d H:i:s')
]);
?> 