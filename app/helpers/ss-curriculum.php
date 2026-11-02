<?php
declare(strict_types=1);

/**
 * Keep all conventional SS1–SS3 classes on the approved eleven-subject
 * curriculum whenever an SS class is opened. Historical result rows remain
 * untouched. Arabic classes and non-SS conventional classes are not modified.
 */
function ensureSeniorSecondaryCurriculum(PDO $pdo, int $classId): void
{
    $classStmt = $pdo->query('SELECT id, name FROM classes');
    $seniorClasses = [];
    $requestedClassIsSenior = false;

    foreach ($classStmt->fetchAll(PDO::FETCH_ASSOC) as $class) {
        $normalized = strtoupper((string) preg_replace('/[\\s_-]+/', '', trim((string) $class['name'])));
        if (!in_array($normalized, ['SS1', 'SS2', 'SS3', 'SSS1', 'SSS2', 'SSS3'], true)) {
            continue;
        }

        $seniorClasses[] = (int) $class['id'];
        if ((int) $class['id'] === $classId) {
            $requestedClassIsSenior = true;
        }
    }

    if (!$requestedClassIsSenior || !$seniorClasses) {
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

        $currentStmt = $pdo->prepare(
            'SELECT subject_id, sort_order
             FROM class_subjects
             WHERE class_id = ?
             ORDER BY sort_order ASC, subject_id ASC'
        );
        $deleteMappings = $pdo->prepare('DELETE FROM class_subjects WHERE class_id = ?');
        $insertMapping = $pdo->prepare(
            'INSERT INTO class_subjects (class_id, subject_id, is_required, sort_order)
             VALUES (?, ?, 1, ?)'
        );

        foreach ($seniorClasses as $seniorClassId) {
            $currentStmt->execute([$seniorClassId]);
            $currentMappings = $currentStmt->fetchAll(PDO::FETCH_ASSOC);

            $isAlreadyCanonical = count($currentMappings) === count($subjectIds);
            if ($isAlreadyCanonical) {
                foreach ($subjectIds as $index => $subjectId) {
                    if ((int) $currentMappings[$index]['subject_id'] !== $subjectId
                        || (int) $currentMappings[$index]['sort_order'] !== $index + 1) {
                        $isAlreadyCanonical = false;
                        break;
                    }
                }
            }

            if ($isAlreadyCanonical) {
                continue;
            }

            $deleteMappings->execute([$seniorClassId]);
            foreach ($subjectIds as $index => $subjectId) {
                $insertMapping->execute([$seniorClassId, $subjectId, $index + 1]);
            }
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
