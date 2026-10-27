// ============================================================
// Ganeshman Singh Academy - Main JavaScript (v2)
// ============================================================
'use strict';

// -----------------------------------------------
// Toast Notifications
// -----------------------------------------------
const Toast = {
  container: null,
  init() {
    this.container = document.createElement('div');
    this.container.className = 'toast-container';
    document.body.appendChild(this.container);
  },
  show(message, type = 'success', duration = 3000) {
    const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
    const toast = document.createElement('div');
    toast.className = `toast ${type !== 'success' ? type : ''}`;
    toast.innerHTML = `<span>${icons[type] || '✓'}</span> ${message}`;
    this.container.appendChild(toast);
    setTimeout(() => { toast.style.opacity='0'; toast.style.transform='translateX(60px)'; setTimeout(()=>toast.remove(),400); }, duration);
  },
  success(msg) { this.show(msg,'success'); },
  error(msg)   { this.show(msg,'error'); },
  warning(msg) { this.show(msg,'warning'); },
  info(msg)    { this.show(msg,'info'); }
};

// -----------------------------------------------
// Modal Controller
// -----------------------------------------------
const Modal = {
  open(id) {
    const o = document.getElementById(id);
    if (o) { o.classList.add('open'); document.body.style.overflow='hidden'; }
  },
  close(id) {
    const o = document.getElementById(id);
    if (o) { o.classList.remove('open'); document.body.style.overflow=''; }
  },
  closeAll() {
    document.querySelectorAll('.modal-overlay.open').forEach(m=>m.classList.remove('open'));
    document.body.style.overflow='';
  }
};
document.addEventListener('click', e => { if(e.target.classList.contains('modal-overlay')) Modal.closeAll(); });
document.addEventListener('keydown', e => { if(e.key==='Escape') Modal.closeAll(); });

// -----------------------------------------------
// SIDEBAR TOGGLE (animated, full-width main content)
// -----------------------------------------------
const Sidebar = {
  el: null,
  main: null,
  overlay: null,
  isOpen: true,
   
  init() {
    this.el   = document.getElementById('sidebar');
    this.main = document.getElementById('mainContent');
    if (!this.el) return;

    // Mobile overlay
    this.overlay = document.createElement('div');
    this.overlay.id = 'sidebarOverlay';
    this.overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:98;display:none;opacity:0;transition:opacity 0.3s ease;';
    document.body.appendChild(this.overlay);
    this.overlay.addEventListener('click', () => this.close());

    // Mobile: start closed
    if (window.innerWidth <= 768) {
      this.isOpen = false;
      document.body.classList.add('sidebar-hidden');
    }
  },

  open() {
    this.isOpen = true;
    document.body.classList.remove('sidebar-hidden');
    if (window.innerWidth <= 768) {
      this.overlay.style.display = 'block';
      setTimeout(() => this.overlay.style.opacity = '1', 10);
    }
  },

  close() {
    this.isOpen = false;
    document.body.classList.add('sidebar-hidden');
    if (window.innerWidth <= 768) {
      this.overlay.style.opacity = '0';
      setTimeout(() => { if (!this.isOpen) this.overlay.style.display = 'none'; }, 300);
    }
  },

  toggle() {
    this.isOpen ? this.close() : this.open();
  }
};

function toggleSidebar() { Sidebar.toggle(); }

// -----------------------------------------------
// AJAX Helper
// -----------------------------------------------
const Ajax = {
  async post(url, data) {
    try {
      const fd = new FormData();
      for (const [k,v] of Object.entries(data)) fd.append(k,v);
      const r = await fetch(url, { method:'POST', body:fd });
      return await r.json();
    } catch(e) { return { success:false, message:'Network error.' }; }
  },
  async get(url, params={}) {
    try {
      const q = new URLSearchParams(params).toString();
      const r = await fetch(q ? `${url}?${q}` : url);
      return await r.json();
    } catch(e) { return { success:false, message:'Network error.' }; }
  }
};

// -----------------------------------------------
// Form Validation
// -----------------------------------------------
const Validator = {
  validate(form) {
    let valid = true;
    form.querySelectorAll('[required]').forEach(f => {
      f.classList.remove('is-invalid');
      if (!f.value.trim()) { f.classList.add('is-invalid'); valid = false; }
    });
    return valid;
  }
};

// -----------------------------------------------
// Attendance System (AJAX)
// -----------------------------------------------
const Attendance = {
  data: {},
  init() {
    document.querySelectorAll('.att-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const sid = this.dataset.student, status = this.dataset.status;
        this.closest('.attendance-toggles').querySelectorAll('.att-btn').forEach(b=>b.classList.remove('active'));
        this.classList.add('active');
        Attendance.data[sid] = status;
        if (typeof updateSummary === 'function') updateSummary();
      });
    });
    document.querySelectorAll('.att-btn[data-status="P"]').forEach(btn => btn.click());
  },
  async submit(classId, date) {
    if (Object.keys(this.data).length === 0) { Toast.warning('No attendance data.'); return; }
    const btn = document.getElementById('submitAttendance');
    if (btn) { btn.disabled=true; btn.innerHTML='<span class="spinner" style="width:16px;height:16px;border-width:2px;display:inline-block;margin-right:6px;vertical-align:middle;"></span>Submitting...'; }
    const result = await Ajax.post('../api/attendance.php', { action:'submit', class_id:classId, date, records:JSON.stringify(this.data) });
    if (btn) { btn.disabled=false; btn.innerHTML='<i class="fas fa-check"></i> Submit Attendance'; }
    result.success ? Toast.success('Attendance submitted!') : Toast.error(result.message||'Failed.');
  }
};

// -----------------------------------------------
// Notifications Panel
// -----------------------------------------------
const Notifications = {
  panel: null,
  init() {
    this.panel = document.getElementById('notifPanel');
    document.querySelectorAll('.topbar-notification').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        this.toggle();
      });
    });
    document.addEventListener('click', () => this.close());
  },
  toggle() {
    if (!this.panel) return;
    const open = this.panel.classList.contains('notif-open');
    open ? this.close() : this.open();
  },
  open() {
    if (!this.panel) return;
    this.panel.classList.add('notif-open');
    // Remove dot
    document.querySelectorAll('.notif-dot').forEach(d=>d.style.display='none');
  },
  close() {
    if (this.panel) this.panel.classList.remove('notif-open');
  }
};

// -----------------------------------------------
// Image Lightbox (for notice images)
// -----------------------------------------------
const Lightbox = {
  el: null,
  init() {
    this.el = document.createElement('div');
    this.el.id = 'lightbox';
    this.el.style.cssText = `
      position:fixed;inset:0;background:rgba(0,0,0,0.92);z-index:2000;
      display:none;align-items:center;justify-content:center;
      flex-direction:column;padding:20px;cursor:zoom-out;
    `;
    this.el.innerHTML = `
      <button onclick="Lightbox.close()" style="position:absolute;top:16px;right:20px;background:rgba(255,255,255,0.15);border:none;color:#fff;width:40px;height:40px;border-radius:50%;font-size:1.2rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
      <img id="lightboxImg" src="" style="max-width:95vw;max-height:85vh;border-radius:8px;object-fit:contain;transition:transform 0.2s ease;cursor:zoom-in;" onclick="Lightbox.zoomToggle(this)">
      <p id="lightboxCaption" style="color:rgba(255,255,255,0.7);margin-top:12px;font-size:0.88rem;text-align:center;"></p>
    `;
    document.body.appendChild(this.el);
    this.el.addEventListener('click', e => { if(e.target===this.el) this.close(); });
    document.addEventListener('keydown', e => { if(e.key==='Escape') this.close(); });
  },
  open(src, caption='') {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxCaption').textContent = caption;
    this.el.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  },
  close() {
    this.el.style.display = 'none';
    document.body.style.overflow = '';
    document.getElementById('lightboxImg').style.transform = 'scale(1)';
  },
  zoomToggle(img) {
    const zoomed = img.style.transform === 'scale(2)';
    img.style.transform = zoomed ? 'scale(1)' : 'scale(2)';
    img.style.cursor = zoomed ? 'zoom-in' : 'zoom-out';
  }
};

// -----------------------------------------------
// Table Search
// -----------------------------------------------
function filterTable(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', function() {
    const term = this.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
  });
}

// -----------------------------------------------
// Clock
// -----------------------------------------------
function updateClock() {
  const el = document.getElementById('liveClock');
  if (el) el.textContent = new Date().toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' });
}

// -----------------------------------------------
// Print section
// -----------------------------------------------
function printSection(id) {
  const el = document.getElementById(id);
  if (!el) return;
  const w = window.open('', '_blank', 'width=800,height=600');
  w.document.write(`<!DOCTYPE html><html><head><title>Print</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:Nunito,sans-serif;padding:20px;} table{width:100%;border-collapse:collapse;} th,td{padding:10px;border:1px solid #ddd;font-size:13px;} th{background:#f0f4f8;font-weight:800;} @media print{button{display:none}}</style>
    </head><body>${el.innerHTML}<script>window.onload=()=>window.print();<\/script></body></html>`);
  w.document.close();
}

// -----------------------------------------------
// Canvas Charts
// -----------------------------------------------
function drawBarChart(canvasId, labels, values, color='#2563a8') {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const W = canvas.width, H = canvas.height;
  const max = Math.max(...values, 1);
  const barW = Math.max(20, (W-60)/labels.length - 10);
  ctx.clearRect(0,0,W,H);
  values.forEach((v,i) => {
    const x = 30 + i*(barW+10);
    const barH = ((v/max)*(H-50));
    const y = H-30-barH;
    ctx.fillStyle = color;
    ctx.beginPath();
    if (ctx.roundRect) ctx.roundRect(x,y,barW,barH,[4,4,0,0]);
    else ctx.rect(x,y,barW,barH);
    ctx.fill();
    ctx.fillStyle='#64748b'; ctx.font='10px Nunito'; ctx.textAlign='center';
    ctx.fillText(labels[i].substring(0,6), x+barW/2, H-10);
    ctx.fillStyle='#1e293b'; ctx.font='bold 10px Nunito';
    ctx.fillText(v, x+barW/2, y-5);
  });
}

function drawDonut(canvasId, values, colors, labels) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const cx=canvas.width/2, cy=canvas.height/2;
  const r=Math.min(cx,cy)-20;
  const total=values.reduce((a,b)=>a+b,0)||1;
  let start=-Math.PI/2;
  values.forEach((v,i) => {
    const sweep=(v/total)*2*Math.PI;
    ctx.beginPath(); ctx.moveTo(cx,cy);
    ctx.arc(cx,cy,r,start,start+sweep); ctx.closePath();
    ctx.fillStyle=colors[i]; ctx.fill();
    start+=sweep;
  });
  ctx.beginPath(); ctx.arc(cx,cy,r*0.55,0,2*Math.PI);
  ctx.fillStyle='#fff'; ctx.fill();
  ctx.fillStyle='#1e293b'; ctx.font='bold 14px Nunito';
  ctx.textAlign='center'; ctx.fillText(total,cx,cy+5);
}

// -----------------------------------------------
// Confirm delete helper
// -----------------------------------------------
function confirmDelete(url, msg='Are you sure?') {
  if (confirm(msg)) window.location.href = url;
}

// -----------------------------------------------
// BS Date Converter (Gregorian → Bikram Sambhat)
// -----------------------------------------------
const NepaliDate = {
  nepaliMonths: ['Baishakh','Jestha','Ashadh','Shrawan','Bhadra','Ashwin','Kartik','Mangsir','Poush','Magh','Falgun','Chaitra'],
  nepaliMonthsNP: ['बैशाख','जेठ','असाढ','श्रावण','भाद्र','आश्विन','कार्तिक','मंसिर','पौष','माघ','फाल्गुन','चैत्र'],
  // BS year data: [total days in year, days in each month]
  bsData: {
    2000:[365,30,32,31,32,31,30,30,30,29,30,29,31],
    2001:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2002:[365,31,31,32,32,31,30,30,29,30,29,30,30],
    2003:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2004:[365,30,32,31,32,31,30,30,30,29,30,29,31],
    2005:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2006:[365,31,31,32,32,31,30,30,29,30,29,30,30],
    2007:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2008:[365,31,31,31,32,31,31,29,30,30,29,29,31],
    2009:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2010:[365,31,31,32,32,31,30,30,29,30,29,30,30],
    2011:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2012:[365,31,31,31,32,31,31,29,30,30,29,30,30],
    2013:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2014:[365,31,31,32,32,31,30,30,29,30,29,30,30],
    2015:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2016:[365,31,31,31,32,31,31,29,30,30,29,30,30],
    2017:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2018:[365,31,32,31,32,31,30,30,29,30,29,30,30],
    2019:[366,31,32,31,32,31,30,30,30,29,30,29,31],
    2020:[365,31,31,31,32,31,31,30,29,30,29,30,30],
    2021:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2022:[365,31,32,31,32,31,30,30,30,29,29,30,30],
    2023:[366,31,32,31,32,31,30,30,30,29,30,29,31],
    2024:[365,31,31,31,32,31,31,30,29,30,29,30,30],
    2025:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2026:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2027:[365,30,32,31,32,31,30,30,30,29,30,29,31],
    2028:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2029:[365,31,31,32,31,32,30,30,29,30,29,30,30],
    2030:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2031:[365,30,32,31,32,31,30,30,30,29,30,29,31],
    2032:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2033:[365,31,31,32,31,32,30,30,29,30,29,30,30],
    2034:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2035:[365,30,32,31,32,31,31,29,30,30,29,29,31],
    2036:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2037:[365,31,31,32,32,31,30,30,29,30,29,30,30],
    2038:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2039:[365,31,31,31,32,31,31,29,30,30,29,30,30],
    2040:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2041:[365,31,31,32,32,31,30,30,29,30,29,30,30],
    2042:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2043:[365,31,31,31,32,31,31,29,30,30,29,30,30],
    2044:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2045:[365,31,32,31,32,31,30,30,29,30,29,30,30],
    2046:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2047:[365,31,31,31,32,31,31,30,29,30,29,30,30],
    2048:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2049:[365,31,32,31,32,31,30,30,30,29,29,30,30],
    2050:[366,31,32,31,32,31,30,30,30,29,30,29,31],
    2051:[365,31,31,31,32,31,31,30,29,30,29,30,30],
    2052:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2053:[365,31,32,31,32,31,30,30,30,29,29,30,30],
    2054:[366,31,32,31,32,31,30,30,30,29,30,29,31],
    2055:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2056:[366,31,31,32,31,32,30,30,29,30,29,30,30],
    2057:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2058:[365,30,32,31,32,31,30,30,30,29,30,29,31],
    2059:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2060:[365,31,31,32,32,31,30,30,29,30,29,30,30],
    2061:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2062:[365,30,32,31,32,31,31,29,30,29,30,29,31],
    2063:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2064:[365,31,31,32,32,31,30,30,29,30,29,30,30],
    2065:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2066:[365,31,31,31,32,31,31,29,30,30,29,29,31],
    2067:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2068:[365,31,31,32,32,31,30,30,29,30,29,30,30],
    2069:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2070:[365,31,31,31,32,31,31,29,30,30,29,30,30],
    2071:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2072:[365,31,32,31,32,31,30,30,29,30,29,30,30],
    2073:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2074:[365,31,31,31,32,31,31,30,29,30,29,30,30],
    2075:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2076:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2077:[366,30,32,31,32,31,30,30,30,29,30,29,31],
    2078:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2079:[365,31,31,32,31,32,30,30,29,30,29,30,30],
    2080:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2081:[365,31,31,31,32,31,31,29,30,30,29,30,30],
    2082:[365,31,31,32,31,31,31,30,29,30,29,30,30],
    2083:[366,31,32,31,32,31,30,30,30,29,29,30,31],
    2084:[365,31,31,31,32,31,31,29,30,30,29,30,30],
    2085:[365,31,31,32,31,31,31,30,29,30,29,30,30],
  },

  toBS(gy, gm, gd) {
    // Reference: 2000 BS = 1943 AD April 14
    const refGY=1943, refGM=4, refGD=14;
    const refBSY=2000, refBSM=1, refBSD=1;
    // Count total AD days from reference
    function adDays(y,m,d) {
      let days=0;
      for(let i=refGY;i<y;i++) days+=((i%4===0&&i%100!==0)||i%400===0)?366:365;
      const mdays=[0,31,28,31,30,31,30,31,31,30,31,30,31];
      const leap=((y%4===0&&y%100!==0)||y%400===0);
      if(leap) mdays[2]=29;
      for(let i=refGM;i<m;i++) days+=mdays[i];
      days+=(d-refGD);
      return days;
    }
    let totalDays = adDays(gy,gm,gd);
    let bsY=refBSY, bsM=refBSM, bsD=refBSD;
    while(totalDays>0){
      const data=this.bsData[bsY];
      if(!data) break;
      if(totalDays>=data[bsM]){
        totalDays-=data[bsM]; bsM++;
        if(bsM>12){bsM=1;bsY++;}
      } else { bsD+=totalDays; totalDays=0; }
    }
    return {y:bsY, m:bsM, d:bsD};
  },

  format(dateStr, lang='en') {
    if (!dateStr || dateStr==='–' || dateStr==='0000-00-00') return '–';
    const parts = dateStr.split(/[-T ]/);
    if (parts.length < 3) return dateStr;
    const bs = this.toBS(parseInt(parts[0]), parseInt(parts[1]), parseInt(parts[2]));
    const months = lang==='np' ? this.nepaliMonthsNP : this.nepaliMonths;
    return `${bs.y} ${months[bs.m-1]} ${bs.d}`;
  }
};

// Convert all elements with data-bs-date attribute
function convertAllDates() {
  document.querySelectorAll('[data-bs-date]').forEach(el => {
    const raw = el.dataset.bsDate;
    if (raw) el.textContent = NepaliDate.format(raw);
  });
}

// -----------------------------------------------
// Initialize
// -----------------------------------------------
document.addEventListener('DOMContentLoaded', function() {
  Toast.init();
  Sidebar.init();
  Notifications.init();
  Lightbox.init();

  if (document.querySelector('.att-btn')) Attendance.init();

  filterTable('searchInput','dataTable');
  updateClock();
  setInterval(updateClock, 60000);

  // Auto dismiss alerts
  document.querySelectorAll('.alert').forEach(a => {
    setTimeout(() => {
      a.style.transition='all 0.4s ease';
      a.style.opacity='0'; a.style.transform='translateY(-10px)';
      setTimeout(()=>a.remove(),400);
    }, 4000);
  });

  // Convert all BS dates
  convertAllDates();
});

// ================================================================
// AJAX NAVIGATION + FORM SUBMISSION (no full page reloads)
// ================================================================
(function () {
    'use strict';

    // ── Config ──────────────────────────────────────────────────
    // Forms with these actions always do a real submit (file uploads, login, logout, print)
    var FULL_SUBMIT_ACTIONS = ['upload_gallery', 'upload_photo', 'import'];
    // Pages that always hard-navigate (login, logout)
    var HARD_NAV_PAGES = ['login.php', 'logout.php'];

    // ── Spinner bar ─────────────────────────────────────────────
    var bar = document.createElement('div');
    bar.id  = 'ajaxBar';
    bar.style.cssText = 'position:fixed;top:0;left:0;height:3px;width:0;background:var(--primary,#2563a8);z-index:99999;transition:width .25s ease,opacity .3s ease;pointer-events:none;';
    document.body.appendChild(bar);

    // URLs are kept exactly as-is (previously .php was stripped)
    function cleanUrl(url) {
        return url;   // keep .php in the address bar (host has no clean-URL rewrite)
    }

    var barTimer;
    function barStart() {
        clearTimeout(barTimer);
        bar.style.opacity = '1';
        bar.style.transition = 'width .25s ease';
        bar.style.width = '60%';
    }
    function barDone() {
        bar.style.width = '100%';
        barTimer = setTimeout(function () {
            bar.style.opacity = '0';
            setTimeout(function () { bar.style.width = '0'; bar.style.opacity = '1'; }, 300);
        }, 150);
    }

    // ── Scroll helpers — page scrolls via window, not mainContent ──
    function getScrollY()    { return window.scrollY || document.documentElement.scrollTop; }
    function setScrollY(y)   { window.scrollTo(0, y || 0); }
    function getMC()         { return document.getElementById('mainContent'); }

    // ── Load a URL and swap .page-content ───────────────────────
    var isLoading = false;
    function loadPage(url, pushState, scrollTop) {
        if (isLoading) return;
        // Hard-navigate for login/logout pages
        for (var i = 0; i < HARD_NAV_PAGES.length; i++) {
            if (url.indexOf(HARD_NAV_PAGES[i]) !== -1) { window.location.href = url; return; }
        }
        isLoading = true;
        barStart();

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, redirect: 'follow' })
        .then(function (res) {
            // If redirected to login, hard navigate
            if (res.url && res.url.indexOf('login.php') !== -1) { window.location.href = res.url; return null; }
            return res.text();
        })
        .then(function (html) {
            if (!html) return;
            var parser     = new DOMParser();
            var doc        = parser.parseFromString(html, 'text/html');
            var newContent = doc.querySelector('.page-content');
            var mc         = getMC();
            if (!newContent || !mc) { window.location.href = url; return; }

            var pageContent = mc.querySelector('.page-content');
            if (pageContent) pageContent.innerHTML = newContent.innerHTML;

            // Update topbar title / breadcrumb
            var nt = doc.querySelector('.topbar-title h2');
            var nb = doc.querySelector('.topbar-title .breadcrumb, .topbar-title .breadcrumb-text');
            var ct = mc.querySelector('.topbar-title h2');
            var cb = mc.querySelector('.topbar-title .breadcrumb, .topbar-title .breadcrumb-text');
            if (nt && ct) ct.textContent = nt.textContent;
            if (nb && cb) cb.textContent = nb.textContent;

            // Update document title
            if (doc.title) document.title = doc.title;

            // Update active nav item
            var sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.querySelectorAll('.nav-item').forEach(function (a) { a.classList.remove('active'); });
                var filename = url.split('/').pop().split('?')[0].split('#')[0].replace('.php','');
                var active   = sidebar.querySelector('a.nav-item[href="' + filename + '"]');
                if (active) active.classList.add('active');
            }

            // Push history
            if (pushState !== false) var cleanedUrl = cleanUrl(url); history.pushState({ url: cleanedUrl }, '', cleanedUrl);

            // Scroll
            setScrollY(scrollTop !== undefined ? scrollTop : 0);

            // Show any flash as toast
            var flashEl = pageContent ? pageContent.querySelector('.alert') : null;
            if (flashEl) {
                var type = flashEl.classList.contains('alert-success') ? 'success'
                         : flashEl.classList.contains('alert-danger')  ? 'error'
                         : flashEl.classList.contains('alert-warning') ? 'warning' : 'info';
                var msg = flashEl.textContent.trim().replace(/\s+/g, ' ');
                Toast.show(msg, type, 4000);
                flashEl.remove();
            }

            // Re-init JS on new content
            reInit(pageContent || mc);
            barDone();
            isLoading = false;
        })
        .catch(function () {
            window.location.href = url;
            isLoading = false;
        });
    }

    // ── Re-init all JS features after content swap ───────────────
    function reInit(container) {
        if (!container) return;
        // Always ensure body scroll is restored after AJAX swap
        document.body.style.overflow = '';

        // Auto-dismiss remaining alerts
        container.querySelectorAll('.alert').forEach(function (a) {
            setTimeout(function () {
                a.style.transition = 'all .4s ease';
                a.style.opacity = '0';
                a.style.transform = 'translateY(-10px)';
                setTimeout(function () { a.remove(); }, 400);
            }, 4000);
        });

        // Table search
        filterTable('searchInput', 'dataTable');

        // BS dates
        convertAllDates();

        // Attendance buttons
        if (container.querySelector('.att-btn')) Attendance.init();

        // Student filter widgets
        container.querySelectorAll('[id$="_faculty"]').forEach(function (el) {
            var uid = el.id.replace('_faculty', '');
            if (typeof window['sfw_' + uid + '_onFaculty'] === 'function') {
                window['sfw_' + uid + '_onFaculty']();
            }
        });

        // Re-execute inline <script> tags
        container.querySelectorAll('script').forEach(function (old) {
            var s = document.createElement('script');
            Array.from(old.attributes).forEach(function (a) { s.setAttribute(a.name, a.value); });
            s.textContent = old.textContent;
            old.parentNode.replaceChild(s, old);
        });

        // Re-init charts if dashboard
        if (typeof initCharts === 'function') setTimeout(initCharts, 60);
    }

    // ── Intercept form submits ───────────────────────────────────
    document.addEventListener('submit', function (e) {
        var form   = e.target;
        var action = (form.getAttribute('action') || window.location.href);
        var method = (form.getAttribute('method') || 'GET').toUpperCase();

        // Skip: GET forms, file upload forms, modal-less full-page forms
        if (method === 'GET') return;

        // Skip forms that contain file inputs with a selected file
        var fileInputs = form.querySelectorAll('input[type="file"]');
        for (var i = 0; i < fileInputs.length; i++) {
            if (fileInputs[i].files && fileInputs[i].files.length > 0) return;
        }

        // Skip forms with data-full-submit attribute
        if (form.hasAttribute('data-full-submit')) return;

        // Skip hard-nav actions
        var actionVal = (form.querySelector('[name="action"]') || {}).value || '';
        for (var j = 0; j < FULL_SUBMIT_ACTIONS.length; j++) {
            if (actionVal === FULL_SUBMIT_ACTIONS[j]) return;
        }

        e.preventDefault();

        var mc = getMC();
        var scrollY = getScrollY();

        barStart();

        var fd  = new FormData(form);
        var url = action.indexOf('://') !== -1 ? action : (window.location.pathname.replace(/[^/]+$/, '') + action);

        fetch(url, {
            method:   'POST',
            body:     fd,
            headers:  { 'X-Requested-With': 'XMLHttpRequest' },
            redirect: 'follow'
        })
        .then(function (res) {
            // After POST, PHP does redirect() → fetch follows it → we get the GET page
            if (res.url && res.url.indexOf('login.php') !== -1) { window.location.href = res.url; return null; }
            var finalUrl = res.url || url;
            return res.text().then(function (html) { return { html: html, url: finalUrl }; });
        })
        .then(function (result) {
            if (!result) return;
            var parser     = new DOMParser();
            var doc        = parser.parseFromString(result.html, 'text/html');
            var newContent = doc.querySelector('.page-content');
            var mc2        = getMC();
            if (!newContent || !mc2) { window.location.href = result.url; return; }

            var pageContent = mc2.querySelector('.page-content');
            if (pageContent) pageContent.innerHTML = newContent.innerHTML;

            // Update title / topbar
            if (doc.title) document.title = doc.title;
            var nt = doc.querySelector('.topbar-title h2');
            var ct = mc2.querySelector('.topbar-title h2');
            if (nt && ct) ct.textContent = nt.textContent;

            // Update sidebar active
            var sidebar = document.getElementById('sidebar');
            if (sidebar) {
                var filename = result.url.split('/').pop().split('?')[0].replace('.php','');
                sidebar.querySelectorAll('.nav-item').forEach(function (a) { a.classList.remove('active'); });
                var al = sidebar.querySelector('a.nav-item[href="' + filename + '"]');
                if (al) al.classList.add('active');
            }

            // Push history with new URL (the redirect target)
            var cleanedResultUrl = cleanUrl(result.url); history.pushState({ url: cleanedResultUrl }, '', cleanedResultUrl);

            // Restore scroll
            setScrollY(scrollY);

            // Flash → Toast
            var flashEl = pageContent ? pageContent.querySelector('.alert') : null;
            if (flashEl) {
                var type = flashEl.classList.contains('alert-success') ? 'success'
                         : flashEl.classList.contains('alert-danger')  ? 'error'
                         : flashEl.classList.contains('alert-warning') ? 'warning' : 'info';
                Toast.show(flashEl.textContent.trim().replace(/\s+/g, ' '), type, 4000);
                flashEl.remove();
            }

            reInit(pageContent || mc2);
            barDone();
        })
        .catch(function () {
            form.submit(); // fallback: real submit
        });
    }, true);

    // ── Sidebar nav link clicks ──────────────────────────────────
    document.addEventListener('click', function (e) {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        var link = e.target.closest('a.nav-item[href]');
        if (!link || !sidebar.contains(link)) return;
        var href = link.getAttribute('href');
        if (!href || href.startsWith('http') || href.startsWith('//') || href.indexOf('logout') !== -1) return;
        e.preventDefault();
        loadPage(href);
    });

    // ── Back / Forward ────────────────────────────────────────────
    window.addEventListener('popstate', function (e) {
        var url = cleanUrl((e.state && e.state.url) ? e.state.url : location.href);
        loadPage(url, false);
    });
    history.replaceState({ url: cleanUrl(location.href) }, '', cleanUrl(location.href));

})();