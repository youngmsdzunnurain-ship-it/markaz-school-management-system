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

## Result-sheet OCR (no-cost, browser-side)

The staff scanner uses Tesseract.js in the browser to extract printed text from JPEG, PNG or WebP images up to 5 MB. The image is passed directly to the browser OCR worker; it is not posted to MARKAZ's PHP API or sent to an AI model provider. The OCR library, WebAssembly core and English language data are downloaded from their configured CDN locations when first needed and may be cached by the browser.

The scanner returns raw extracted text for manual review only. It does not reliably understand table columns, match student identities, validate score fields or create result records. Staff must compare every extracted value with the original sheet and enter confirmed marks through the existing authorised result-entry workflow. OCR errors are possible, especially with handwriting, skewed images, poor lighting and decimal marks.

## Features deliberately disabled in no-cost mode

- AI voice transcription for staff and students.
- Generative language-model answers beyond the deterministic, verified school-data question set.

Voice transcription previously depended on a paid external model provider. It remains disabled rather than silently sending staff or student audio to an online speech service. Normal manual result entry and typed assistant questions remain available.

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
