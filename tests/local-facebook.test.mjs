import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { gunzipSync } from 'node:zlib';
import { repairText, sync, openDatabase } from '../scripts/facebook-local.mjs';
import { createServer } from '../scripts/facebook-local-server.mjs';

const silent = () => {};
function setup(t) {
    const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'atlas-test-'));
    t.after(() => fs.rmSync(temp, { recursive: true, force: true }));
    const source = path.join(temp, 'source');
    fs.mkdirSync(source);
    const database = path.join(temp, 'private', 'test.sqlite');
    const write = (name, document) => {
        const file = path.join(source, name);
        fs.mkdirSync(path.dirname(file), { recursive: true });
        fs.writeFileSync(file, typeof document === 'string' ? document : JSON.stringify(document));
        return file;
    };
    return { source, database, write };
}
const message = { sender_name: 'Chủ tài khoản', timestamp_ms: 1700000000000, content: 'Xin chào <script>alert(1)</script>' };
const thread = messages => ({ participants: [{ name: 'Chủ tài khoản' }, { name: 'Bạn bè' }], messages, title: 'Hội thoại thử', thread_path: 'inbox/test_123' });

test('repairs Facebook Vietnamese and emoji without changing correct Unicode', () => {
    for (const text of ['Cảm xúc', 'Tiêu đề', 'Nguyễn Văn An', 'Xin chào ❤️ 😀', 'é à ü', 'ASCII']) {
        assert.equal(repairText(Buffer.from(text, 'utf8').toString('latin1')), text);
        assert.equal(repairText(text), text);
    }
});

test('syncs every schema, preserves exact originals, groups pages and deduplicates reruns', t => {
    const { source, database, write } = setup(t);
    const original = JSON.stringify(thread([message]), null, 2);
    write('messages/inbox/test_123/message_1.json', original);
    write('messages/inbox/test_123/message_2.json', thread([message, { ...message, timestamp_ms: 1700001000000 }]));
    write('comments_and_reactions/likes_and_reactions.json', [{ timestamp: 1700000000, label_values: [{ label: 'Tiêu đề', value: 'Thích bài viết' }] }]);
    write('groups/comments.json', { group_comments_v2: [{ title: 'Bình luận' }] });
    write('other_activity/new.json', { unknown_schema: { arbitrary: 'kept' } });
    const first = sync(source, database, silent);
    assert.equal(first.files, 5);
    assert.equal(first.messages, 2);
    assert.equal(first.conversations, 1);
    assert.equal(first.activities, 3);
    assert.equal(first.duplicate_messages, 1);
    assert.equal(first.activities_without_date, 2);
    const second = sync(source, database, silent);
    assert.deepEqual(second.sync, { scanned: 5, changed: 0, unchanged: 5, removed: 0 });
    assert.equal(second.messages, 2);
    const db = openDatabase(database);
    assert.equal(gunzipSync(db.prepare("SELECT raw_gzip FROM source_files WHERE path LIKE '%message_1.json'").get().raw_gzip).toString(), original);
    assert.equal(db.prepare('PRAGMA integrity_check').get().integrity_check, 'ok');
    assert.deepEqual(db.prepare('PRAGMA foreign_key_check').all(), []);
    db.close();
});

test('updates changed files and reactions; removes missing files only from snapshot', t => {
    const { source, database, write } = setup(t);
    const relative = 'messages/inbox/test_123/message_1.json';
    write(relative, thread([message]));
    const other = write('posts/posts.json', [{ timestamp: 1, title: 'Before' }]);
    sync(source, database, silent);
    write(relative, thread([{ ...message, reactions: [{ actor: 'Bạn bè', reaction: '❤' }] }]));
    fs.unlinkSync(other);
    const result = sync(source, database, silent);
    assert.equal(result.messages, 1);
    assert.equal(result.activities, 0);
    assert.equal(result.sync.removed, 1);
    const db = openDatabase(database);
    assert.equal(db.prepare('SELECT reaction_count FROM messages').get().reaction_count, 1);
    db.close();
    assert.ok(fs.existsSync(path.join(source, relative)));
});

test('preserves messages with empty or missing sender names', t => {
    const { source, database, write } = setup(t);
    write('messages/inbox/test_123/message_1.json', thread([{ timestamp_ms: 1700000000000, sender_name: '', is_geoblocked_for_viewer: true }, { timestamp_ms: 1700000000001 }]));
    assert.equal(sync(source, database, silent).messages, 2);
});

test('retains exact large IDs and treats zero timestamps as unknown', t => {
    const { source, database, write } = setup(t);
    write('posts/posts.json', '[{"fbid":12345678901234567890,"timestamp":0,"title":"Test"}]');
    assert.equal(sync(source, database, silent).activities_without_date, 1);
    const db = openDatabase(database);
    const record = db.prepare('SELECT payload_json,occurred_at FROM activities').get();
    assert.equal(JSON.parse(record.payload_json).fbid, '12345678901234567890');
    assert.equal(record.occurred_at, null);
    db.close();
});

test('invalid JSON rolls back the entire sync and another source is rejected', t => {
    const { source, database, write } = setup(t);
    write('a.json', [{ title: 'Original' }]);
    sync(source, database, silent);
    write('a.json', [{ title: 'Changed' }]);
    write('z.json', '{broken');
    assert.throws(() => sync(source, database, silent), /Invalid JSON/);
    const db = openDatabase(database);
    assert.equal(db.prepare('SELECT title FROM activities').get().title, 'Original');
    assert.equal(db.prepare('SELECT COUNT(*) AS n FROM source_files').get().n, 1);
    assert.equal(db.prepare('SELECT status FROM sync_runs ORDER BY id DESC LIMIT 1').get().status, 'failed');
    db.close();
    const other = path.join(path.dirname(source), 'other');
    fs.mkdirSync(other);fs.writeFileSync(path.join(other, 'test.json'), '[]');
    assert.throws(() => sync(other, database, silent), /another source/);
});

test('dashboard API searches, paginates and rejects foreign origins and writes', async t => {
    const { source, database, write } = setup(t);
    write('messages/inbox/test_123/message_1.json', thread([message]));
    write('posts/posts.json', Array.from({ length: 55 }, (_, i) => ({ title: 'Bản ghi '+i, timestamp: 1700000000+i })));
    sync(source, database, silent);
    const server = createServer(database);
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    try {
        const base = 'http://127.0.0.1:'+server.address().port;
        const overview = await (await fetch(base+'/api/overview')).json();assert.equal(overview.messages, 1);
        const page = await (await fetch(base+'/api/activities?page=2')).json();assert.equal(page.data.length, 5);assert.equal(page.total, 55);
        const results = await (await fetch(base+'/api/messages?q='+encodeURIComponent('Xin chào'))).json();assert.equal(results.total, 1);
        const injected = await (await fetch(base+'/api/activities?q='+encodeURIComponent("' OR 1=1 --"))).json();assert.equal(injected.total, 0);
        assert.equal((await fetch(base+'/api/overview', { headers: { Origin: 'https://example.com' } })).status, 403);
        assert.equal((await fetch(base+'/api/overview', { method: 'POST' })).status, 405);
        const html = await (await fetch(base)).text();assert.ok(html.includes('src="/app.js"'));
        const js = await (await fetch(base+'/app.js')).text();assert.ok(js.includes('node.textContent=text'));assert.ok(!js.includes('innerHTML'));
    } finally { await new Promise(resolve => server.close(resolve)); }
});
