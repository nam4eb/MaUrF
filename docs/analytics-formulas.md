# Analytics formulas

- Conversation sessions split when the elapsed time reaches `FACEBOOK_ANALYTICS_CONVERSATION_GAP_HOURS` (8 by default). The first sender starts that session.
- Mutuality: `100 × (1 - |sent - received| / max(sent + received, 1))`.
- Recency: `100 × exp(-days_since_last / decay_days)`; decay defaults to 90 days.
- Frequency and active days: `100 × log(1 + value) / log(1 + maximum_user_value)`.
- Facebook engagement is the logarithmically normalized sum of configured reaction/comment/reply/mention/tag weights.
- Overall score is the weighted mean of available dimensions. An unavailable Facebook dataset is omitted and its weight redistributed; a present dataset with zero activity is a genuine zero.
- Trends compare days 0–29 with 30–59 and days 0–89 with 90–179. At least ±50% is rapid, ±15% is increasing/decreasing, values between are stable. Current zero is inactive; previous zero and current positive is rapidly increasing at 100%.

Scores describe interaction volume, balance and recency. They do not measure affection, friendship quality, or intent.

## Fingerprints

- Archive: SHA-256 of exact uploaded bytes, unique per user.
- Message: SHA-256 of logical thread path, exported timestamp, sender identity, type, content and attachment structure. It contains no database-generated ID.
- Interaction: SHA-256 of type, timestamp, exported actor identity, object identifier and structural data.
- Friendship: SHA-256 of source status, exported stable identity (or normalized name fallback), and timestamp.
