{{-- This partial is included INSIDE the exportImage() JS method body --}}
if (!this.circuit || !this.baskets.length) return;

const scale = 2;
const pad = 60;
const positions = this.positions;
const bs = this.baskets;
const _NW = this.NW, _NH = this.NH;

const xs = bs.map(b => (positions[b.id] || {x:0}).x);
const ys = bs.map(b => (positions[b.id] || {y:0}).y);
const minX = Math.min(...xs), minY = Math.min(...ys);
const maxX = Math.max(...xs) + _NW, maxY = Math.max(...ys) + _NH;
const headerH = 50;
const w = (maxX - minX) + pad * 2, h = (maxY - minY) + pad * 2 + headerH;

const c = document.createElement('canvas');
c.width = w * scale; c.height = h * scale;
const ctx = c.getContext('2d');
ctx.scale(scale, scale);

// Background
ctx.fillStyle = this.dark ? '#111827' : '#ffffff';
ctx.fillRect(0, 0, w, h);

// Grid dots
ctx.fillStyle = this.dark ? '#1f2937' : '#e5e7eb';
for (let gx = 0; gx < w; gx += 24) {
    for (let gy = 0; gy < h; gy += 24) {
        ctx.beginPath(); ctx.arc(gx, gy, 0.8, 0, Math.PI * 2); ctx.fill();
    }
}

// Title
ctx.fillStyle = this.dark ? '#f9fafb' : '#111827';
ctx.font = 'bold 18px system-ui,sans-serif';
ctx.textBaseline = 'top';
ctx.fillText(this.circuit.name, pad, 20);
ctx.fillStyle = this.dark ? '#6b7280' : '#9ca3af';
ctx.font = '12px system-ui,sans-serif';
ctx.fillText(this.circuit.targetModel + (this.circuit.description ? ' — ' + this.circuit.description : ''), pad, 42);

const ox = pad - minX, oy = pad - minY + headerH;
const lineCol = this.dark ? 'rgba(129,140,248,0.7)' : 'rgba(99,102,241,0.5)';
const dotCol = this.dark ? '#a5b4fc' : '#6366f1';
const lblBg = this.dark ? 'rgba(31,41,55,0.9)' : 'rgba(255,255,255,0.95)';
const lblCol = this.dark ? '#d1d5db' : '#4b5563';

// Edges
bs.forEach(b => {
    (b.next || []).forEach(n => {
        const f = positions[b.id], t = positions[n.id];
        if (!f || !t) return;
        const x1 = f.x + _NW + ox, y1 = f.y + _NH/2 + oy;
        const x2 = t.x + ox, y2 = t.y + _NH/2 + oy;
        const dx = Math.max(Math.abs(x2-x1)*0.5, 70);
        const cx1 = x1+dx, cy1 = y1, cx2 = x2-dx, cy2 = y2;

        ctx.beginPath(); ctx.strokeStyle = this.dark ? 'rgba(0,0,0,0.3)' : 'rgba(0,0,0,0.08)'; ctx.lineWidth = 5;
        ctx.moveTo(x1,y1); ctx.bezierCurveTo(cx1,cy1,cx2,cy2,x2,y2); ctx.stroke();
        ctx.beginPath(); ctx.strokeStyle = lineCol; ctx.lineWidth = 2;
        ctx.moveTo(x1,y1); ctx.bezierCurveTo(cx1,cy1,cx2,cy2,x2,y2); ctx.stroke();
        ctx.fillStyle = dotCol;
        ctx.beginPath(); ctx.arc(x1,y1,4,0,Math.PI*2); ctx.fill();

        const u=0.97, uu=1-u;
        const apx = uu*uu*uu*x1+3*uu*uu*u*cx1+3*uu*u*u*cx2+u*u*u*x2;
        const apy = uu*uu*uu*y1+3*uu*uu*u*cy1+3*uu*u*u*cy2+u*u*u*y2;
        const angle = Math.atan2(y2-apy, x2-apx);
        ctx.beginPath(); ctx.fillStyle = dotCol;
        ctx.moveTo(x2,y2);
        ctx.lineTo(x2-7*Math.cos(angle-0.4), y2-7*Math.sin(angle-0.4));
        ctx.lineTo(x2-7*Math.cos(angle+0.4), y2-7*Math.sin(angle+0.4));
        ctx.closePath(); ctx.fill();

        const label = n.pivot?.label;
        if (label) {
            const mt=0.5, mu=1-mt;
            const mpx = mu*mu*mu*x1+3*mu*mu*mt*cx1+3*mu*mt*mt*cx2+mt*mt*mt*x2;
            const mpy = mu*mu*mu*y1+3*mu*mu*mt*cy1+3*mu*mt*mt*cy2+mt*mt*mt*y2;
            ctx.font = '600 10px system-ui,sans-serif';
            const tw = ctx.measureText(label).width;
            ctx.fillStyle = lblBg;
            const rr=5, rpx=4, rpy=2, rx=mpx-tw/2-rpx, ry=mpy-7-rpy, rw=tw+rpx*2, rh=14+rpy*2;
            ctx.beginPath();
            ctx.moveTo(rx+rr,ry); ctx.lineTo(rx+rw-rr,ry); ctx.quadraticCurveTo(rx+rw,ry,rx+rw,ry+rr);
            ctx.lineTo(rx+rw,ry+rh-rr); ctx.quadraticCurveTo(rx+rw,ry+rh,rx+rw-rr,ry+rh);
            ctx.lineTo(rx+rr,ry+rh); ctx.quadraticCurveTo(rx,ry+rh,rx,ry+rh-rr);
            ctx.lineTo(rx,ry+rr); ctx.quadraticCurveTo(rx,ry,rx+rr,ry);
            ctx.closePath(); ctx.fill();
            ctx.fillStyle = lblCol; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText(label, mpx, mpy); ctx.textAlign = 'start';
        }
    });
});

// Nodes
bs.forEach(b => {
    const p = positions[b.id]; if (!p) return;
    const x = p.x + ox, y = p.y + oy;
    const col = this.color(b.color);
    const cr = 10;

    ctx.shadowColor = this.dark ? 'rgba(0,0,0,0.5)' : 'rgba(0,0,0,0.1)';
    ctx.shadowBlur = 10; ctx.shadowOffsetY = 2;
    ctx.fillStyle = this.dark ? '#1f2937' : '#ffffff';
    ctx.beginPath();
    ctx.moveTo(x+cr,y); ctx.lineTo(x+_NW-cr,y); ctx.quadraticCurveTo(x+_NW,y,x+_NW,y+cr);
    ctx.lineTo(x+_NW,y+_NH-cr); ctx.quadraticCurveTo(x+_NW,y+_NH,x+_NW-cr,y+_NH);
    ctx.lineTo(x+cr,y+_NH); ctx.quadraticCurveTo(x,y+_NH,x,y+_NH-cr);
    ctx.lineTo(x,y+cr); ctx.quadraticCurveTo(x,y,x+cr,y); ctx.closePath(); ctx.fill();
    ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0; ctx.shadowOffsetY = 0;
    ctx.strokeStyle = this.dark ? '#374151' : '#e5e7eb'; ctx.lineWidth = 1; ctx.stroke();

    ctx.fillStyle = col;
    ctx.beginPath(); ctx.moveTo(x+cr,y); ctx.lineTo(x+_NW-cr,y); ctx.quadraticCurveTo(x+_NW,y,x+_NW,y+cr);
    ctx.lineTo(x+_NW,y+6); ctx.lineTo(x,y+6); ctx.lineTo(x,y+cr); ctx.quadraticCurveTo(x,y,x+cr,y);
    ctx.closePath(); ctx.fill();

    ctx.font = 'bold 9px system-ui,sans-serif';
    const stw = ctx.measureText(b.status).width;
    ctx.fillStyle = col + '20';
    const bx=x+12, by=y+14, bw=stw+8, bh=16, br=4;
    ctx.beginPath();
    ctx.moveTo(bx+br,by); ctx.lineTo(bx+bw-br,by); ctx.quadraticCurveTo(bx+bw,by,bx+bw,by+br);
    ctx.lineTo(bx+bw,by+bh-br); ctx.quadraticCurveTo(bx+bw,by+bh,bx+bw-br,by+bh);
    ctx.lineTo(bx+br,by+bh); ctx.quadraticCurveTo(bx,by+bh,bx,by+bh-br);
    ctx.lineTo(bx,by+br); ctx.quadraticCurveTo(bx,by,bx+br,by); ctx.closePath(); ctx.fill();
    ctx.fillStyle = col; ctx.textBaseline = 'middle';
    ctx.fillText(b.status, bx+4, by+bh/2);

    ctx.fillStyle = this.dark ? '#f3f4f6' : '#1f2937';
    ctx.font = '600 12px system-ui,sans-serif'; ctx.textBaseline = 'top';
    ctx.fillText(b.name, x+12, y+36, _NW-24);

    if ((b.roles||[]).length) {
        ctx.fillStyle = this.dark ? '#6b7280' : '#9ca3af';
        ctx.font = '10px system-ui,sans-serif';
        ctx.fillText(b.roles.join(', '), x+12, y+56, _NW-24);
    }

    if (!(b.next||[]).length && b.status !== 'DRAFT') {
        ctx.fillStyle = '#10b981'; ctx.font = 'bold 9px system-ui,sans-serif';
        ctx.fillText('FIN', x+12, y+_NH-20);
    }
});

// Watermark
ctx.fillStyle = this.dark ? '#374151' : '#d1d5db';
ctx.font = '10px system-ui,sans-serif'; ctx.textAlign = 'right'; ctx.textBaseline = 'bottom';
ctx.fillText('Laravel Workflow — ' + new Date().toLocaleDateString('fr-FR'), w-20, h-12);

// Download
c.toBlob(blob => {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'workflow-' + this.circuit.name.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '.png';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
    this.notify('Image exportée');
}, 'image/png');
