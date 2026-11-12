<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$navigation=(string)file_get_contents($root.'/public/js/settings-panel-navigation.js');
$html=(string)file_get_contents($root.'/public/settings.html');
$dashboard=(string)file_get_contents($root.'/public/js/dashboard.js');
$css=(string)file_get_contents($root.'/public/css/dashboard-2026.css');
$checks=[
 'role is fetched before Settings categories are created'=>strpos($navigation,'await fetch("./api/auth/me.php",') < strpos($navigation,'const allCategories = ['),
 'Admin dynamic Settings modules load before category discovery'=>strpos($navigation,'await new Promise(resolve => {') < strpos($navigation,'const allCategories = ['),
 'Management Settings are restricted to profile information'=>str_contains($navigation,'new Set(["profile"])'),
 'Exam Officer Settings have their own explicit category allow-list'=>str_contains($navigation,'new Set(["profile", "result-release", "academic-archive", "academic", "results", "memorization"])'),
 'Settings remain hidden until role resolution completes'=>str_contains($html,'settings-page-pending')&&str_contains($navigation,'classList.remove("settings-page-pending")'),
 'Management greeting includes explanatory dashboard copy'=>str_contains($dashboard,'Your assigned school workspaces are ready below.'),
 'Management profile card has dedicated visual styling'=>str_contains($css,'.management-dashboard-profile-body')&&str_contains($css,'.management-dashboard-profile-link'),
 'Admin greeting is moved below the dashboard data'=>str_contains($dashboard,'pageContent.insertBefore(welcomeCard, shortcutsSection)')
];
foreach($checks as $label=>$ok){echo ($ok?'PASS ':'FAIL ').$label.PHP_EOL;if(!$ok)exit(1);}
echo 'Settings and dashboard role regression checks passed ('.count($checks).').'.PHP_EOL;
