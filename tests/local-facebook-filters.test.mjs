import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { sync, openDatabase } from '../scripts/facebook-local.mjs';
import { createServer } from '../scripts/facebook-local-server.mjs';
import { dateRange, highlightParts } from '../scripts/facebook-search-client.mjs';

async function fixture(t) {
    const root=fs.mkdtempSync(path.join(os.tmpdir(),'atlas-filter-test-')),source=path.join(root,'source'),database=path.join(root,'data.sqlite');
    fs.mkdirSync(source);
    const write=(name,data)=>{const p=path.join(source,name);fs.mkdirSync(path.dirname(p),{recursive:true});fs.writeFileSync(p,JSON.stringify(data));};
    const messages=[
        {sender_name:'Đặng Ánh',timestamp_ms:Date.parse('2026-08-31T17:00:00Z'),content:'Hẹn cà phê sáng mai',photos:[{uri:'photo.jpg'}],reactions:[{actor:'Bạn',reaction:'❤'}]},
        {sender_name:'Bạn',timestamp_ms:Date.parse('2026-09-01T16:59:59Z'),content:'Cà ngon và phê quá',audio_files:[{uri:'audio.mp3'}]},
        {sender_name:'Đặng Ánh',timestamp_ms:Date.parse('2026-09-01T17:00:00Z'),content:'Cà phê quảng cáo',share:{link:'https://example.com'}},
        {sender_name:'',content:null,files:[{uri:'file.pdf'}]},
        ...Array.from({length:60},(_,i)=>({sender_name:'Bạn',timestamp_ms:Date.parse('2026-09-03T00:00:00Z'),content:'Ngữ cảnh thứ '+i})),
    ];
    const thread={participants:[{name:'Đặng Ánh'},{name:'Bạn'}],messages,title:'Hội thoại Đặng Ánh',thread_path:'inbox/thread_123'};
    write('messages/inbox/thread_123/message_1.json',thread);
    write('messages/archived_threads/thread_123/message_2.json',{...thread,messages:[messages[0]]});
    write('posts/posts.json',[{timestamp:Date.parse('2026-09-01T12:00:00Z')/1000,title:'Đi Đà Nẵng'},{title:'Thiếu thời gian'}]);
    write('groups/comments.json',{group_comments_v2:[{timestamp:Date.parse('2026-09-01T12:00:00Z')/1000,title:'Cà phê ở Đà Nẵng'}]});
    sync(source,database,()=>{});
    const server=createServer(database);await new Promise(r=>server.listen(0,'127.0.0.1',r));
    t.after(async()=>{await new Promise(r=>server.close(r));fs.rmSync(root,{recursive:true,force:true});});
    const base='http://127.0.0.1:'+server.address().port;
    const get=async(route,params={})=>{const response=await fetch(base+'/api/'+route+'?'+new URLSearchParams(params));assert.equal(response.status,200);return response.json();};
    return {get,base,source,database,write,thread};
}

test('accent-insensitive all/any/phrase/exclusion search uses safe FTS queries',async t=>{
    const {get}=await fixture(t);
    assert.equal((await get('messages',{q:'CA PHE',mode:'all'})).total,3);
    assert.equal((await get('messages',{q:'ca phe',mode:'phrase'})).total,2);
    assert.equal((await get('messages',{q:'hen ngu',mode:'any'})).total,61);
    assert.equal((await get('messages',{q:'ca phe',exclude:'quang cao'})).total,2);
    assert.equal((await get('messages',{q:'dang anh'})).total,2);
    assert.equal((await get('messages',{q:'" OR 1=1 --'})).total,0);
    assert.equal((await get('messages',{q:'" NEAR(*) :',mode:'phrase'})).total,0);
    assert.equal((await get('conversations',{q:'dang anh',min_count:60})).total,1);
});

test('combines sender/thread/date/media/reactions and uses actual source buckets',async t=>{
    const {get}=await fixture(t);
    const range={from:'2026-08-31T17:00:00.000Z',until:'2026-09-01T17:00:00.000Z'};
    assert.equal((await get('messages',range)).total,2);
    assert.equal((await get('messages',{...range,thread:'thread_123',sender:'Đặng Ánh',media:'photo',reactions:'yes',q:'ca phe'})).total,1);
    assert.equal((await get('messages',{bucket:'archived_threads'})).total,1);
    assert.equal((await get('messages',{media:'audio'})).total,1);
    assert.equal((await get('messages',{media:'file',quality:'missing_sender'})).total,1);
    assert.equal((await get('messages',{media:'link'})).total,1);
    assert.equal((await get('messages',{quality:'missing_date'})).total,1);
    assert.equal((await get('messages',{quality:'duplicate'})).total,1);
    assert.equal((await get('messages',{media:'__proto__'})).total,64);
    const names=await get('senders',{q:'dang',thread:'thread_123'});assert.equal(names.data[0].sender,'Đặng Ánh');
});

test('filters activities by category, kind, source file, unknown date and text',async t=>{
    const {get}=await fixture(t);
    assert.equal((await get('activities',{q:'da nang'})).total,2);
    assert.equal((await get('activities',{q:'da nang',category:'groups',kind:'group_comments_v2'})).total,1);
    const files=await get('files',{q:'posts.json'});
    assert.equal((await get('activities',{file:files.data[0].id,quality:'missing_date'})).total,1);
    assert.equal((await get('activities',{category:'groups',quality:'missing_date'})).total,0);
    const options=await get('options');assert.equal(options.threads.length,1);assert.equal(options.buckets.length,2);
});

test('context is chronological, bounded, includes source provenance and pages through ties',async t=>{
    const {get}=await fixture(t);
    const all=await get('messages',{q:'ngu canh',page:1,sort:'oldest'}),target=all.data[30];
    const context=await get('context',{id:target.fingerprint});
    assert.equal(context.data.length,41);assert.equal(context.data[20].fingerprint,target.fingerprint);
    assert.ok(context.previous);assert.ok(context.next);assert.equal(context.sources.length,1);
    assert.equal(new Set(context.data.map(x=>x.fingerprint)).size,41);
    assert.ok(context.data.every((x,i,a)=>!i||x.position>a[i-1].position));
    const previous=await get('context',{id:context.previous});assert.notEqual(previous.anchor,context.anchor);
});

test('rejects invalid ranges, clamps pagination and refreshes FTS after sync',async t=>{
    const {get,base,source,database,write,thread}=await fixture(t);
    for(const params of [{from:'bad'},{from:'2026-09-03T00:00:00.000Z',until:'2026-09-02T00:00:00.000Z'}])assert.equal((await fetch(base+'/api/messages?'+new URLSearchParams(params))).status,400);
    assert.equal((await get('activities',{page:999999})).page,1);
    assert.equal((await get('messages',{q:'moi nhat'})).total,0);
    write('messages/inbox/thread_123/message_1.json',{...thread,messages:[{sender_name:'Bạn',timestamp_ms:1700000000000,content:'Nội dung mới nhất'}]});
    sync(source,database,()=>{});
    assert.equal((await get('messages',{q:'moi nhat'})).total,1);
    assert.equal((await get('messages',{q:'ngu canh'})).total,0);
    assert.equal((await get('overview')).messages,2);
    const db=openDatabase(database);
    assert.equal(db.prepare('SELECT COUNT(*) AS n FROM message_search').get().n,2);
    assert.ok(db.prepare("EXPLAIN QUERY PLAN SELECT rowid FROM message_search WHERE message_search MATCH 'moi'").all().some(x=>x.detail.includes('VIRTUAL TABLE')));
    db.close();
});

test('inclusive calendar ranges and highlights preserve Unicode and markup as text',()=>{
    assert.deepEqual(dateRange('2026-09-01','2026-09-01','utc'),{from:'2026-09-01T00:00:00.000Z',until:'2026-09-02T00:00:00.000Z'});
    assert.deepEqual(dateRange('2026-09-01','2026-09-01'),{from:new Date(2026,8,1).toISOString(),until:new Date(2026,8,2).toISOString()});
    assert.throws(()=>dateRange('2026-02-30',''),/Ngày/);assert.throws(()=>dateRange('2026-09-02','2026-09-01'),/Ngày bắt đầu/);
    const original='😀 Đặng Ánh uống cà phê <script>alert(1)</script>',parts=highlightParts(original,'DANG ca phe');
    assert.equal(parts.map(x=>x.text).join(''),original);assert.deepEqual(parts.filter(x=>x.match).map(x=>x.text),['Đặng','cà','phê']);
});
