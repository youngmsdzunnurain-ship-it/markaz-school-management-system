# MARKAZ AI — hosted intelligence with a no-provider fallback

## How chat works

MARKAZ AI can use Cloudflare Workers AI as a hosted language-model provider. The PHP application calls the provider from the server; neither the user's phone nor a laptop is used as an inference host. The selected model is `@cf/google/gemma-4-26b-a4b-it`.

When the provider is not configured, unavailable, times out or returns an unusable response, MARKAZ falls back to its deterministic, verified-data answer engine. Conversation history is loaded from the database for the authenticated account and included in the hosted request, so short follow-ups can be understood in context. The no-provider fallback also resolves a teacher's name when it directly answers the assistant's request for the exact staff account name.

## Free-plan requirement

This integration is optional and does not require paid model credits. To use hosted answers, configure a Cloudflare account that remains on the Workers Free plan and Workers AI free allocation. Cloudflare documents a daily free allocation (currently 10,000 Neurons/day); availability and model eligibility can change. When the free allocation is exhausted, hosted requests may fail and MARKAZ will use its local fallback. Do not upgrade to Workers Paid, enable paid add-ons, or add prepaid credits for this setup. Free allocation is limited, not unlimited, and Cloudflare account usage must be monitored.

If no Cloudflare credentials are configured, no provider request is made. The app continues using its local answer engine.

## Server environment configuration

Set these values in the deployment server's private environment or untracked server-side `.env` file. Never commit real values to GitHub, place them in browser JavaScript, or paste them into chat:

- `MARKAZ_AI_CLOUDFLARE_ACCOUNT_ID` — Cloudflare account ID.
- `MARKAZ_AI_CLOUDFLARE_API_TOKEN` — a scoped Workers AI API token with the permissions required to run inference.

The API token is sent only from PHP to Cloudflare over HTTPS. It is not returned to the browser or written to application logs. Keep the token private and rotate it if exposed. Do not configure these variables until the Cloudflare account is confirmed to be on the Free plan.

## Conversation and data handling

Each request uses the authenticated staff role, permissions, server-verified workspace context and at most the ten most recent stored conversation turns. Only user and assistant turns are forwarded; stored system-role messages are discarded. Conversation retrieval is filtered by both conversation ID and authenticated account ID.

The model is instructed to answer from the supplied MARKAZ context, respect role boundaries, avoid fabricated school records and distinguish recorded activity from unlogged work. The model is not allowed to execute SQL or perform write actions. All existing same-origin checks, account ownership checks, staff role checks, hourly rate limits and audit events remain in place.

Only authenticated Admin, Exam Officer, Teacher and Management accounts can use the staff assistant. Student-facing AI remains disabled and student endpoints continue to reject direct requests. Teachers and Management remain limited to their server-confirmed assignments and permissions; only Admin receives school-wide oversight context.

## Supported verified information

The current server context includes the active academic period, permitted school/attendance summaries, assigned teacher workspaces and Admin's bounded recorded school activity. Admin can ask for a named teacher's recorded activity for today. Activity summaries are based on events actually recorded by MARKAZ and are not a complete measure of all work performed.

The language model improves natural conversation and follow-up handling; it does not create missing database facts. If a requested detail is not in the permitted context, it must say so rather than invent it.

## Result-sheet OCR

The staff scanner uses Tesseract.js in the browser to extract printed text from JPEG, PNG or WebP images. The image is processed by the browser OCR worker and is not posted to MARKAZ's PHP API or sent to the language model. OCR output is for manual review only: it does not match student identities, validate marks or save result records. Staff must verify every value against the original sheet.

## Deliberately disabled features

- Student-facing generative AI.
- AI voice transcription.
- Automatic result entry or record editing by the model.
- Arbitrary SQL execution or unrestricted database tools.

## Database setup

The AI foundation migration is:

`database/migrations/20261116_markaz_ai_foundation.sql`

It creates private conversation history and separate staff/student audit tables. Back up the database before applying migrations on a new deployment, and check whether each migration has already been applied before running it.
