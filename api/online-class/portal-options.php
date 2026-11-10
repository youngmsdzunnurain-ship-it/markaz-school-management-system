<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/config/app.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
$enabled=db()->query("SELECT setting_value FROM settings WHERE setting_key='online_class_student_access' LIMIT 1")->fetchColumn();
if(!in_array(strtolower(trim((string)$enabled)),['1','true','yes','on'],true)){http_response_code(403);echo json_encode(['success'=>false,'message'=>'Online Class student portal is currently closed.']);exit;}
$levels=db()->query('SELECT id,name FROM online_class_levels WHERE is_active=1 ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
$sections=db()->query('SELECT DISTINCT level_id,section FROM online_class_students WHERE is_active=1 AND TRIM(section)<>'' AND TRIM(section) NOT REGEXP '^[0-9]{4}/[0-9]{4}')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['success'=>true,'levels'=>$levels,'sections'=>$sections,'terms'=>['First Term','Second Term','Third Term']],JSON_UNESCAPED_UNICODE);
 ORDER BY level_id,section')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['success'=>true,'levels'=>$levels,'sections'=>$sections,'terms'=>['First Term','Second Term','Third Term']],JSON_UNESCAPED_UNICODE);
