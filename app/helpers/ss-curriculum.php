<?php
declare(strict_types=1);

function normalizeSeniorSubjectKey(string $name): string
{
    $key = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', trim($name)));
    $aliases = [
        'quran' => ['quran', 'qur an', 'quranic studies'],
        'english' => ['english', 'english language'],
        'mathematics' => ['mathematics', 'math', 'maths'],
        'agric' => ['agric', 'agriculture', 'agricultural science'],
        'computer' => ['computer', 'computer studies', 'ict'],
        'foodandnutrition' => ['foodandnutrition', 'foodnutrition'],
    ];
    foreach ($aliases as $canonical => $variants) {
        foreach ($variants as $variant) {
            $variantKey = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $variant));
            if ($key === $variantKey) return $canonical;
        }
    }
    return $key;
}

/**
 * Reconcile all conventional SS1–SS3 classes to the approved curriculum.
 * Existing subject IDs are reused by normalized name; existing score rows
 * are never deleted. Arabic and non-SS conventional classes are untouched.
 */
function ensureSeniorSecondaryCurriculum(PDO $pdo, int $classId): void
{
    $classStmt = $pdo->query('SELECT id, name FROM classes');
    $seniorClasses = [];
    $requestedClassIsSenior = false;

    foreach ($classStmt->fetchAll(PDO::FETCH_ASSOC) as $class) {
        $normalized = strtoupper((string) preg_replace('/[\s_-]+/', '', trim((string) $class['name'])));
        if (!in_array($normalized, ['SS1', 'SS2', 'SS3', 'SSS1', 'SSS2', 'SSS3'], true)) continue;
        $seniorClasses[] = (int) $class['id'];
        if ((int) $class['id'] === $classId) $requestedClassIsSenior = true;
    }

    if (!$requestedClassIsSenior || !$seniorClasses) return;

    $curriculum = [
        'Quran', 'English', 'Mathematics', 'Biology', 'Chemistry',
        'Physics', 'Civic Education', 'Agric', 'Computer',
        'Food and Nutrition', 'Economics',
    ];

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();

    try {
        // Earliest matching subject ID is the stable canonical record.
        $existingSubjects = $pdo->query('SELECT id, name FROM subjects ORDER BY id ASC')
            ->fetchAll(PDO::FETCH_ASSOC);
        $existingSubjectIdsByKey = [];
        foreach ($existingSubjects as $existingSubject) {
            $key = normalizeSeniorSubjectKey((string) $existingSubject['name']);
            if ($key !== '' && !isset($existingSubjectIdsByKey[$key])) {
                $existingSubjectIdsByKey[$key] = (int) $existingSubject['id'];
            }
        }

        $insertSubject = $pdo->prepare(
            "INSERT INTO subjects (name, category, max_ca1, max_ca2, max_exam, is_active)
             VALUES (?, 'Senior Secondary', 20.00, 20.00, 60.00, 1)"
        );
        $activateSubject = $pdo->prepare(
            "UPDATE subjects SET category = 'Senior Secondary', is_active = 1 WHERE id = ?"
        );

        $subjectIds = [];
        foreach ($curriculum as $name) {
            $key = normalizeSeniorSubjectKey($name);
            $subjectId = $existingSubjectIdsByKey[$key] ?? null;
            if ($subjectId === null) {
                $insertSubject->execute([$name]);
                $subjectId = (int) $pdo->lastInsertId();
                $existingSubjectIdsByKey[$key] = $subjectId;
            } else {
                $activateSubject->execute([$subjectId]);
            }
            $subjectIds[] = (int) $subjectId;
        }

        $currentStmt = $pdo->prepare(
            'SELECT subject_id, sort_order FROM class_subjects
             WHERE class_id = ? ORDER BY sort_order ASC, subject_id ASC'
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

            if ($isAlreadyCanonical) continue;
            $deleteMappings->execute([$seniorClassId]);
            foreach ($subjectIds as $index => $subjectId) {
                $insertMapping->execute([$seniorClassId, $subjectId, $index + 1]);
            }
        }

        // Animal Husbandry is not part of the SS curriculum. Its subject
        // record and any historic results remain intact.
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
