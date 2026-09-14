export function fold(value) { return String(value ?? '').normalize('NFD').replace(/\p{M}/gu, '').replace(/[đĐ]/g, 'd').toLowerCase(); }
export function dateRange(start, end, basis = 'local') {
    const result = {};
    function parse(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) throw Error('Ngày không hợp lệ.');
        const [y,m,d] = value.split('-').map(Number), date = basis==='utc'?new Date(Date.UTC(y,m-1,d)):new Date(y,m-1,d);
        const parts=basis==='utc'?[date.getUTCFullYear(),date.getUTCMonth(),date.getUTCDate()]:[date.getFullYear(),date.getMonth(),date.getDate()];
        if (parts[0]!==y || parts[1]!==m-1 || parts[2]!==d) throw Error('Ngày không hợp lệ.');
        return date;
    }
    if (start) result.from = parse(start).toISOString();
    if (end) { const date = parse(end); if(basis==='utc')date.setUTCDate(date.getUTCDate()+1);else date.setDate(date.getDate()+1);result.until = date.toISOString(); }
    if (result.from && result.until && result.from>=result.until) throw Error('Ngày bắt đầu phải trước hoặc bằng ngày kết thúc.');
    return result;
}
export function highlightParts(value, query) {
    const text = String(value ?? ''), terms = fold(query).match(/[\p{L}\p{N}]+/gu) ?? [];
    let normalized = '', offsets = [];
    for (let i=0;i<text.length;) {
        const char = String.fromCodePoint(text.codePointAt(i)), part=fold(char);
        for (let j=0;j<part.length;j++) offsets.push([i,i+char.length]);
        normalized+=part;i+=char.length;
    }
    const spans=[];
    for(const term of terms.slice(0,20)) for(let at=normalized.indexOf(term);at>=0;at=normalized.indexOf(term,at+term.length)) {
        spans.push([offsets[at][0],offsets[at+term.length-1][1]]);
    }
    spans.sort((a,b)=>a[0]-b[0]);const merged=[];
    for(const span of spans){const last=merged.at(-1);if(last&&span[0]<=last[1])last[1]=Math.max(last[1],span[1]);else merged.push([...span]);}
    const parts=[];let index=0;
    for(const [start,end] of merged){if(index<start)parts.push({text:text.slice(index,start),match:false});parts.push({text:text.slice(start,end),match:true});index=end;}
    if(index<text.length)parts.push({text:text.slice(index),match:false});
    return parts;
}
