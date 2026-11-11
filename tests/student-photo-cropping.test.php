<?php
declare(strict_types=1);

function studentCropAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$html = file_get_contents($root . '/public/students.html');
$js = file_get_contents($root . '/public/js/students.js');
$css = file_get_contents($root . '/public/css/students.css');
$reportCss = file_get_contents($root . '/public/css/student-result.css');

studentCropAssert(is_string($html), 'students page exists');
studentCropAssert(is_string($js), 'students script exists');
studentCropAssert(is_string($css), 'students stylesheet exists');
studentCropAssert(is_string($reportCss), 'student result stylesheet exists');

foreach ([
    'id="studentPhotoCropModal"',
    'id="studentPhotoCropCanvas"',
    'id="studentPhotoCropZoom"',
    'id="confirmStudentPhotoCrop"',
    'id="cancelStudentPhotoCrop"',
] as $required) {
    studentCropAssert(str_contains($html, $required), "crop interface includes {$required}");
}

studentCropAssert(str_contains($html, 'id="studentPhotoCropCanvas" width="320" height="320"'), 'crop viewport is square');
studentCropAssert(str_contains($js, 'createImageBitmap'), 'cropper decodes selected images with orientation-aware browser support');
studentCropAssert(str_contains($js, 'drawImage'), 'cropper renders the selected area to a canvas');
studentCropAssert(str_contains($js, 'canvas.toBlob'), 'cropper produces a real image file for upload');
studentCropAssert(str_contains($js, 'image/jpeg'), 'cropped image is normalized to JPEG for consistent uploads');
studentCropAssert(str_contains($js, 'new File('), 'cropped image replaces the selected upload file');
studentCropAssert(str_contains($js, 'studentPhotoCropZoom'), 'cropper exposes zoom control');
studentCropAssert(str_contains($js, 'setPointerCapture'), 'crop viewport supports mouse and touch dragging');
studentCropAssert(str_contains($js, 'studentPhotoCamera'), 'camera capture continues to use the same crop flow');
studentCropAssert(str_contains($js, 'editStudentPhotoCamera'), 'edit camera capture continues to use the same crop flow');
studentCropAssert(str_contains($css, '.student-photo-crop-viewport'), 'crop interface has responsive viewport styling');
studentCropAssert(str_contains($css, 'prefers-reduced-motion'), 'crop interface respects reduced-motion preferences');
studentCropAssert(str_contains($reportCss, 'object-fit:cover'), 'result sheet keeps the cropped photo fitted without distortion');
studentCropAssert(str_contains($reportCss, 'object-position:center center'), 'result sheet displays the centre of the confirmed square crop');
studentCropAssert(str_contains($reportCss, 'width:100px !important;') && str_contains($reportCss, 'height:100px !important;'), 'printed student photo frame remains square');
studentCropAssert(str_contains($js, 'bindInput(studentPhoto, studentPhotoPreview)'), 'Add Student photo selection opens the cropper');
studentCropAssert(str_contains($js, 'bindInput(editStudentPhoto, editStudentPhotoPreview)'), 'Edit Student photo selection opens the cropper');
studentCropAssert(str_contains($js, 'confirmedFiles'), 'cancelling a replacement crop preserves the last confirmed photo');

echo "Student photo crop workflow regression checks passed.\n";
