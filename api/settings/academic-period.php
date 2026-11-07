<?php
declare(strict_types=1);

// Keep the legacy /api route on the same protected academic-period workflow
// as the public Settings route so both entry points preserve archived terms.
require_once __DIR__ . '/../../public/api/settings/academic-period.php';
