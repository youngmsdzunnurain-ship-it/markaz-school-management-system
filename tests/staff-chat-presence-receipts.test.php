<?php
declare(strict_types=1);
function chatReceiptCheck(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}}
$api=file_get_contents(__DIR__.'/../api/messages/index.php');
$js=file_get_contents(__DIR__.'/../public/js/staff-chat.js');
$css=file_get_contents(__DIR__.'/../public/css/staff-chat.css');
$migration=file_get_contents(__DIR__.'/../database/migrations/20261119_staff_chat_presence_receipts.sql');
chatReceiptCheck(str_contains($api,'a.profile_image'),'directory and conversation participants expose existing profile image data');
chatReceiptCheck(str_contains($api,'last_delivered_message_id'),'recipient activity records delivery acknowledgement');
chatReceiptCheck(str_contains($api,'last_read_message_id >= m.id'),'read receipts derive from recipient read position');
chatReceiptCheck(str_contains($api,'staff_presence') && str_contains($api,'INTERVAL 45 SECOND'),'online presence uses a bounded last-seen window');
chatReceiptCheck(str_contains($migration,'last_delivered_message_id') && str_contains($migration,'CREATE TABLE staff_presence'),'migration adds delivery and presence state');
chatReceiptCheck(str_contains($js,'avatar_url') && str_contains($js,'message-ticks'),'chat renders profile images and message ticks');
chatReceiptCheck(str_contains($js,'status==="read"') && str_contains($js,'status==="delivered"'),'tick colors distinguish read from delivered');
chatReceiptCheck(str_contains($css,'.message-ticks.read') && str_contains($css,'.online-dot'),'chat styles read ticks and online indicators');
echo "MARKAZ staff chat avatar/presence/receipt checks passed.\n";
