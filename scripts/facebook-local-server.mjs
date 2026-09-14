import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { DatabaseSync } from 'node:sqlite';
import { defaultDatabase, overview, openDatabase } from './facebook-local.mjs';
import { fold, registerSearch, ftsExpression } from './facebook-search.mjs';

class BadRequest extends Error {}
function dateBounds(search, column, conditions, params) {
    for (const [key, operator] of [['from', '>='], ['until', '<']]) {
        const value = search.get(key);
        if (!value) continue;
        if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/.test(value) || !Number.isFinite(Date.parse(value)) || new Date(value).toISOString() !== value) throw new BadRequest('Khoảng thời gian không hợp lệ.');
        conditions.push(`${column} ${operator} ?`); params.push(value);
    }
    if (search.get('from') && search.get('until') && search.get('from') >= search.get('until')) throw new BadRequest('Ngày bắt đầu phải trước hoặc bằng ngày kết thúc.');
}
function textFilter(search, table, rowid, fallback, conditions, params) {
    const query = (search.get('q') || '').trim().slice(0, 300), excluded = (search.get('exclude') || '').trim().slice(0, 300);
    for (const [text, negative] of [[query, false], [excluded, true]]) {
        if (!text) continue;
        const expression = ftsExpression(text, negative ? 'any' : search.get('mode'));
        if (expression) {
            conditions.push(`${rowid} ${negative ? 'NOT IN' : 'IN'} (SELECT rowid FROM ${table} WHERE ${table} MATCH ?)`); params.push(expression);
        } else { conditions.push(`instr(fold(${fallback}),?) ${negative ? '=' : '>'} 0`); params.push(fold(text)); }
    }
}
function context(db, fingerprint) {
    const anchor = db.prepare('SELECT rowid AS position,* FROM messages WHERE fingerprint=?').get(fingerprint);
    if (!anchor) return null;
    const earlier = db.prepare(`SELECT rowid AS position,* FROM messages WHERE thread_key=? AND
        (coalesce(sent_at,'') < coalesce(?, '') OR (coalesce(sent_at,'')=coalesce(?,'') AND rowid<?))
        ORDER BY coalesce(sent_at,'') DESC,rowid DESC LIMIT 21`).all(anchor.thread_key, anchor.sent_at, anchor.sent_at, anchor.position);
    const later = db.prepare(`SELECT rowid AS position,* FROM messages WHERE thread_key=? AND
        (coalesce(sent_at,'') > coalesce(?, '') OR (coalesce(sent_at,'')=coalesce(?,'') AND rowid>?))
        ORDER BY coalesce(sent_at,'') ASC,rowid ASC LIMIT 21`).all(anchor.thread_key, anchor.sent_at, anchor.sent_at, anchor.position);
    const before = earlier.slice(0, 20).reverse(), after = later.slice(0, 20);
    return { anchor: anchor.fingerprint, title: anchor.thread_title, data: [...before, anchor, ...after],
        previous: earlier.length > 20 ? before[0].fingerprint : null, next: later.length > 20 ? after.at(-1).fingerprint : null,
        sources: db.prepare('SELECT sf.path,ms.record_index FROM message_sources ms JOIN source_files sf ON sf.id=ms.file_id WHERE ms.fingerprint=?').all(fingerprint) };
}

export function createServer(filename = defaultDatabase) {
    if (!fs.existsSync(filename)) throw new Error('Chưa có kho dữ liệu. Hãy chạy data:sync trước.');
    const writable = openDatabase(filename); writable.close();
    const db = new DatabaseSync(filename, { readOnly: true }); registerSearch(db);
    db.exec('PRAGMA busy_timeout=5000');
    let cachedOverview, cachedOptions, cachedVersion;
    const server = http.createServer((req, res) => {
        res.setHeader('Cache-Control', 'no-store'); res.setHeader('X-Content-Type-Options', 'nosniff');
        res.setHeader('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; connect-src 'self'; img-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
        const host = `127.0.0.1:${server.address().port}`;
        if (req.headers.host !== host || (req.headers.origin && req.headers.origin !== `http://${host}`) || req.headers['sec-fetch-site'] === 'cross-site') { res.writeHead(403); res.end('Forbidden'); return; }
        if (req.method !== 'GET') { res.writeHead(405); res.end(); return; }
        const url = new URL(req.url, `http://${host}`), search = url.searchParams;
        const staticFiles = { '/': ['facebook-local.html', 'text/html'], '/app.js': ['facebook-local-ui.mjs', 'text/javascript'], '/app.css': ['facebook-local.css', 'text/css'], '/search.js': ['facebook-search-client.mjs', 'text/javascript'] };
        if (staticFiles[url.pathname]) {
            const [file, type] = staticFiles[url.pathname]; res.setHeader('Content-Type', type+'; charset=utf-8');
            res.end(fs.readFileSync(new URL(file, import.meta.url))); return;
        }
        res.setHeader('Content-Type', 'application/json; charset=utf-8');
        try {
            const version = db.prepare('PRAGMA data_version').get().data_version;
            if (version !== cachedVersion) { cachedOverview = null; cachedOptions = null; cachedVersion = version; }
            if (url.pathname === '/api/overview') { cachedOverview ??= overview(db); res.end(JSON.stringify(cachedOverview)); return; }
            if (url.pathname === '/api/options') {
                cachedOptions ??= {
                    threads: db.prepare('SELECT * FROM conversations ORDER BY title').all(),
                    kinds: db.prepare('SELECT category,kind,COUNT(*) AS total FROM activities GROUP BY category,kind ORDER BY category,kind').all(),
                    files: db.prepare('SELECT id,path,category FROM source_files ORDER BY path').all(),
                    buckets: db.prepare("SELECT DISTINCT substr(path,10,instr(substr(path,10),'/')-1) AS bucket FROM source_files WHERE path LIKE 'messages/%/%' ORDER BY bucket").all(),
                }; res.end(JSON.stringify(cachedOptions)); return;
            }
            if (url.pathname === '/api/senders') {
                const thread = search.get('thread') || '', q = fold(search.get('q') || '').slice(0, 100), where = ["sender<>''"], params = [];
                if (thread) { where.push('thread_key=?'); params.push(thread); }
                if (q) { where.push('instr(fold(sender),?)>0'); params.push(q); }
                res.end(JSON.stringify({ data: db.prepare(`SELECT sender,COUNT(*) AS total FROM messages WHERE ${where.join(' AND ')} GROUP BY sender ORDER BY total DESC,sender LIMIT 100`).all(...params) })); return;
            }
            if (url.pathname === '/api/context') {
                const data = context(db, search.get('id') || '');
                if (!data) { res.writeHead(404); res.end(JSON.stringify({ error: 'Không tìm thấy tin nhắn.' })); return; }
                res.end(JSON.stringify(data)); return;
            }
            const limit = 50; let page = Math.max(1, Math.min(10000000, Number.parseInt(search.get('page'), 10) || 1));
            const params = [], conditions = ['1=1']; let table, fields = '*', order;
            const category = search.get('category'), thread = search.get('thread'), quality = search.get('quality');
            const add = (sql, value) => { conditions.push(sql); if (value !== undefined) params.push(value); };
            switch (url.pathname) {
                case '/api/conversations':
                    table = 'conversations'; order = (search.get('sort')==='oldest'?'last_at ASC':search.get('sort')==='newest'?'last_at DESC':'message_count DESC')+',thread_key';
                    if (search.get('q')) add('instr(fold(title),?)>0', fold(search.get('q')).slice(0, 300));
                    dateBounds(search, 'last_at', conditions, params);
                    if (search.get('min_count')) {
                        const count = Number(search.get('min_count'));
                        if (!Number.isSafeInteger(count) || count < 0) throw new BadRequest('Số tin nhắn tối thiểu không hợp lệ.');
                        add('message_count>=?', count);
                    }
                    break;
                case '/api/messages': {
                    table = 'messages m'; fields = 'm.*'; order = search.get('sort') === 'oldest' ? 'm.sent_at ASC,m.rowid ASC' : 'm.sent_at DESC,m.rowid DESC';
                    textFilter(search, 'message_search', 'm.rowid', "coalesce(m.content,'') || ' ' || m.sender", conditions, params);
                    if (thread) add('m.thread_key=?', thread);
                    if (search.get('sender')) add('m.sender=?', search.get('sender'));
                    if (search.get('bucket')) add("EXISTS(SELECT 1 FROM message_sources ms JOIN source_files sf ON sf.id=ms.file_id WHERE ms.fingerprint=m.fingerprint AND instr(sf.path,?)=1)", 'messages/'+search.get('bucket')+'/');
                    dateBounds(search, 'm.sent_at', conditions, params);
                    const media = search.get('media'), keys = { photo: 'photos', video: 'videos', audio: 'audio_files', file: 'files', gif: 'gifs' };
                    if (media === 'any') add('m.has_media=1');
                    else if (Object.hasOwn(keys,media)) add(`coalesce(json_array_length(m.payload_json,'$.${keys[media]}'),0)>0`);
                    else if (media === 'link') add("(instr(lower(coalesce(m.content,'')),'http://')>0 OR instr(lower(coalesce(m.content,'')),'https://')>0 OR json_extract(m.payload_json,'$.share.link') IS NOT NULL)");
                    else if (media === 'no_text') add("coalesce(trim(m.content),'')=''");
                    if (search.get('reactions') === 'yes') add('m.reaction_count>0');
                    if (search.get('reactions') === 'no') add('m.reaction_count=0');
                    if (quality === 'missing_sender') add("m.sender=''");
                    if (quality === 'missing_date') add('m.sent_at IS NULL');
                    if (quality === 'duplicate') add('(SELECT COUNT(*) FROM message_sources ms WHERE ms.fingerprint=m.fingerprint)>1');
                    break;
                }
                case '/api/activities':
                    table = 'activities a'; fields = 'a.id,a.category,a.kind,a.occurred_at,a.title,a.payload_json,(SELECT path FROM source_files WHERE id=a.file_id) AS source_path';
                    order = search.get('sort') === 'oldest' ? 'a.occurred_at ASC,a.id ASC' : 'a.occurred_at DESC,a.id DESC';
                    textFilter(search, 'activity_search', 'a.id', 'a.payload_json', conditions, params);
                    if (category) add('a.category=?', category);
                    if (search.get('kind')) add('a.kind=?', search.get('kind'));
                    if (search.get('file')) add('a.file_id=?', search.get('file'));
                    if (quality === 'missing_date') add('a.occurred_at IS NULL');
                    dateBounds(search, 'a.occurred_at', conditions, params);
                    break;
                case '/api/files':
                    table = 'source_files'; fields = 'id,path,bytes,category,schema,record_count,synced_at'; order = 'path';
                    if (search.get('q')) add('instr(fold(path),?)>0', fold(search.get('q')).slice(0, 300));
                    if (category) add('category=?', category);
                    break;
                default: res.writeHead(404); res.end(JSON.stringify({ error: 'Not found' })); return;
            }
            const where = conditions.join(' AND '), total = db.prepare(`SELECT COUNT(*) AS total FROM ${table} WHERE ${where}`).get(...params).total;
            page = Math.min(page, Math.max(1, Math.ceil(total / limit)));
            const data = db.prepare(`SELECT ${fields} FROM ${table} WHERE ${where} ORDER BY ${order} LIMIT ? OFFSET ?`).all(...params, limit, (page - 1) * limit);
            res.end(JSON.stringify({ data, total, page, limit }));
        } catch (error) {
            res.writeHead(error instanceof BadRequest ? 400 : 500);
            res.end(JSON.stringify({ error: error instanceof BadRequest ? error.message : 'Không đọc được dữ liệu. Hãy thử lại hoặc kiểm tra kho SQLite.' }));
        }
    }); server.on('close', () => db.close()); return server;
}
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    const args = process.argv.slice(2), option = (key, fallback) => args.includes(key) ? args[args.indexOf(key) + 1] : fallback;
    try {
        const server = createServer(option('--db', defaultDatabase));
        server.on('error', error => { console.error(error.message); process.exitCode = 1; });
        server.listen(Number(option('--port', '4318')), '127.0.0.1', () => console.log(`Facebook data dashboard: http://127.0.0.1:${server.address().port}`));
    } catch (error) { console.error(error.message); process.exitCode = 1; }
}
