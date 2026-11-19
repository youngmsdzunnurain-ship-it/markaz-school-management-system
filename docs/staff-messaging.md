# MARKAZ staff messaging

## Features
- Private one-to-one conversations between active Admin, Exam Officer, Teacher and Management accounts.
- Named group conversations with up to 30 other staff members.
- Conversation membership is checked on every message request.
- Unread counts refresh automatically while the chat page is open.
- Students, inactive accounts and frozen accounts are excluded from the staff directory.

## Database setup
Back up the MARKAZ database, then apply `database/migrations/20261119_staff_messaging.sql` once. The migration creates the conversation, participant and message tables. Do not run it repeatedly unless the tables have first been deliberately removed.

## Use
Sign in to a staff account, open **Staff Chat** from the dashboard, choose one colleague for a private chat or multiple colleagues for a group, and send messages. A group requires a name.

## Cost and hosting
Messaging runs inside the existing MARKAZ PHP and MariaDB application. It uses no external messaging provider, AI model, paid subscription or third-party account. Message history is stored in the MARKAZ database.

## Security
Only authenticated staff roles can use the API. The server verifies account state and conversation membership. Message text is limited to 4,000 characters and displayed as text, not HTML. Student accounts cannot join staff conversations.

## Current scope
This first release supports text messages, private chats and group chats. Voice notes, file attachments, push notifications and message editing/deletion are not included in this release.
