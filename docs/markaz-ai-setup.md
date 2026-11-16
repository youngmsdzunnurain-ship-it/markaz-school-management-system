# MARKAZ AI foundation

## What this branch introduces

- A shared assistant entry point on the staff dashboard and authenticated management workspaces.
- Server-derived role scopes for Admin, Exam Officer, Teacher and Management.
- Account-private conversations, owner-checked history and conversation deletion.
- A Responses API adapter configured only through private server environment values.
- Review-only result-sheet image transcription. The scanner does not write to the results table.
- Admin-only activity feed for instrumented school actions and AI usage.
- Result-created/result-updated audit events that record actor, class, subject, period and student reference, but never score values.

## Database setup

Back up the database first, then apply this migration once:

```sh
mariadb -u root markaz_result_system < database/migrations/20261116_markaz_ai_foundation.sql
```

Use the password option required by the local MariaDB account. Do not re-run a migration blindly.

## Private AI configuration

Add these values to the server-side `.env` file. Never put the API key in HTML, JavaScript, Git, or a browser setting.

```dotenv
OPENAI_API_KEY=your_private_api_key
MARKAZ_AI_MODEL=your_enabled_model_id
MARKAZ_AI_TRANSCRIPTION_MODEL=your_enabled_transcription_model_id
MARKAZ_AI_BASE_URL=https://api.openai.com/v1
```

Voice input is transcribed through the provider's audio-transcription endpoint. Audio is held only in the request and is not written to MARKAZ storage; the transcript is placed in the compose box for the user to review before sending. Microphone access requires browser support and a secure context (HTTPS or localhost).

The Responses request sets `store: false`. This disables Responses application-state storage for the request; it does not mean the provider has zero abuse-monitoring retention. Review the provider's current data controls and your school's lawful basis/consent before sending identifiable student records to an external AI provider.

## Security boundaries

- The browser never chooses its role or permissions. The API reads the authenticated session and current MARKAZ assignments.
- Teacher scanning requires a server-verified assigned class and subject.
- Management capabilities are derived from individual Management permissions.
- Only Admin can read the school-wide activity feed.
- Conversations and messages are filtered by the signed-in account and role snapshot. Other teachers cannot open another account's conversation. Student conversations are additionally tied to the verified student, class, academic period and Student Portal session. Users can delete their own conversations. Conversation content remains in the database until deleted; the school should approve a retention schedule before production. Audit events are separate and retained for accountability.
- The scanner stores no uploaded image and does not save extracted marks. Staff must verify every value and use the existing result-entry API, which retains its score validation, teacher assignment and academic-period finalization checks.
- The activity feed is prospective: it contains events recorded after the migration and only from instrumented workflows. It is not a retroactive audit of every action in the school system.

## Current scope and follow-up

The staff assistant and a session-isolated Student Learning Assistant are included. Persistent voice-note attachments, cross-role messaging and push notifications remain separate follow-up work requiring dedicated privacy, retention, delivery and authorization designs. Automated result writes are intentionally excluded; scanned marks remain review-only. The scanner is deliberately review-only until a verified correction-and-approval workflow exists.
