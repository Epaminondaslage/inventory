<?php
/**
 * index.php — Inventário de Servidores v5
 * Projeto: Sentinela — Sítio Pé de Serra
 * Design: SPS Tipo 2 — header fixo + abas por servidor
 * Interior: cards ricos com barras, grid de containers, portas coloridas
 * Compatível com PHP >= 7.0
 */

$SERVICE_NAMES = [
    22=>'SSH', 25=>'SMTP', 53=>'DNS', 80=>'HTTP/Nginx/Apache',
    111=>'RPC', 139=>'NetBIOS', 443=>'HTTPS', 445=>'SMB',
    631=>'CUPS', 873=>'rsync', 1883=>'MQTT Broker', 2375=>'Docker API',
    2947=>'gpsd', 3000=>'Grafana/App', 3001=>'Open WebUI', 3002=>'Conectados',
    3003=>'Rep nodejs', 3010=>'Weather API', 3011=>'Sentinela Dashboard',
    3020=>'Sentinela Devices', 3025=>'IA Monitor', 3030=>'Tasmota Monitor',
    3100=>'MCP Server', 3306=>'MySQL', 3350=>'xrdp', 3389=>'RDP',
    4000=>'MQTT Hub/IM Backend', 5005=>'DocAI v3', 5006=>'DocAI v4 RAG',
    5007=>'DocAI Tool', 5050=>'Sentinela Core', 5432=>'PostgreSQL',
    5678=>'n8n', 6379=>'Redis', 8080=>'phpMyAdmin/HTTP Alt',
    8086=>'InfluxDB', 8088=>'Portal Sentinela', 8090=>'Inventory Agent',
    8091=>'Swagger/Alarme PDS', 8092=>'Critical Events', 8093=>'Swagger UI',
    8123=>'Home Assistant', 8877=>'Tuya MQTT Bridge', 9000=>'Portainer',
    9001=>'MQTT WebSocket', 9020=>'App', 11434=>'Ollama LLM',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Inventário de Servidores — Sítio Pé de Serra</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --teal:#009688;--teal-lt:#e0f2f1;--teal-dk:#00796b;
  --bg:#f0f0f0;--card:#fff;--bd:#e0e0e0;
  --text:#212121;--muted:#757575;--r:10px;
  --green:#43a047;--red:#e53935;--orange:#fb8c00;--blue:#1976d2;
}
html,body{height:100%;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:14px;background:var(--bg);color:var(--text)}
body{display:flex;flex-direction:column;min-height:100vh}

/* ── HEADER ── */
.hdr{background:var(--card);border-bottom:1px solid var(--bd);padding:0 20px;height:56px;display:flex;align-items:center;gap:12px;flex-shrink:0}
.hdr-logo{width:36px;height:36px;border-radius:8px;object-fit:contain}
.hdr-info{flex:1;min-width:0}
.hdr-title{font-size:15px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hdr-sub{font-size:12px;color:var(--muted);display:flex;gap:10px;margin-top:1px}
.hdr-actions{display:flex;align-items:center;gap:8px;flex-shrink:0}
.btn-teal{height:34px;padding:0 14px;border-radius:8px;border:none;background:var(--teal);color:#fff;font-size:13px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:6px;transition:background .15s;white-space:nowrap}
.btn-teal:hover{background:var(--teal-dk)}
.btn-teal:disabled{opacity:.6;cursor:not-allowed}
.btn-out{height:34px;padding:0 14px;border-radius:8px;border:1px solid var(--bd);background:var(--card);color:var(--text);font-size:13px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:6px;white-space:nowrap}
.btn-out:hover{background:var(--bg)}
.ref-sel{height:34px;padding:0 10px;border-radius:8px;border:1px solid var(--bd);background:var(--card);font-size:13px;cursor:pointer}
.countdown{font-size:12px;color:var(--muted);min-width:55px;text-align:right}

/* ── MAIN ── */
.main{flex:1;max-width:1400px;width:100%;margin:0 auto;padding:16px;display:flex;flex-direction:column;gap:16px}

/* ── RESUMO ── */
.summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px}
.sum-card{background:var(--card);border:1px solid var(--bd);border-radius:var(--r);padding:12px 16px;display:flex;align-items:center;gap:10px}
.sum-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:var(--teal-lt)}
.sum-ico svg{stroke:var(--teal-dk)}
.sum-val{font-size:20px;font-weight:700;color:var(--teal);line-height:1}
.sum-lbl{font-size:11px;color:var(--muted);margin-top:2px}

/* ── SERVER TABS ── */
.srv-wrap{background:var(--card);border:1px solid var(--bd);border-radius:var(--r);overflow:hidden}
.srv-tabs{display:flex;overflow-x:auto;border-bottom:1px solid var(--bd)}
.srv-tabs::-webkit-scrollbar{height:3px}
.srv-tabs::-webkit-scrollbar-thumb{background:#ccc;border-radius:4px}
.srv-tab{padding:13px 20px;font-size:14px;font-weight:500;color:var(--muted);cursor:pointer;border:none;background:none;border-bottom:3px solid transparent;white-space:nowrap;display:flex;align-items:center;gap:8px;transition:color .15s}
.srv-tab:hover{color:var(--teal);background:var(--bg)}
.srv-tab.active{color:var(--teal);border-bottom-color:var(--teal)}
.srv-tab .sdot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.srv-tab .sdot.on{background:var(--green)}
.srv-tab .sdot.off{background:var(--red)}

.srv-panel{display:none;padding:20px}
.srv-panel.active{display:block}

/* ── PANEL HEADER ── */
.panel-hdr{margin-bottom:20px}
.panel-hdr h2{font-size:20px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:10px}
.panel-hdr h2 .ip-badge{font-size:12px;font-weight:600;background:var(--teal-lt);color:var(--teal-dk);padding:3px 10px;border-radius:999px}
.panel-hdr .sub{font-size:13px;color:var(--muted);margin-top:4px;display:flex;align-items:center;gap:8px}
.status-pill{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;padding:3px 10px;border-radius:999px}
.status-pill.on{background:#e8f5e9;color:#2e7d32}
.status-pill.off{background:#ffebee;color:#c62828}
.status-pill .dot{width:7px;height:7px;border-radius:50%}
.status-pill.on .dot{background:var(--green)}
.status-pill.off .dot{background:var(--red)}

/* ── SECTION TITLE ── */
.sec{margin-bottom:16px}
.sec-title{font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.sec-title svg{color:var(--teal)}

/* ── CARD GRID ── */
.card-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-bottom:20px}
.card-grid.wide{grid-template-columns:repeat(auto-fill,minmax(300px,1fr))}

/* Info card */
.ic{background:var(--bg);border:1px solid var(--bd);border-radius:var(--r);padding:14px 16px}
.ic-head{display:flex;align-items:center;gap:8px;margin-bottom:10px}
.ic-head svg{color:var(--text)}
.ic-head .ic-title{font-size:14px;font-weight:600;color:var(--text)}
.ic-row{display:flex;justify-content:space-between;align-items:center;padding:3px 0;font-size:13px;border-bottom:1px solid var(--bd)}
.ic-row:last-child{border-bottom:none}
.ic-row .lbl{color:var(--muted)}
.ic-row .val{font-weight:500;text-align:right}

/* Progress bar */
.progress-wrap{margin-top:8px}
.progress-bar{height:6px;background:#e0e0e0;border-radius:999px;overflow:hidden;margin-top:4px}
.progress-fill{height:100%;border-radius:999px;background:var(--green);transition:width .5s}
.progress-fill.warn{background:var(--orange)}
.progress-fill.danger{background:var(--red)}
.progress-lbl{font-size:11px;color:var(--muted);margin-top:3px}

/* Load avg */
.load-val{font-size:22px;font-weight:700;color:var(--text);margin:6px 0}
.load-sub{font-size:11px;color:var(--muted)}

/* Container cards grid */
.ctr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px;margin-bottom:20px}
.ctr-card{background:var(--bg);border:1px solid var(--bd);}
.ctr-card.stopped{background:#fff5f5;border-color:#ffcdd2;border-radius:var(--r);padding:12px 14px}
.ctr-head{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.ctr-head svg{color:var(--muted);flex-shrink:0}
.ctr-name{font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ctr-status{font-size:12px;font-weight:600;display:flex;align-items:center;gap:4px;margin-bottom:4px}
.ctr-status .dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.ctr-status.up .dot{background:var(--green)}
.ctr-status.up{color:var(--green)}
.ctr-status.stopped .dot{background:var(--red)}
.ctr-status.stopped{color:var(--red)}
.ctr-img{font-size:11px;color:var(--muted);font-family:monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ctr-ports{margin-top:6px;display:flex;flex-wrap:wrap;gap:4px}
.ctr-port{font-size:11px;background:var(--teal-lt);color:var(--teal-dk);border-radius:4px;padding:1px 6px;font-family:monospace}

/* Port cards */
.port-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:20px}
.port-card{border-radius:var(--r);padding:14px 16px;border:1px solid transparent}
.port-card.container{background:#f0fdf4;border-color:#bbf7d0}
.port-card.local{background:#fffbeb;border-color:#fde68a}
.port-card .pc-head{display:flex;align-items:center;gap:6px;margin-bottom:6px}
.port-card .pc-head svg{color:var(--muted)}
.port-card .pc-name{font-size:12px;font-weight:500;color:var(--text)}
.port-card .pc-num{font-size:22px;font-weight:700;color:var(--text)}
.port-card .pc-type{font-size:10px;font-weight:700;letter-spacing:.06em;margin-top:3px}
.port-card.container .pc-type{color:var(--green)}
.port-card.local .pc-type{color:var(--orange)}

/* Badge pills */
.pill{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:3px 9px;border-radius:999px}
.pill-green{background:#e8f5e9;color:#2e7d32}
.pill-red{background:#ffebee;color:#c62828}
.pill-gray{background:#f5f5f5;color:#555;border:1px solid var(--bd)}
.pill-teal{background:var(--teal-lt);color:var(--teal-dk)}
.pill-orange{background:#fff3e0;color:#e65100}

/* disk */
.disk-item{margin-bottom:10px}
.disk-item:last-child{margin-bottom:0}
.disk-mount{font-size:13px;font-weight:600}
.disk-detail{font-size:12px;color:var(--muted);display:flex;justify-content:space-between;margin-top:2px}

/* net ifaces */
.iface-item{background:#f8f8f8;border-radius:6px;padding:7px 10px;font-family:monospace;font-size:12px;margin-bottom:6px;border:1px solid var(--bd)}
.iface-item:last-child{margin-bottom:0}
.iface-name{font-weight:700;color:var(--text)}
.iface-ip{color:var(--muted)}

/* no data */
.no-data{color:var(--muted);font-style:italic;font-size:13px;padding:8px 0}

/* toast */
#toast{position:fixed;bottom:20px;right:20px;padding:11px 16px;border-radius:var(--r);font-size:13px;font-weight:500;color:#fff;background:var(--text);box-shadow:0 4px 16px rgba(0,0,0,.18);z-index:999;opacity:0;transform:translateY(8px);transition:opacity .25s,transform .25s;pointer-events:none;max-width:300px}
#toast.show{opacity:1;transform:translateY(0)}
#toast.ok{background:var(--green)}
#toast.err{background:var(--red)}
#toast.inf{background:var(--teal)}

.loading{padding:48px;text-align:center;color:var(--muted)}
@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:700px){.hdr-sub{display:none}.summary{grid-template-columns:repeat(2,1fr)}.card-grid,.ctr-grid,.port-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<header class="hdr">
  <img src="img/logo_inventory.jpg" alt="Logo" class="hdr-logo">
  <div class="hdr-info">
    <div class="hdr-title">Inventário de Servidores — Sítio Pé de Serra</div>
    <div class="hdr-sub">
      <span>Sentinela</span>
      <span id="last-update">· Aguardando...</span>
    </div>
  </div>
  <div class="hdr-actions">
    <span class="countdown" id="countdown"></span>
    <select class="ref-sel" id="refSel" onchange="setRefresh(this.value)">
      <option value="0">Manual</option>
      <option value="30" selected>30s</option>
      <option value="60">1 min</option>
      <option value="120">2 min</option>
      <option value="300">5 min</option>
    </select>
    <button class="btn-teal" id="btnRef" onclick="load()">
      <svg id="ref-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
      Atualizar
    </button>
    <button class="btn-out" onclick="goBack()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Voltar
    </button>
  </div>
</header>

<div class="main">
  <div class="summary">
    <div class="sum-card"><div class="sum-ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></div><div><div class="sum-val" id="s-total">—</div><div class="sum-lbl">Servidores</div></div></div>
    <div class="sum-card"><div class="sum-ico" style="background:#e8f5e9"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2e7d32" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div><div><div class="sum-val" id="s-on" style="color:var(--green)">—</div><div class="sum-lbl">Online</div></div></div>
    <div class="sum-card"><div class="sum-ico" style="background:#ffebee"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#c62828" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div><div><div class="sum-val" id="s-off" style="color:var(--red)">—</div><div class="sum-lbl">Offline</div></div></div>
    <div class="sum-card"><div class="sum-ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg></div><div><div class="sum-val" id="s-svcs">—</div><div class="sum-lbl">Serviços</div></div></div>
    <div class="sum-card"><div class="sum-ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div><div><div class="sum-val" id="s-dock">—</div><div class="sum-lbl">Containers</div></div></div>
  </div>

  <div class="srv-wrap" id="container">
    <div class="loading">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#009688" stroke-width="2" style="animation:spin 1s linear infinite;display:block;margin:0 auto 12px"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
      Carregando inventário...
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
var SVC = <?= json_encode($SERVICE_NAMES) ?>;
var refInt=null,cdInt=null,refSecs=30,cdLeft=30;

function goBack(){document.referrer?history.back():(window.location.href='/');}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function fmt_uptime(s){
  if(!s)return '—';
  var d=Math.floor(s/86400),h=Math.floor((s%86400)/3600),m=Math.floor((s%3600)/60);
  return d>0?d+'d '+h+'h '+m+'m':h+'h '+m+'m';
}
function fmt_time(str){
  if(!str)return '—';
  var d=new Date(str.replace(' ','T'));
  return d.getHours().toString().padStart(2,'0')+':'+d.getMinutes().toString().padStart(2,'0')+':'+d.getSeconds().toString().padStart(2,'0');
}
function pct_class(p){return p>85?'danger':p>60?'warn':'';}

// ── Parse helpers ──
function parse_mem_pct(used,total){
  var units={'Ki':1/1048576,'Mi':1/1024,'Gi':1,'Ti':1024};
  function to_gi(s){
    s=String(s||'');
    for(var u in units){if(s.endsWith(u))return parseFloat(s)*units[u];}
    return parseFloat(s)||0;
  }
  var u=to_gi(used),t=to_gi(total);
  return t>0?Math.round(u/t*100):0;
}

function parse_load(cpu_info){
  // tenta extrair do raw.cpu_info se não disponível
  return null;
}

function parse_disks(disk_str){
  if(!disk_str||disk_str==='unavailable')return[];
  var lines=disk_str.split('\n').filter(function(l){return l.trim()&&!l.startsWith('NAME');});
  var disks=[];
  lines.forEach(function(l){
    var p=l.trim().split(/\s+/);
    if(p.length>=3&&p[2]==='part'&&p[3]&&p[3]!==''&&!p[3].includes('host')&&!p[3].includes('etc')){
      disks.push({name:p[0],size:p[1],mount:p[3]});
    }
  });
  return disks;
}

function parse_network(net_str){
  if(!net_str||net_str==='unavailable')return[];
  var ifaces=[];
  net_str.split('\n').forEach(function(l){
    l=l.trim();
    if(!l)return;
    var p=l.split(/\s+/);
    if(p.length>=3){
      var name=p[0].replace(/@.*/,'');
      var ip=p[2]||'';
      ifaces.push({name:name,status:p[1],ip:ip});
    }
  });
  return ifaces;
}

function is_relevant(port,bind){
  if(port>32767)return false;
  if(port===53&&bind&&bind.startsWith('127.'))return false;
  return true;
}

function docker_icon(name){
  var icons={
    mysql:'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>',
    nginx:'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
    mqtt:'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
    default:'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>'
  };
  for(var k in icons){if(name.toLowerCase().includes(k))return icons[k];}
  return icons.default;
}

// ── Render panel ──
function renderPanel(srv){
  var snap=srv.snapshot||{};
  var raw=srv.raw||{};
  var svcs=(srv.services||[]).filter(function(s){return is_relevant(s.port,s.bind_addr);});
  var docker=srv.docker||[];

  // Calcula mem %
  var memPct=parse_mem_pct(snap.mem_used,snap.mem_total);
  var memClass=pct_class(memPct);

  // Discos
  var disks=parse_disks(raw.disks);

  // Rede
  var ifaces=parse_network(raw.network).filter(function(i){
    return !i.name.includes('docker')&&!i.name.includes('br-')&&!i.name.includes('veth')&&i.name!=='lo';
  });

  // Load avg do cpu_info
  var loadAvg='—';
  if(raw.cpu_info){
    var lm=raw.cpu_info.match(/BogoMIPS:\s+([\d.]+)/);
  }

  // Kernel
  var kernel='—';
  if(raw.cpu_info){var km=raw.cpu_info.match(/Architecture:\s+\S+\n[\s\S]*?Stepping:\s+\S+/);} 
  // pega do os_release
  var osStr=snap.os_name||'—';

  var html='';

  // Panel header
  html+='<div class="panel-hdr">'
    +'<h2>'
      +'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>'
      +esc(srv.label||srv.hostname)
      +'<span class="ip-badge">'+esc(srv.ip)+'</span>'
    +'</h2>'
    +'<div class="sub">'
      +'<span class="status-pill '+(srv.online?'on':'off')+'"><span class="dot"></span>'+(srv.online?'Online':'Offline')+'</span>'
      +(snap.collected_at?'<span style="color:var(--muted);font-size:12px">Snapshot: '+fmt_time(snap.collected_at)+'</span>':'')
      +(snap.agent_version?'<span class="pill pill-teal">v'+esc(snap.agent_version)+'</span>':'')
      +(snap.exec_ms?'<span class="pill pill-gray">'+snap.exec_ms+'ms</span>':'')
    +'</div>'
  +'</div>';

  if(!srv.online){
    html+='<div class="no-data">Servidor offline — sem dados disponíveis.</div>';
    return html;
  }

  // ── Row 1: Sistema, CPU, Memória, Load ──
  html+='<div class="card-grid">';

  // Sistema
  html+='<div class="ic">'
    +'<div class="ic-head"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg><span class="ic-title">Sistema</span></div>'
    +'<div class="ic-row"><span class="lbl">OS</span><span class="val">'+esc(osStr)+'</span></div>'
    +(snap.arch?'<div class="ic-row"><span class="lbl">Arquitetura</span><span class="val">'+esc(snap.arch)+'</span></div>':'')
    +(raw.cpu_info&&raw.cpu_info.match(/Kernel Version:\s+(.+)/)?'<div class="ic-row"><span class="lbl">Kernel</span><span class="val">'+esc(raw.cpu_info.match(/Kernel Version:\s+(.+)/)[1].trim())+'</span></div>':'')
    +'<div class="ic-row"><span class="lbl">Uptime</span><span class="val">'+esc(fmt_uptime(snap.uptime_sec))+'</span></div>'
  +'</div>';

  // CPU
  var cpuModel='—';
  if(raw.cpu_info){var mm=raw.cpu_info.match(/Model name:\s+(.+)/g);if(mm)cpuModel=[...new Set(mm.map(function(m){return m.replace('Model name:','').trim();}))].join(', ');}
  html+='<div class="ic">'
    +'<div class="ic-head"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M1 9h3M1 15h3M20 9h3M20 15h3"/></svg><span class="ic-title">CPU</span></div>'
    +(cpuModel!=='—'?'<div class="ic-row"><span class="lbl">Modelo</span><span class="val" style="font-size:12px">'+esc(cpuModel)+'</span></div>':'')
    +'<div class="ic-row"><span class="lbl">Núcleos</span><span class="val">'+(snap.cpu_cores||'—')+'</span></div>'
    +(raw.cpu_info&&raw.cpu_info.match(/CPU max MHz:\s+([\d.]+)/)?'<div class="ic-row"><span class="lbl">Max MHz</span><span class="val">'+parseFloat(raw.cpu_info.match(/CPU max MHz:\s+([\d.]+)/)[1]).toFixed(0)+' MHz</span></div>':'')
  +'</div>';

  // Memória
  html+='<div class="ic">'
    +'<div class="ic-head"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="8" width="22" height="8" rx="2"/><path d="M7 8V6M12 8V6M17 8V6M7 16v2M12 16v2M17 16v2"/></svg><span class="ic-title">Memória</span></div>'
    +'<div class="ic-row"><span class="lbl">Total</span><span class="val">'+(snap.mem_total||'—')+'</span></div>'
    +'<div class="ic-row"><span class="lbl">Usado</span><span class="val">'+(snap.mem_used||'—')+'</span></div>'
    +'<div class="ic-row"><span class="lbl">Livre</span><span class="val">'+(snap.mem_free||'—')+'</span></div>'
    +'<div class="progress-wrap">'
      +'<div class="progress-bar"><div class="progress-fill '+memClass+'" style="width:'+memPct+'%"></div></div>'
      +'<div class="progress-lbl">'+memPct+'% em uso</div>'
    +'</div>'
  +'</div>';

  // Docker summary
  var dockRunning=docker.filter(function(d){return (d.status||'').includes('Up');}).length;
  var dockStopped=docker.length-dockRunning;
  html+='<div class="ic">'
    +'<div class="ic-head"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg><span class="ic-title">Docker</span></div>'
    +'<div class="ic-row"><span class="lbl">Total</span><span class="val">'+docker.length+'</span></div>'
    +'<div class="ic-row"><span class="lbl">Ativos</span><span class="val" style="color:var(--green);font-weight:700">'+dockRunning+'</span></div>'
    +'<div class="ic-row"><span class="lbl">Parados</span><span class="val" style="color:'+(dockStopped>0?'var(--red)':'var(--muted)')+'">'+dockStopped+'</span></div>'
    +(raw.virtualization&&raw.virtualization!=='unavailable'&&raw.virtualization!=='none'&&raw.virtualization!=='unknown'?'<div class="ic-row"><span class="lbl">Virt</span><span class="val">'+esc(raw.virtualization)+'</span></div>':'')
  +'</div>';

  html+='</div>'; // card-grid row1

  // ── Row 2: Discos + Rede ──
  html+='<div class="card-grid wide">';

  // Discos
  html+='<div class="ic">'
    +'<div class="ic-head"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg><span class="ic-title">Discos</span></div>';
  if(disks.length===0){
    html+='<div class="no-data">Nenhuma partição montada detectada.</div>';
  } else {
    disks.forEach(function(d){
      html+='<div class="disk-item"><div class="disk-mount">'+esc(d.mount)+' <span style="color:var(--muted);font-size:11px">'+esc(d.name)+'</span></div>'
        +'<div class="disk-detail"><span>'+esc(d.size)+'</span></div></div>';
    });
  }
  html+='</div>';

  // Rede
  html+='<div class="ic">'
    +'<div class="ic-head"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="16" y="16" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="6" rx="1"/><rect x="9" y="2" width="6" height="6" rx="1"/><path d="M5 16v-4h14v4M12 8v4"/></svg><span class="ic-title">Rede</span></div>';
  if(ifaces.length===0){
    html+='<div class="no-data">Sem interfaces detectadas.</div>';
  } else {
    ifaces.slice(0,6).forEach(function(i){
      html+='<div class="iface-item"><span class="iface-name">'+esc(i.name)+'</span> '
        +'<span class="iface-ip">'+esc(i.ip)+'</span></div>';
    });
  }
  html+='</div>';
  html+='</div>'; // card-grid wide

  // ── Containers Docker ──
  if(docker.length>0){
    var running=docker.filter(function(d){return (d.status||'').includes('Up');});
    var stopped=docker.filter(function(d){return !(d.status||'').includes('Up');});
    html+='<div class="sec"><div class="sec-title">'
      +'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>'
      +'Containers Docker'
      +'<span class="pill pill-green">'+running.length+' ativos</span>'
      +(stopped.length>0?'<span class="pill pill-red">'+stopped.length+' parados</span>':'')
      +'<span class="pill pill-gray">'+docker.length+' total</span>'
    +'</div>';
    html+='<div class="ctr-grid">';
    docker.forEach(function(d){
      var isUp=(d.status||'').includes('Up');
      var isHealthy=(d.status||'').includes('healthy');
      var stClass=isUp?(isHealthy?'up':'up'):'stopped';
      var stLabel=isUp?(isHealthy?'Ativo (healthy)':'Ativo'):'Parado';
      var ports=(d.mapped_ports||[]).map(function(p){return '<span class="ctr-port">'+p+(SVC[p]?' '+SVC[p]:'')+'</span>';}).join('');
      html+='<div class="ctr-card '+(isUp?'':'stopped')+'">'
        +'<div class="ctr-head">'+docker_icon(d.name||d.image||'')+'<span class="ctr-name">'+esc(d.name||'')+'</span></div>'
        +'<div class="ctr-status '+stClass+'"><span class="dot"></span>'+stLabel+'</div>'
        +'<div class="ctr-img">'+esc((d.image||'').split('/').pop())+'</div>'
        +(ports?'<div class="ctr-ports">'+ports+'</div>':'')
      +'</div>';
    });
    html+='</div></div>';
  }

  // ── Portas ──
  if(svcs.length>0){
    // Determina quais portas são de container (tem container mapeado)
    var dockerPorts={};
    docker.forEach(function(d){(d.mapped_ports||[]).forEach(function(p){dockerPorts[p]=d.name;});});

    html+='<div class="sec"><div class="sec-title">'
      +'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 3a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3H6a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3V6a3 3 0 0 0-3-3 3 3 0 0 0-3 3 3 3 0 0 0 3 3h12a3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>'
      +'Portas Abertas'
      +'<span class="pill pill-gray">'+svcs.length+' detectadas</span>'
      +'<span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px">'
        +'<span style="display:inline-block;width:8px;height:8px;background:#bbf7d0;border-radius:2px;margin-right:3px"></span>Container &nbsp;'
        +'<span style="display:inline-block;width:8px;height:8px;background:#fde68a;border-radius:2px;margin-right:3px"></span>Local'
      +'</span>'
    +'</div>';
    html+='<div class="port-grid">';
    svcs.forEach(function(s){
      var isContainer=!!dockerPorts[s.port];
      var name=SVC[s.port]||(dockerPorts[s.port]?dockerPorts[s.port]:'Serviço '+s.port);
      var isLocal=s.bind_addr&&(s.bind_addr.startsWith('127.')||s.bind_addr==='::1');
      var cls=isContainer?'container':isLocal?'local':'local';
      var typeLabel=isContainer?'CONTAINER':'LOCAL';
      html+='<div class="port-card '+cls+'">'
        +'<div class="pc-head"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 3a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3H6a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3V6a3 3 0 0 0-3-3 3 3 0 0 0-3 3 3 3 0 0 0 3 3h12a3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>'
          +'<span class="pc-name">'+esc(name)+'</span>'
        +'</div>'
        +'<div class="pc-num">'+s.port+'</div>'
        +'<div class="pc-type">'+typeLabel+'</div>'
      +'</div>';
    });
    html+='</div></div>';
  }

  return html;
}

function renderAll(data){
  var c=document.getElementById('container');
  var totalSvcs=0,totalDock=0;
  var tabsHtml='<div class="srv-tabs">';
  var panelsHtml='';

  data.servers.forEach(function(srv,i){
    var svcs=(srv.services||[]).filter(function(s){return is_relevant(s.port,s.bind_addr);});
    totalSvcs+=svcs.length;
    totalDock+=(srv.docker||[]).length;

    tabsHtml+='<button class="srv-tab'+(i===0?' active':'')+'" onclick="switchSrv('+i+')" id="srv-tab-'+i+'">'
      +'<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>'
      +'<span class="sdot '+(srv.online?'on':'off')+'"></span>'
      +esc(srv.label||srv.hostname)
    +'</button>';

    panelsHtml+='<div class="srv-panel'+(i===0?' active':'')+'" id="srv-panel-'+i+'">'+renderPanel(srv)+'</div>';
  });

  tabsHtml+='</div>';
  c.innerHTML=tabsHtml+panelsHtml;

  document.getElementById('s-total').textContent=data.summary.total;
  document.getElementById('s-on').textContent=data.summary.online;
  document.getElementById('s-off').textContent=data.summary.offline;
  document.getElementById('s-svcs').textContent=totalSvcs;
  document.getElementById('s-dock').textContent=totalDock;

  var d=new Date(data.generated_at);
  document.getElementById('last-update').textContent=
    '· '+d.getHours().toString().padStart(2,'0')+':'+d.getMinutes().toString().padStart(2,'0')+':'+d.getSeconds().toString().padStart(2,'0');
}

function switchSrv(i){
  document.querySelectorAll('.srv-tab').forEach(function(t){t.classList.remove('active');});
  document.querySelectorAll('.srv-panel').forEach(function(p){p.classList.remove('active');});
  document.getElementById('srv-tab-'+i).classList.add('active');
  document.getElementById('srv-panel-'+i).classList.add('active');
}

function load(){
  var btn=document.getElementById('btnRef'),ico=document.getElementById('ref-ico');
  btn.disabled=true;ico.style.animation='spin 1s linear infinite';
  fetch('api/inventory_central/inventory_collector.php')
    .then(function(r){return r.json();})
    .then(function(d){renderAll(d);resetCd();})
    .catch(function(e){showToast('Erro: '+e.message,'err');})
    .finally(function(){btn.disabled=false;ico.style.animation='';});
}
function setRefresh(v){
  refSecs=parseInt(v);clearInterval(refInt);clearInterval(cdInt);
  document.getElementById('countdown').textContent='';
  if(refSecs>0){refInt=setInterval(load,refSecs*1000);startCd();}
}
function startCd(){
  cdLeft=refSecs;clearInterval(cdInt);
  cdInt=setInterval(function(){
    cdLeft--;
    if(cdLeft<=0){document.getElementById('countdown').textContent='';clearInterval(cdInt);}
    else document.getElementById('countdown').textContent='em '+cdLeft+'s';
  },1000);
}
function resetCd(){if(refSecs>0)startCd();}
var tt;
function showToast(msg,type){
  var t=document.getElementById('toast');
  t.textContent=msg;t.className='show '+(type||'inf');
  clearTimeout(tt);tt=setTimeout(function(){t.className='';},4000);
}
load();setRefresh(30);
</script>
</body>
</html>
