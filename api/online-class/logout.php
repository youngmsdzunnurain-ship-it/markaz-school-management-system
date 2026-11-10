<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/middleware/session.php';
startSecureSession();
unset($_SESSION['online_student_id']);
session_regenerate_id(true);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success'=>true]);
