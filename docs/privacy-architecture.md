# Privacy architecture

Every normalized row belongs to the authenticated user. API queries derive that identity from the server-side session, and private archive/export downloads require matching ownership. Database foreign keys cascade on account or import deletion.

Privacy mode is enabled by default. The application stores timestamps, participants, media/reaction flags and a SHA-256 content fingerprint, but not message text. Logs contain identifiers and exception classes, never raw messages or uploaded JSON. Archives are private and deleted after successful import by default.

ZIP extraction rejects absolute paths, parent traversal, NUL bytes, excessive file counts and excessive declared expanded sizes. Production should use encrypted storage, TLS, PostgreSQL, Redis authentication and short backup retention.

Users can delete one import or their complete analytics dataset. Admin health views must expose counts and system status only—not message content.
