export function fold(value) {
    return String(value ?? '').normalize('NFD').replace(/\p{M}/gu, '').replace(/[đĐ]/g, 'd').toLowerCase();
}

export function registerSearch(db) { db.function('fold', { deterministic: true }, fold); }

// FTS rowids match source rowids; triggers maintain them on subsequent syncs.
export function ensureSearch(db) {
    registerSearch(db);
    if (db.prepare("SELECT value FROM settings WHERE key='search_version'").get()?.value === '1') return;
    db.exec('BEGIN IMMEDIATE');
    try {
        db.exec(`
            CREATE VIRTUAL TABLE IF NOT EXISTS message_search USING fts5(body, tokenize='unicode61 remove_diacritics 2');
            CREATE VIRTUAL TABLE IF NOT EXISTS activity_search USING fts5(body, tokenize='unicode61 remove_diacritics 2');
            DELETE FROM message_search; DELETE FROM activity_search;
            INSERT INTO message_search(rowid,body) SELECT rowid,fold(coalesce(content,'') || ' ' || sender) FROM messages;
            INSERT INTO activity_search(rowid,body) SELECT id,fold(payload_json) FROM activities;
            CREATE TRIGGER IF NOT EXISTS message_search_insert AFTER INSERT ON messages BEGIN
                INSERT INTO message_search(rowid,body) VALUES(new.rowid,fold(coalesce(new.content,'') || ' ' || new.sender)); END;
            CREATE TRIGGER IF NOT EXISTS message_search_delete AFTER DELETE ON messages BEGIN
                DELETE FROM message_search WHERE rowid=old.rowid; END;
            CREATE TRIGGER IF NOT EXISTS message_search_update AFTER UPDATE OF content,sender ON messages BEGIN
                DELETE FROM message_search WHERE rowid=old.rowid;
                INSERT INTO message_search(rowid,body) VALUES(new.rowid,fold(coalesce(new.content,'') || ' ' || new.sender)); END;
            CREATE TRIGGER IF NOT EXISTS activity_search_insert AFTER INSERT ON activities BEGIN
                INSERT INTO activity_search(rowid,body) VALUES(new.id,fold(new.payload_json)); END;
            CREATE TRIGGER IF NOT EXISTS activity_search_delete AFTER DELETE ON activities BEGIN
                DELETE FROM activity_search WHERE rowid=old.id; END;
            CREATE TRIGGER IF NOT EXISTS activity_search_update AFTER UPDATE OF payload_json ON activities BEGIN
                DELETE FROM activity_search WHERE rowid=old.id;
                INSERT INTO activity_search(rowid,body) VALUES(new.id,fold(new.payload_json)); END;
            CREATE INDEX IF NOT EXISTS messages_sender_date ON messages(sender,sent_at);
            CREATE INDEX IF NOT EXISTS activities_category_kind_date ON activities(category,kind,occurred_at);
            INSERT OR REPLACE INTO settings VALUES('search_version','1');
        `);
        db.exec('COMMIT; PRAGMA optimize');
    } catch (error) { db.exec('ROLLBACK'); throw error; }
}

export function searchTerms(query) { return (fold(query).match(/[\p{L}\p{N}]+/gu) ?? []).slice(0, 20); }

export function ftsExpression(query, mode = 'all') {
    const terms = searchTerms(query);
    if (!terms.length) return null;
    if (mode === 'phrase') return '"'+terms.join(' ')+'"';
    return terms.map(term => '"'+term+'"*').join(mode === 'any' ? ' OR ' : ' AND ');
}
