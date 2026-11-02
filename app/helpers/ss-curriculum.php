<?php
declare(strict_types=1);

/**
 * Keep the conventional SS1–SS3 curriculum canonical at the API boundary.
 * Existing result rows are intentionally retained; only class-subject mappings
 * are replaced. Arabic classes and every non-SS conventional class are untouched.
 */
function ensureSeniorSecondaryCurriculum(PDO $pdo, int $classId): void
{
    $classStmt = $pdo->prepare('SELECT name FROM classes WHERE id = ? LIMIT 1');
    $classStmt->execute([$classId]);
    $className = $classStmt->fetchColumn();

    if (!is_string($className)) {
        return;
    }

    $normalizedClass = strtoupper((string) preg_replace('/[\\s_-]+/', '', trim($className)));
    if (!in_array($normalizedClass, ['SS1', 'SS2', 'SS3', 'SSS1', 'SSS2', 'SSS3'], true)) {
        return;
    }

    $curriculum = [
        'Quran',
        'English',
        'Mathematics',
        'Biology',
        'Chemistry',
        'Physics',
        'Civic Education',
        'Agric',
        'Computer',
        'Food and Nutrition',
        'Economics',
    ];

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $findSubject = $pdo->prepare(
            'SELECT id FROM subjects WHERE LOWER(TRIM(name)) = LOWER(?) ORDER BY id ASC LIMIT 1'
        );
        $insertSubject = $pdo->prepare(
            'INSERT INTO subjects (name, category, max_ca1, max_ca2, max_exam, is_active)
             VALUES (?, \'Senior Secondary\', 20.00, 20.00, 60.00, 1)'
        );
        $activateSubject = $pdo->prepare(
            'UPDATE subjects SET category = \'Senior Secondary\', is_active = 1
             WHERE id = ?'
        );

        $subjectIds = [];
        foreach ($curriculum as $name) {
            $findSubject->execute([$name]);
            $subjectId = $findSubject->fetchColumn();

            if ($subjectId === false) {
                $insertSubject->execute([$name]);
                $subjectId = $pdo->lastInsertId();
            } else {
                $activateSubject->execute([(int) $subjectId]);
            }

            $subjectIds[] = (int) $subjectId;
        }

        $deleteMappings = $pdo->prepare('DELETE FROM class_subjects WHERE class_id = ?');
        $deleteMappings->execute([$classId]);

        $insertMapping = $pdo->prepare(
            'INSERT INTO class_subjects (class_id, subject_id, is_required, sort_order)
             VALUES (?, ?, 1, ?)'
        );

        foreach ($subjectIds as $index => $subjectId) {
            $insertMapping->execute([$classId, $subjectId, $index + 1]);
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
