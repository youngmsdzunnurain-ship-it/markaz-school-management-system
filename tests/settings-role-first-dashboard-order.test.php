<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$navigation=(string)file_get_contents($root.'/public/js/settings-panel-navigation.js');
$html=(string)file_get_contents($root.'/public/settings.html');
$dashboardHtml=(string)file_get_contents($root.'/public/dashboard.html');
$dashboard=(string)file_get_contents($root.'/public/js/dashboard.js');
$css=(string)file_get_contents($root.'/public/css/dashboard-2026.css');
$settingsNavCss=(string)file_get_contents($root.'/public/css/settings-panel-navigation.css');
$checks=[
 'role is fetched before Settings categories are created'=>strpos($navigation,'await fetch("./api/auth/me.php",') < strpos($navigation,'const allCategories = ['),
 'Admin dynamic Settings modules load concurrently'=>str_contains($navigation,'Promise.all(adminModules.map(src => new Promise(resolve => {')) && !str_contains($navigation,'for (const src of adminModules)'),
 'Management Settings are restricted to profile information'=>str_contains($navigation,'new Set(["profile"])'),
 'Exam Officer Settings have their own explicit category allow-list'=>str_contains($navigation,'new Set(["profile", "result-release", "academic-archive", "academic", "results", "memorization"])'),
 'Settings remain hidden until role resolution completes'=>str_contains($html,'settings-page-pending')&&str_contains($navigation,'classList.remove("settings-page-pending")'),
 'Settings profile is visible while remaining categories load'=>!str_contains($settingsNavCss,'html.settings-page-pending body .settings-page { visibility: hidden; }'),
 'Management greeting includes explanatory dashboard copy'=>str_contains($dashboard,'Your assigned school workspaces are ready below.'),
 'Management profile card has dedicated visual styling'=>str_contains($css,'.management-dashboard-profile-body')&&str_contains($css,'.management-dashboard-profile-link'),
 'Welcome banner appears before dashboard metrics'=>strpos($dashboardHtml,'class="welcome-card"') < strpos($dashboardHtml,'class="stats-grid"') && str_contains($dashboard,'Your assigned school workspaces are ready below.')
];
foreach($checks as $label=>$ok){echo ($ok?'PASS ':'FAIL ').$label.PHP_EOL;if(!$ok)exit(1);}
echo 'Settings and dashboard role regression checks passed ('.count($checks).').'.PHP_EOL;
