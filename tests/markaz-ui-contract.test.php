<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$failures = [];
$check = static function(bool $ok,string $message) use (&$failures):void { if(!$ok)$failures[]=$message; };
$pages = [
'public/about.html','public/academics.html','public/admissions.html','public/attendance.html',
'public/class-subjects/index.html','public/class.html','public/classes.html','public/contact.html',
'public/dashboard.html','public/exam-officer-login.html','public/facilities.html','public/faq.html',
'public/gallery.html','public/home.html','public/login.html','public/management-login.html',
'public/print-results.html','public/school-fees.html','public/settings.html','public/student-login.html',
'public/student-result-print.html','public/student-result.html','public/students.html','public/subjects/index.html',
'public/teacher-login.html'
];
foreach($pages as $page){
 $html=@file_get_contents($root.'/'.$page);
 $check($html!==false,"Missing page: {$page}");
 if($html===false)continue;
 $check(str_contains($html,'markaz-theme.css'),"{$page}: shared theme stylesheet missing");
 $check(str_contains($html,'localStorage.getItem("markaz.theme")'),"{$page}: pre-paint theme bootstrap missing");
 $check(str_contains($html,'markaz-theme.js'),"{$page}: shared theme runtime missing");
 $check(!str_contains($html,'markaz-i18n.js'),"{$page}: Arabic language runtime must be removed");
}
$dashboard=file_get_contents($root.'/public/dashboard.html')?:'';
$check(str_contains($dashboard,'class="dashboard-page"'),'Dashboard scope class missing');
$check(str_contains($dashboard,'data-role-check-pending="true"'),'Dashboard role gate was changed');
$check(substr_count($dashboard,'id="dashboardProfileTrigger"')===1,'Dashboard must retain one profile/avatar trigger');
$check(!str_contains($dashboard,'data-markaz-language'),'Dashboard must be English-only');
$themeCss=file_get_contents($root.'/public/css/markaz-theme.css')?:'';
$themeJs=file_get_contents($root.'/public/js/markaz-theme.js')?:'';
$dashboardDark=file_get_contents($root.'/public/css/dashboard-dark-mode.css')?:'';
$check(str_contains($themeCss,'--canvas:#0a120d'),'Requested dark canvas token missing');
$check(str_contains($themeCss,'--surface:#101a14'),'Requested dark surface token missing');
$check(str_contains($themeCss,'--track:#243729'),'Requested dark chart track token missing');
$check(str_contains($themeCss,'@media print'),'Print-safe palette missing');
$check(str_contains($themeJs,'markaz.theme'),'Shared theme storage key missing');
$check(str_contains($themeJs,'prefers-color-scheme: dark'),'System theme default missing');
$check(str_contains($themeJs,'storage',),'Cross-tab theme synchronization missing');
$check(!preg_match('/#[0-9a-f]{3,8}\b/i',$dashboardDark),'Dashboard dark overrides must use theme variables only');
$check(str_contains($dashboardDark,'var(--track)'),'Dark chart tracks must use shared track token');
$check(str_contains($dashboardDark,'.stat-card::before'),'Stat card decorative blob removal rule missing');
$printPage=file_get_contents($root.'/public/student-result-print.html')?:'';
$check(preg_match('/<body[^>]*class="[^"]*print-page/i',$printPage)===1,'Result print page must be marked print-safe');
$preferences=file_get_contents($root.'/public/css/markaz-preferences.css')?:'';
$check(!str_contains($preferences,'html[dir=rtl]'),'Legacy RTL theme overrides remain active');
if($failures){fwrite(STDERR,"MARKAZ shared theme contract: FAILED\n- ".implode("\n- ",$failures)."\n");exit(1);}
echo "MARKAZ shared theme contract: PASS (".count($pages)." pages checked)\n";
