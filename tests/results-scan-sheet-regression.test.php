<?php
declare(strict_types=1);

function scanSheetCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$sheetHtml = file_get_contents($root . '/public/results-scan-sheet.html');
$sheetJs = file_get_contents($root . '/public/js/results-scan-sheet.js');
$scanner = file_get_contents($root . '/public/js/results-scanner.js');
$classHtml = file_get_contents($root . '/public/class.html');
$classJs = file_get_contents($root . '/public/js/class.js');
$batchSave = file_get_contents($root . '/api/results/batch-save.php');

scanSheetCheck(
    str_contains($classHtml, 'id="printResultsScanSheet"') &&
    str_contains($classJs, 'results-scan-sheet.html?class_id=') &&
    str_contains($classJs, 'window.open(url, "_blank", "noopener")'),
    'Class workspace exposes a dedicated printable scan-sheet route'
);
scanSheetCheck(
    str_contains($sheetHtml, '@page{size:A4 landscape;margin:0}') &&
    str_contains($sheetHtml, 'width:297mm;height:210mm') &&
    str_contains($sheetJs, 'i+=15') &&
    str_contains($sheetJs, 'subjects.length>12'),
    'Printable template is A4 landscape, supports up to 12 selected subjects and paginates rosters in groups of 15'
);
scanSheetCheck(
    str_contains($sheetHtml, '.scan-grid tbody tr{height:10mm}') &&
    str_contains($sheetJs, "<col style='width:20mm'><col style='width:48mm'>") &&
    str_contains($sheetJs, 'const scoreWidth=(289-68)/scoreCols;'),
    'Student names and score boxes have increased width and row height for handwriting clarity'
);
scanSheetCheck(
    str_contains($sheetJs, 'MZSCAN1') &&
    str_contains($sheetJs, 'subjects.map(s=>s.id).join(",")') &&
    str_contains($sheetJs, 'studentPage.map(s=>s.id).join(",")') &&
    str_contains($sheetJs, 'data.academic_period.id'),
    'Each printed page carries its exact class, class type, selected subject order and student order in its QR payload'
);
scanSheetCheck(
    substr_count($sheetHtml, '.scan-fiducial.') >= 4 &&
    str_contains($sheetJs, '["tl","tr","bl","br"]'),
    'All four page-corner fiducials are printed for full-page perspective correction'
);
scanSheetCheck(
    str_contains($scanner, 'loadJsQR()') &&
    str_contains($scanner, 'parts[0] !== "MZSCAN1"') &&
    str_contains($scanner, 'printedClassId') &&
    str_contains($scanner, 'printedClassType') &&
    str_contains($scanner, 'printedPeriodId') &&
    str_contains($scanner, 'state.meta.academicPeriodId'),
    'Scanner identifies and validates the printed sheet before assigning marks'
);
scanSheetCheck(
    str_contains($scanner, 'findSheetFiducials(canvas, code.location)') &&
    str_contains($scanner, 'function findSheetFiducials(canvas, location)') &&
    str_contains($scanner, 'const ux =') && str_contains($scanner, 'const uy =') &&
    str_contains($scanner, 'function sheetHomography(points)') &&
    str_contains($scanner, '[4, 4, points.topLeft]') &&
    str_contains($scanner, '[293, 206, points.bottomRight]'),
    'Perspective correction uses four fiducials spanning the full A4 page, not a small QR-only reference'
);
scanSheetCheck(
    str_contains($scanner, 'function makeScoreStrip(') &&
    str_contains($scanner, 'for (let groupStart = 0; groupStart < scoreCellCount; groupStart += 12)') &&
    str_contains($scanner, 'numericTokensForCell(result, localCell * strip.cellWidth'),
    'Score extraction OCRs small groups of individual numeric cells and maps each token back to its own cell'
);
scanSheetCheck(
    str_contains($scanner, 'const cellWidth = (289 - 68) / scoreCellCount') &&
    str_contains($scanner, 'const rowY = 42 + rowIndex * 10;') &&
    str_contains($scanner, 'const scoreStartX = 72;') &&
    str_contains($scanner, 'subjectIds.length > 12') &&
    str_contains($scanner, 'studentIds.length > 15'),
    'Cell geometry adapts to any selected subject subset from one through twelve subjects and up to twenty students per page'
);
scanSheetCheck(
    str_contains($scanner, 'state.reviewStudents = students') &&
    str_contains($scanner, 'if (await processTemplateSheet(canvas)) return;') &&
    str_contains($scanner, '(state.reviewStudents || classStudents()).forEach') &&
    str_contains($scanner, 'Save reviewed results'),
    'Review is restricted to the students printed on that page and still requires explicit confirmation'
);
scanSheetCheck(
    str_contains($scanner, './api/results/batch-save.php') &&
    str_contains($batchSave, '$pdo->beginTransaction()') &&
    str_contains($batchSave, '$pdo->rollBack()'),
    'Template scan continues to use the existing atomic, transaction-protected batch save'
);

echo "MARKAZ A4 scan-sheet regression checks passed.\n";
