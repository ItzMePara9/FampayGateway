<div id="fampay-gateway" x-data="{
    checkUrl: '{{ $checkPaymentUrl }}',
    invoiceUrl: '{{ $invoiceUrl }}',
    timeoutMin: {{ $timeout }},
    pollSec: {{ $pollInterval }},
    remaining: {{ $timeout }} * 60,
    isPaid: false,
    timerText: '{{ $timeout }}:00',
    statusText: 'Waiting for payment confirmation…',
    statusClass: '',
    timerLabelText: 'Time Remaining',
    timerMsgText: 'QR expires in {{ $timeout }} minutes',
    timerLabelColor: '',
    timerMsgColor: '',
    ringOffset: '0',
    ringColor: '#6366f1',
    qrOpacity: '1',
    showRetry: false,
    showUpiBtn: true,
    timerUrgent: false,
    csrf: '',
    _ti: null,
    _pi: null,
    init() {
        var self = this;
        var cm = document.querySelector('meta[name=csrf-token]');
        self.csrf = cm ? cm.getAttribute('content') : '';
        if (!self.csrf) { var ck = document.cookie.match(/XSRF-TOKEN=([^;]+)/); if (ck) self.csrf = decodeURIComponent(ck[1]); }
        console.log('[AG] Boot OK. CSRF=' + (self.csrf ? 'yes' : 'NO'));
        self._ti = setInterval(function() { self.tick(); }, 1000);
        var pn = 0;
        self._pi = setInterval(function() { self.poll(++pn); }, self.pollSec * 1000);
        setTimeout(function() { self.poll(++pn); }, 3000);
    },
    tick() {
        this.remaining--;
        if (this.remaining <= 0) { this.doExpire(); return; }
        var mm = Math.floor(this.remaining / 60);
        var ss = this.remaining % 60;
        this.timerText = mm + ':' + (ss < 10 ? '0' : '') + ss;
        this.ringOffset = String(150.8 * (1 - this.remaining / (this.timeoutMin * 60)));
        if (this.remaining <= 60) { this.timerUrgent = true; this.timerMsgText = 'Expiring soon!'; this.timerMsgColor = '#ef4444'; }
    },
    poll(n) {
        if (this.isPaid) return;
        var self = this;
        console.log('[AG] Poll #' + n);
        var h = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (self.csrf) h['X-CSRF-TOKEN'] = self.csrf;
        fetch(self.checkUrl, { method: 'POST', headers: h, credentials: 'same-origin' })
        .then(function(r) {
            console.log('[AG] HTTP ' + r.status);
            if (r.status === 419) { var fm = document.querySelector('meta[name=csrf-token]'); if (fm) self.csrf = fm.getAttribute('content'); return null; }
            return r.ok ? r.json() : null;
        })
        .then(function(j) {
            if (!j) return;
            console.log('[AG] Response:', JSON.stringify(j));
            if (j.status === 'paid') { self.isPaid = true; self.doPaid(j); }
            else if (j.status === 'expired') { self.doExpire(); }
            else if (j.error) { console.error('[AG] SERVER ERROR:', j.error, j.file, j.trace); self.statusText = 'Error: ' + j.error; }
        })
        .catch(function(e) { console.error('[AG]', e); self.statusText = 'Retrying…'; });
    },
    doPaid(j) {
        var self = this;
        clearInterval(self._ti); clearInterval(self._pi);
        self.statusClass = 'ag-paid'; self.statusText = '✓ Confirmed!';
        self.timerText = '✓'; self.timerLabelText = 'Paid'; self.timerLabelColor = '#22c55e';
        self.timerMsgText = 'Verified'; self.timerMsgColor = '#22c55e';
        self.ringColor = '#22c55e'; self.ringOffset = '0'; self.showUpiBtn = false;
        setTimeout(function() { window.location.href = j.redirect || self.invoiceUrl; }, 2000);
    },
    doExpire() {
        clearInterval(this._ti); clearInterval(this._pi); this.isPaid = true;
        this.statusClass = 'ag-expired'; this.statusText = 'Payment expired';
        this.timerText = '0:00'; this.timerLabelText = 'Expired'; this.timerLabelColor = '#ef4444';
        this.timerMsgText = 'Try again'; this.timerMsgColor = '#ef4444';
        this.qrOpacity = '0.25'; this.showUpiBtn = false; this.showRetry = true;
    }
}">
    <style>
        *,*::before,*::after{box-sizing:border-box}
        #fampay-gateway{font-family:'Inter','SF Pro Display','Segoe UI',system-ui,-apple-system,sans-serif;color:#e2e8f0;text-align:center;padding:1rem;-webkit-font-smoothing:antialiased}
        .ag-container{max-width:440px;margin:0 auto}
        .ag-badge{display:inline-flex;align-items:center;gap:.4rem;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);border-radius:2rem;padding:.3rem .9rem;font-size:.7rem;font-weight:600;color:#a78bfa;letter-spacing:.05em;text-transform:uppercase;margin-bottom:1rem}
        .ag-badge-dot{width:6px;height:6px;border-radius:50%;background:#22c55e;animation:ag-blink 2s ease-in-out infinite}
        @keyframes ag-blink{0%,100%{opacity:1}50%{opacity:.3}}
        .ag-card{background:linear-gradient(145deg,#1a1b2e,#141520,#0f1017);border:1px solid rgba(99,102,241,.15);border-radius:1.5rem;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.03) inset}
        .ag-topbar{height:3px;background:linear-gradient(90deg,#6366f1,#8b5cf6,#a78bfa,#c4b5fd,#a78bfa,#8b5cf6,#6366f1);background-size:300% 100%;animation:ag-grad 4s ease infinite}
        @keyframes ag-grad{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
        .ag-body{padding:1.75rem 1.5rem}
        .ag-amount-label{font-size:.65rem;color:#64748b;text-transform:uppercase;letter-spacing:.15em;font-weight:600;margin-bottom:.35rem}
        .ag-amount{font-size:2.75rem;font-weight:800;background:linear-gradient(135deg,#e0e7ff,#a78bfa,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1.1;margin-bottom:.5rem}
        .ag-ref{display:inline-flex;align-items:center;gap:.4rem;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.12);border-radius:.5rem;padding:.35rem .85rem;font-size:.78rem;color:#94a3b8}
        .ag-ref code{color:#a78bfa;font-weight:700;font-family:'SF Mono','Fira Code',monospace;font-size:.8rem}
        .ag-divider{height:1px;background:linear-gradient(90deg,transparent,rgba(99,102,241,.2),transparent);margin:1.25rem 0}
        .ag-qr-label{font-size:.7rem;color:#64748b;text-transform:uppercase;letter-spacing:.1em;font-weight:600;margin-bottom:.75rem}
        .ag-qr-frame{position:relative;display:inline-block;padding:1.25rem;background:#fff;border-radius:1rem;box-shadow:0 8px 24px rgba(0,0,0,.15);max-width:calc(100% - 2rem);transition:opacity .3s}
        .ag-qr-frame img{display:block;max-width:100%;height:auto}
        .ag-qr-frame::before,.ag-qr-frame::after{content:'';position:absolute;width:20px;height:20px;border:2px solid #6366f1;pointer-events:none}
        .ag-qr-frame::before{top:8px;left:8px;border-right:0;border-bottom:0;border-radius:4px 0 0 0}
        .ag-qr-frame::after{bottom:8px;right:8px;border-left:0;border-top:0;border-radius:0 0 4px 0}
        .ag-scan-hint{margin-top:.75rem;font-size:.75rem;color:#64748b}
        .ag-note-warn{font-size:.65rem;color:#f59e0b;background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.12);border-radius:.4rem;padding:.3rem .7rem;margin-top:.6rem;display:inline-block}
        .ag-upi-btn{display:block;width:100%;padding:.85rem 1.5rem;margin-top:1rem;border:none;border-radius:.75rem;font-size:.9rem;font-weight:700;cursor:pointer;background:linear-gradient(135deg,#6366f1,#7c3aed);color:#fff;box-shadow:0 4px 16px rgba(99,102,241,.3);transition:all .2s;text-decoration:none;text-align:center}
        .ag-upi-btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,.45)}
        .ag-timer-section{display:flex;align-items:center;justify-content:center;gap:1rem;margin-top:.25rem}
        .ag-timer-ring{position:relative;width:56px;height:56px;flex-shrink:0}
        .ag-timer-ring svg{transform:rotate(-90deg)}
        .ag-ring-bg{stroke:rgba(99,102,241,.1);fill:none;stroke-width:3}
        .ag-ring-fg{fill:none;stroke-width:3;stroke-linecap:round;transition:stroke-dashoffset 1s linear}
        .ag-timer-time{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:.8rem;font-weight:700;color:#e2e8f0;font-family:'SF Mono','Fira Code',monospace}
        .ag-timer-info{text-align:left}
        .ag-timer-info-label{font-size:.65rem;color:#64748b;text-transform:uppercase;letter-spacing:.08em;font-weight:600}
        .ag-timer-info-msg{font-size:.78rem;color:#94a3b8;margin-top:.15rem}
        .ag-status{display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.6rem 1rem;margin-top:1rem;background:rgba(99,102,241,.05);border-radius:.5rem;font-size:.78rem;color:#94a3b8}
        .ag-status-dot{width:7px;height:7px;border-radius:50%;background:#6366f1;animation:ag-pulse 1.5s ease-in-out infinite;flex-shrink:0}
        @keyframes ag-pulse{0%,100%{opacity:.4;transform:scale(.8)}50%{opacity:1;transform:scale(1.2)}}
        .ag-status.ag-paid{background:rgba(34,197,94,.08)}.ag-status.ag-paid .ag-status-dot{background:#22c55e;animation:none}
        .ag-status.ag-expired{background:rgba(239,68,68,.08)}.ag-status.ag-expired .ag-status-dot{background:#ef4444;animation:none}
        .ag-retry{padding:.6rem 2rem;margin-top:.75rem;border:1px solid rgba(99,102,241,.25);border-radius:.6rem;background:transparent;color:#a78bfa;font-size:.8rem;font-weight:600;cursor:pointer}
        .ag-footer{padding:.75rem 1.5rem;background:rgba(0,0,0,.15);border-top:1px solid rgba(99,102,241,.08);display:flex;align-items:center;justify-content:center;font-size:.6rem;color:#475569}
        @media(max-width:480px){#fampay-gateway{padding:.5rem}.ag-body{padding:1.25rem .75rem}.ag-amount{font-size:2rem}.ag-qr-frame{padding:.75rem}}
    </style>

    <div class="ag-container">
        <div class="ag-badge"><span class="ag-badge-dot"></span> Secure UPI Payment</div>
        <div class="ag-card">
            <div class="ag-topbar"></div>
            <div class="ag-body">
                <div class="ag-amount-label">Amount Due</div>
                <div class="ag-amount">₹{{ number_format($total, 2) }}</div>
                <div class="ag-ref">Reference: <code>{{ $note }}</code></div>
                <div class="ag-divider"></div>

                <div class="ag-qr-label">Scan to Pay</div>
                <div class="ag-qr-frame" x-bind:style="'opacity:' + qrOpacity">
                    @if(!empty($qrDataUri))
                        <img src="{{ $qrDataUri }}" width="200" height="200" alt="UPI QR Code" style="display:block;image-rendering:pixelated">
                    @else
                        <div style="width:200px;height:200px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:.8rem">QR Loading…</div>
                    @endif
                </div>
                <div class="ag-scan-hint">Scan QR code with any UPI app</div>
                <div class="ag-note-warn">⚠️ Keep UPI remark as <strong>{{ $note }}</strong></div>

                <a x-show="showUpiBtn" class="ag-upi-btn" href="{{ $upiString }}">⚡ Pay using UPI App</a>

                <div class="ag-divider"></div>

                <div class="ag-timer-section">
                    <div class="ag-timer-ring" x-bind:class="timerUrgent ? 'ag-timer-ring ag-urgent' : 'ag-timer-ring'">
                        <svg width="56" height="56" viewBox="0 0 56 56">
                            <circle class="ag-ring-bg" cx="28" cy="28" r="24"/>
                            <circle class="ag-ring-fg" cx="28" cy="28" r="24" stroke-dasharray="150.8"
                                    x-bind:stroke-dashoffset="ringOffset" x-bind:stroke="ringColor"/>
                        </svg>
                        <div class="ag-timer-time" x-text="timerText"></div>
                    </div>
                    <div class="ag-timer-info">
                        <div class="ag-timer-info-label" x-text="timerLabelText" x-bind:style="timerLabelColor ? 'color:'+timerLabelColor : ''"></div>
                        <div class="ag-timer-info-msg" x-text="timerMsgText" x-bind:style="timerMsgColor ? 'color:'+timerMsgColor : ''"></div>
                    </div>
                </div>

                <div class="ag-status" x-bind:class="statusClass ? 'ag-status ' + statusClass : 'ag-status'">
                    <div class="ag-status-dot"></div>
                    <span x-text="statusText"></span>
                </div>

                <button x-show="showRetry" class="ag-retry" x-on:click="location.reload()">↻ Generate New QR Code</button>
            </div>
            <div class="ag-footer">🔒 Secured by Fampay Gateway • Developed by Rolex Dev</div>
        </div>
    </div>
</div>
