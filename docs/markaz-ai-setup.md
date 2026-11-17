# MARKAZ AI — no-cost mode

## Current behaviour

MARKAZ AI currently runs in a no-cost, server-side deterministic mode. It does not require an API key, paid model credits, an external AI subscription, or a model installed on the school’s phone.

The assistant is available only to authenticated Admin, Exam Officer, Teacher and Management accounts. Both the conventional Student Portal and Online Class Student Portal are excluded. Student-facing AI endpoints return HTTP 403 even if called directly. Staff answers use live MARKAZ data filtered by the authenticated account’s role and permissions. Conversation ownership, rate limits, same-origin checks and audit records remain enforced.

This mode is intentionally transparent: it does not generate unrestricted answers like a large language model. If a question is outside its supported topics or the required data is unavailable, it says so rather than inventing an answer. It does not edit school records or claim to perform actions.

## Supported no-cost questions

- Current academic session and term, including finalization and result-release status.
- Admin school overview counts that are already available in the Admin scope.
- A teacher’s own server-verified assigned classes, subjects, active-student counts and current-period result-row counts.
- Attendance figures only where the authenticated role and stored permissions allow them.
- Current-period result-row counts for roles permitted to analyse results.
- Admin summaries of a named teacher’s recorded MARKAZ activity for today, with explicit caveats for unlogged actions and the 500-event safety cap.

The exact answer depends on the current account role and the live data available to that role.

## Features deliberately disabled in no-cost mode

- AI voice transcription for staff and students.
- AI result-sheet image scanning.

These features previously depended on a paid external model provider. Their endpoints now return an explicit no-cost-mode response and do not upload audio or images to an external provider. Normal manual result entry remains available through the existing authorised MARKAZ workflow.

## Database setup

The AI foundation migration is:

`database/migrations/20261116_markaz_ai_foundation.sql`

It provides private conversation history and separate staff/student audit tables. Back up the database before applying migrations on a new deployment, and check whether each migration has already been applied before running it.

## Security boundaries

- The browser never chooses a role or permission. The server derives these from the authenticated MARKAZ session and persisted permissions.
- Teachers receive only their own active assignments and permitted workspace figures.
- Management receives only the summaries allowed by its individual permissions.
- Only Admin receives school-wide oversight capabilities.
- Student-facing AI is disabled. Its legacy tables remain unused; student endpoints reject access with HTTP 403.
- Conversation history is isolated to the account or verified Student Portal session.
- Unsupported questions are not answered with invented records or scores.
- The assistant cannot write results, change attendance, send messages or perform administrative actions. Image scanning and voice transcription remain disabled until a genuinely no-cost, self-hostable processor can be verified.

## Future portability

The assistant is ordinary PHP and database logic. It can run wherever MARKAZ itself is hosted; the development phone is not the production server. The school can later choose online hosting or a school-owned server without tying the application to this phone.

A future self-hosted language model could be added behind a separate adapter if suitable hardware and operational resources are available. Self-hosting avoids per-request API billing but is not automatically cost-free: server hardware, hosting, electricity, maintenance and backups may still cost money. No such model is required or installed by this no-cost implementation.
