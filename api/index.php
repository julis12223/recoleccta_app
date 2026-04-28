<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Recoleccta API online',
    'apiBase' => '/api'
]);
