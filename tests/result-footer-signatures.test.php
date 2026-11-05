<?php
declare(strict_types=1);

function signatureCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$settingsPage = file_get_contents(__DIR__ . '/../public/settings.html');
$settingsJs = file_get_contents(__DIR__ . '/../public/js/settings.js');
$resultJs = file_get_contents(__DIR__ . '/../public/js/student-result.js');
$resultApi = file_get_contents(__DIR__ . '/../public/api/settings/result-footer.php');
$resultCss = file_get_contents(__DIR__ . '/../public/css/student-result.css');
$printCss = file_get_contents(__DIR__ . '/../public/css/student-result-print.css');

signatureCheck(str_contains($settingsPage, 'signatureEditorDialog') && str_contains($settingsPage, 'signatureCanvas'), 'Settings must provide the in-app handwritten signature editor');
signatureCheck(str_contains($settingsPage, 'class_teacher') && str_contains($settingsPage, 'director'), 'Settings must expose the two shared signature roles');
signatureCheck(str_contains($settingsJs, 'pointerdown') && str_contains($settingsJs, 'pointermove'), 'Signature drawing must support touch, stylus and mouse pointer input');
signatureCheck(str_contains($settingsJs, 'toDataURL("image/png")') && str_contains($settingsJs, 'getImageData'), 'Signature must be cropped and saved as transparent PNG');
signatureCheck(str_contains($settingsJs, 'signatureSize') && str_contains($settingsPage, 'Use This Signature'), 'Admin must be able to adjust signature size before saving');
signatureCheck(str_contains($settingsPage, 'signatureLivePreview') && str_contains($settingsJs, 'updateSignaturePreview'), 'Signature size changes must update a visible preview before saving');
signatureCheck(str_contains($settingsJs, 'const scale = fitScale * (Number(sizeInput.value) / 100)'), 'Signature size percentage must scale the fitted signature without being neutralized by the height cap');
signatureCheck(str_contains($settingsJs, 'response.text()') && str_contains($settingsJs, 'Invalid JSON response'), 'Signature API errors must expose non-JSON server responses instead of masking them');
signatureCheck(strpos($resultApi, 'try {') < strpos($resultApi, 'requireSeniorAdmin();'), 'Signature API authentication/database exceptions must be handled by its JSON error boundary');

signatureCheck(str_contains($resultApi, 'requireLogin();') && str_contains($resultApi, 'requireSeniorAdmin();'), 'Result footer reads must require login while writes remain Senior Admin only');
signatureCheck(str_contains($resultApi, 'image/png') && str_contains($resultApi, 'uploads/signatures/'), 'API must validate and store PNG signature files outside the database');
signatureCheck(str_contains($resultJs, 'result-signature-image') && str_contains($resultJs, 'class_teacher_signature_image'), 'Student results must render the shared signature images');
signatureCheck(str_contains($resultCss, '.signature-space .result-signature-image'), 'Screen result styling must display transparent signature images');
signatureCheck(str_contains($printCss, '.signature-space .result-signature-image'), 'Print result styling must display transparent signature images');

echo "Handwritten result signature regression tests passed.\n";
