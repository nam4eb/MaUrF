import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { gzipSync } from 'node:zlib';
import { DatabaseSync } from 'node:sqlite';
import { fileURLToPath } from 'node:url';
import { ensureSearch } from './facebook-search.mjs';

const project = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
export const defaultDatabase = path.join(project, 'storage/app/private/facebook-local/activity.sqlite');
const digest = value => crypto.createHash('sha256').update(value).digest('hex');
const parserVersion = '2';

// Meta exports sometimes encode UTF-8 bytes as Latin-1 code points.
// Repair only reversible, valid UTF-8 runs; already-correct Unicode is preserved.
export function repairText(value) {
    return value.replace(/[\u0080-\u00ff]+/g, run => {
        try { return new TextDecoder('utf-8', { fatal: true }).decode(Buffer.from(run, 'latin1')); }
        catch { return run; }
    });
}

function normalize(value) {
    if (typeof value === 'string') return repairText(value);
    if (Array.isArray(value)) return value.map(normalize);
    if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).map(([k, v]) => [repairText(k), normalize(v)]));
    return value;
}

function timestamp(row) {
    const raw = row.timestamp_ms ?? row.timestamp ?? row.creation_timestamp ?? row.start_timestamp;
    if (raw === undefined || raw === null || raw === '') return null;
    const number = Number(raw);
    if (!Number.isFinite(number) || number <= 0) return null;
    const date = new Date(number * (row.timestamp_ms !== undefined || number > 9999999999 ? 1 : 1000));
    return Number.isFinite(date.getTime()) ? date.toISOString() : null;
}

function walk(root) {
    const files = [];
    function visit(directory) {
        for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
            const full = path.join(directory, entry.name);
            if (entry.isSymbolicLink()) continue;
            if (entry.isDirectory()) visit(full);
            else if (entry.isFile() && /\.json$/i.test(entry.name)) files.push(full);
        }
    }
    visit(root);
    return files.sort();
}

export function openDatabase(filename = defaultDatabase) {
    fs.mkdirSync(path.dirname(filename), { recursive: true });
    const db = new DatabaseSync(filename);
    db.exec(`PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;
        CREATE TABLE IF NOT EXISTS settings(key TEXT PRIMARY KEY,value TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS sync_runs(id INTEGER PRIMARY KEY,started_at TEXT NOT NULL,completed_at TEXT,status TEXT NOT NULL,summary TEXT);
        CREATE TABLE IF NOT EXISTS source_files(id INTEGER PRIMARY KEY,path TEXT UNIQUE NOT NULL,sha256 TEXT NOT NULL,bytes INTEGER NOT NULL,category TEXT NOT NULL,schema TEXT NOT NULL,record_count INTEGER NOT NULL,raw_gzip BLOB NOT NULL,synced_at TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS messages(fingerprint TEXT PRIMARY KEY,thread_key TEXT NOT NULL,thread_title TEXT NOT NULL,thread_bucket TEXT NOT NULL,participants_json TEXT NOT NULL,sender TEXT NOT NULL,sent_at TEXT,content TEXT,has_media INTEGER NOT NULL,reaction_count INTEGER NOT NULL,payload_json TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS message_sources(file_id INTEGER REFERENCES source_files(id) ON DELETE CASCADE,record_index INTEGER NOT NULL,fingerprint TEXT REFERENCES messages(fingerprint),PRIMARY KEY(file_id,record_index));
        CREATE TABLE IF NOT EXISTS activities(id INTEGER PRIMARY KEY,file_id INTEGER NOT NULL REFERENCES source_files(id) ON DELETE CASCADE,record_index INTEGER NOT NULL,category TEXT NOT NULL,kind TEXT NOT NULL,occurred_at TEXT,title TEXT NOT NULL,search_text TEXT NOT NULL,payload_json TEXT NOT NULL,UNIQUE(file_id,record_index));
        CREATE INDEX IF NOT EXISTS messages_thread_date ON messages(thread_key,sent_at);
        CREATE INDEX IF NOT EXISTS messages_date ON messages(sent_at);
        CREATE INDEX IF NOT EXISTS message_sources_fingerprint ON message_sources(fingerprint);
        CREATE INDEX IF NOT EXISTS activities_date ON activities(occurred_at);
        CREATE INDEX IF NOT EXISTS activities_category ON activities(category);
        CREATE VIEW IF NOT EXISTS conversations AS SELECT thread_key,MAX(thread_title) AS title,COUNT(*) AS message_count,COUNT(DISTINCT NULLIF(sender,'')) AS sender_count,MIN(sent_at) AS first_at,MAX(sent_at) AS last_at FROM messages GROUP BY thread_key;
        CREATE VIEW IF NOT EXISTS monthly_messages AS SELECT substr(sent_at,1,7) AS month,COUNT(*) AS total FROM messages WHERE sent_at IS NOT NULL GROUP BY month;
        CREATE VIEW IF NOT EXISTS activity_categories AS SELECT category,COUNT(*) AS total,MIN(occurred_at) AS first_at,MAX(occurred_at) AS last_at FROM activities GROUP BY category;
    `);
    ensureSearch(db);
    return db;
}

function records(document) {
    if (Array.isArray(document)) return document.map(row => ({ kind: 'array', row }));
    if (!document || typeof document !== 'object') return [{ kind: 'scalar', row: document }];
    if ('label_values' in document || 'timestamp' in document || 'media' in document) return [{ kind: 'record', row: document }];
    const arrays = Object.entries(document).filter(([, value]) => Array.isArray(value));
    if (arrays.length) return arrays.flatMap(([kind, rows]) => rows.map(row => ({ kind, row })));
    return Object.keys(document).length ? [{ kind: 'object', row: document }] : [];
}

export function overview(db) {
    const counts = db.prepare(`SELECT (SELECT COUNT(*) FROM source_files) AS files,(SELECT COALESCE(SUM(bytes),0) FROM source_files) AS bytes,(SELECT COUNT(*) FROM messages) AS messages,(SELECT COUNT(DISTINCT thread_key) FROM messages) AS conversations,(SELECT COUNT(*) FROM activities) AS activities,(SELECT COUNT(*) FROM message_sources)-(SELECT COUNT(*) FROM messages) AS duplicate_messages,(SELECT COUNT(*) FROM messages WHERE sent_at IS NULL) AS messages_without_date,(SELECT COUNT(*) FROM activities WHERE occurred_at IS NULL) AS activities_without_date`).get();
    return { ...counts, messages_without_sender: db.prepare("SELECT COUNT(*) AS total FROM messages WHERE sender=''").get().total, range: db.prepare('SELECT MIN(sent_at) AS first_at,MAX(sent_at) AS last_at FROM messages').get(), categories: db.prepare('SELECT * FROM activity_categories ORDER BY total DESC').all(), monthly: db.prepare('SELECT * FROM monthly_messages ORDER BY month').all(), last_sync: db.prepare('SELECT id,started_at,completed_at,status,summary FROM sync_runs ORDER BY id DESC LIMIT 1').get() ?? null };
}

export function sync(source, filename = defaultDatabase, log = console.log) {
    const root = fs.realpathSync(source);
    if (!fs.statSync(root).isDirectory()) throw new Error('Source must be a directory.');
    const filenames = walk(root);
    if (!filenames.length) throw new Error('No JSON files found; the existing database was preserved.');
    const db = openDatabase(filename);
    const previousRoot = db.prepare("SELECT value FROM settings WHERE key='source_root'").get()?.value;
    if (previousRoot && previousRoot !== root) { db.close(); throw new Error('This database belongs to another source folder. Use --db with a separate database.'); }
    const run = db.prepare("INSERT INTO sync_runs(started_at,status) VALUES(?,'running')").run(new Date().toISOString()).lastInsertRowid;
    const stats = { scanned: filenames.length, changed: 0, unchanged: 0, removed: 0 };
    const putFile = db.prepare(`INSERT INTO source_files(path,sha256,bytes,category,schema,record_count,raw_gzip,synced_at) VALUES(?,?,?,?,?,?,?,?) ON CONFLICT(path) DO UPDATE SET sha256=excluded.sha256,bytes=excluded.bytes,category=excluded.category,schema=excluded.schema,record_count=excluded.record_count,raw_gzip=excluded.raw_gzip,synced_at=excluded.synced_at RETURNING id`);
    const putMessage = db.prepare('INSERT INTO messages VALUES(?,?,?,?,?,?,?,?,?,?,?) ON CONFLICT(fingerprint) DO UPDATE SET thread_title=excluded.thread_title,participants_json=excluded.participants_json,reaction_count=excluded.reaction_count,payload_json=excluded.payload_json');
    const putMessageSource = db.prepare('INSERT INTO message_sources VALUES(?,?,?)');
    const putActivity = db.prepare('INSERT INTO activities(file_id,record_index,category,kind,occurred_at,title,search_text,payload_json) VALUES(?,?,?,?,?,?,?,?)');
    const existing = new Map(db.prepare('SELECT id,path,sha256 FROM source_files').all().map(f => [f.path, f]));
    const sameParser = db.prepare("SELECT value FROM settings WHERE key='parser_version'").get()?.value === parserVersion;
    const observed = new Set();
    db.exec('BEGIN IMMEDIATE');
    try {
        db.prepare("INSERT OR REPLACE INTO settings VALUES('source_root',?)").run(root);
        for (const [index, file] of filenames.entries()) {
            const relative = path.relative(root, file).replaceAll('\\', '/');
            observed.add(relative);
            const bytes = fs.readFileSync(file);
            const hash = digest(bytes);
            if (sameParser && existing.get(relative)?.sha256 === hash) { stats.unchanged++; continue; }
            let doc;
            try { doc = normalize(JSON.parse(bytes.toString('utf8').replace(/^\uFEFF/, ''), (key, value, context) => typeof value === 'number' && Number.isInteger(value) && !Number.isSafeInteger(value) ? context.source : value)); }
            catch { throw new Error(`Invalid JSON: ${relative}. Sync rolled back; previous data preserved.`); }
            const isThread = Array.isArray(doc?.messages) && Array.isArray(doc?.participants);
            const rows = isThread ? doc.messages : records(doc);
            const category = relative.includes('/') ? relative.split('/')[0] : 'root';
            const schema = Array.isArray(doc) ? 'array' : doc && typeof doc === 'object' ? Object.keys(doc).join(',') : typeof doc;
            const fileId = putFile.get(relative, hash, bytes.length, category, schema, rows.length, gzipSync(bytes), new Date().toISOString()).id;
            db.prepare('DELETE FROM message_sources WHERE file_id=?').run(fileId);
            db.prepare('DELETE FROM activities WHERE file_id=?').run(fileId);
            if (isThread) {
                const threadPath = String(doc.thread_path || path.posix.dirname(relative)).replaceAll('\\', '/').replace(/\/$/, '');
                // A conversation may span message_1.json, message_2.json, or export buckets.
                const threadKey = threadPath.split('/').at(-1);
                const title = doc.title || threadKey;
                const participants = JSON.stringify(doc.participants);
                for (const [i, message] of rows.entries()) {
                    if (!message || typeof message !== 'object' || Array.isArray(message)) throw new Error(`Invalid Messenger record in ${relative} at index ${i}; sync rolled back.`);
                    const identity = JSON.stringify([threadKey, message.sender_name, message.timestamp_ms, message.type ?? 'generic', message.content ?? null, message.photos ?? null, message.videos ?? null, message.audio_files ?? null, message.files ?? null, message.share ?? null, message.sticker ?? null, message.call_duration ?? null, message.is_unsent ?? false]);
                    const fp = digest(identity);
                    const hasMedia = ['photos', 'videos', 'audio_files', 'files', 'gifs', 'sticker'].some(k => Boolean(message[k]?.length || message[k]?.uri));
                    putMessage.run(fp, threadKey, title, relative.split('/')[1] || 'root', participants, String(message.sender_name ?? ''), timestamp(message), message.content ?? null, Number(hasMedia), message.reactions?.length ?? 0, JSON.stringify(message));
                    putMessageSource.run(fileId, i, fp);
                }
            } else {
                for (const [i, { kind, row }] of rows.entries()) {
                    const object = row && typeof row === 'object' ? row : {};
                    const labels = object.label_values ?? [];
                    const title = object.title ?? object.name ?? labels.find(l => ['Tiêu đề', 'Title', 'Văn bản', 'Text', 'Tên', 'Name'].includes(l.label))?.value ?? '';
                    const payload = JSON.stringify(row);
                    putActivity.run(fileId, i, category, kind === 'array' || kind === 'record' ? path.posix.basename(relative, '.json') : kind, timestamp(object), String(title), payload.toLocaleLowerCase('vi'), payload);
                }
            }
            stats.changed++;
            if ((index + 1) % 25 === 0) log(`Processed ${index + 1}/${filenames.length} JSON files`);
        }
        for (const [relative, file] of existing) if (!observed.has(relative)) {
            db.prepare('DELETE FROM source_files WHERE id=?').run(file.id);
            stats.removed++;
        }
        db.exec('DELETE FROM messages WHERE NOT EXISTS(SELECT 1 FROM message_sources WHERE message_sources.fingerprint=messages.fingerprint)');
        db.exec("DROP VIEW conversations; CREATE VIEW conversations AS SELECT thread_key,MAX(thread_title) AS title,COUNT(*) AS message_count,COUNT(DISTINCT NULLIF(sender,'')) AS sender_count,MIN(sent_at) AS first_at,MAX(sent_at) AS last_at FROM messages GROUP BY thread_key");
        db.prepare("INSERT OR REPLACE INTO settings VALUES('parser_version',?)").run(parserVersion);
        db.prepare("UPDATE sync_runs SET status='completed',completed_at=?,summary=? WHERE id=?").run(new Date().toISOString(), JSON.stringify(stats), run);
        db.exec('COMMIT');
        const result = { sync: stats, ...overview(db) };
        db.close();
        return result;
    } catch (error) {
        db.exec('ROLLBACK');
        db.prepare("UPDATE sync_runs SET status='failed',completed_at=?,summary=? WHERE id=?").run(new Date().toISOString(), JSON.stringify({ error: error.message }), run);
        db.close();
        throw error;
    }
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    const args = process.argv.slice(2);
    const dbIndex = args.indexOf('--db');
    const database = dbIndex >= 0 ? path.resolve(args[dbIndex + 1]) : defaultDatabase;
    try {
        if (!args[0] || args[0] === '--help') console.log('node scripts/facebook-local.mjs <source-folder> [--db <sqlite-file>]');
        else {
            const result = sync(args[0], database);
            const report = path.join(path.dirname(database), 'sync-report.json');
            fs.writeFileSync(report, JSON.stringify(result, null, 2));
            console.log(JSON.stringify({ database, report, ...result }, null, 2));
        }
    } catch (error) { console.error(error.message); process.exitCode = 1; }
}
