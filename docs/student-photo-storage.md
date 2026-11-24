# MARKAZ student photo storage and caching

## Storage layout

New student photographs are saved below the public document root in:

`images/{active-academic-session}/{random-file-name}.{jpg|png|webp}`

The upload helper reads the active session from `academic_sessions` at upload time, sanitizes the session name into a safe directory name, creates the directory when needed, validates the actual image content, and uses a cryptographically random filename. The relative path (for example, `images/2026-2027/...`) is saved in `students.profile_image`.

Both student creation and photo replacement use the same helper. Existing photo paths remain valid and are not moved or rewritten automatically because their original upload session cannot be reliably inferred. Replacing a photo writes the new file into the session that is current at the time of replacement.

## Photo retrieval and rendering

The Students workspace requests photo paths in one authenticated batch from `api/students/photos.php`. The response is marked `no-store`; the client maps returned paths to the matching student rows and sets image sources in a loop. Images use native lazy loading and asynchronous decoding. Existing student-list and result APIs continue to provide the profile path for their existing consumers.

## Caching

`public/images/.htaccess` applies a one-year immutable browser cache to the random-named uploaded images, using `private` so shared proxies do not cache student photographs. Random filenames ensure a changed photo gets a new URL rather than being hidden behind a stale cached response.

The service worker continues to cache approved public-site assets and images only. Its asset cache is bounded to 160 entries and its version is bumped so previous cache entries are cleaned up. It deliberately does not persist student photographs: those are personal student records and should not be retained in an offline cache on shared school devices. The protected photo-path API is never cached.

## Deployment notes

- Apache must allow the `.htaccess` directives in `public/images`; `mod_expires` and `mod_headers` provide the cache headers.
- PHP needs write permission to create `public/images/{session}` and save files there.
- No schema migration is required: `students.profile_image` already stores the relative path.
- Existing `uploads/students/...` paths remain readable. New uploads use the academic-session structure.
