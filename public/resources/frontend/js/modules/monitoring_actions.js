(function(){
function initNocSuite(){
var L=document.getElementById('nodeListBody');if(!L)return;
var M=document.getElementById('addNodeModal');if(M&&!M.dataset.moved){document.body.appendChild(M);M.dataset.moved='true';}
if(L.dataset.initialized==='true')return;L.dataset.initialized='true';
var gw='api/index.php',ai=null,MC=null,sS=[],pS='',bs=false,sc=false,tm=null,mC=null,rH={},cl={},pStates={},
sH={cpu:[],mem:[],disk:[],net:[],fpm:[],dkrCPU:[],dkrMEM:[],diskIoR:[],diskIoW:[],perDisk:{},perLvm:{},perDiskIO:{}},_sysData=null,_metricsData=null,_advChartsInit=false,_trendRange=30,
cC=[],mC2=[],dC=[],nC=[],
pl=['#3b82f6','#22c55e','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6','#f97316','#6366f1','#84cc16','#06b6d4','#d946ef','#10b981','#e11d48'];
var palpha=['rgba(59,130,246,0.12)','rgba(34,197,94,0.12)','rgba(245,158,11,0.12)','rgba(239,68,68,0.12)','rgba(139,92,246,0.12)','rgba(236,72,153,0.12)','rgba(20,184,166,0.12)','rgba(249,115,22,0.12)','rgba(99,102,241,0.12)','rgba(132,204,22,0.12)','rgba(6,182,212,0.12)','rgba(217,70,239,0.12)','rgba(16,185,129,0.12)','rgba(225,29,72,0.12)'];
function rttVal(v){return v!==undefined&&v!==null?v:0;}
function rttLabel(v){return v===undefined||v===null||v===''?'--':v<1?'<1ms':v+'ms';}
function healthScore(s){if(s.status==='down')return 0;var loss=s.packet_loss||0,rtt=rttVal(s.avg_ttl);var sc=100-loss*2;if(rtt>100)sc-=30;else if(rtt>50)sc-=15;else if(rtt>20)sc-=5;return Math.max(0,Math.min(100,Math.round(sc)));}
function u(a){return gw+'?endpoint=monitoring_api&action='+a+'&_='+Date.now();}
function playMonAlertSound(t){try{if(typeof window._soundAlertsEnabled==='function'&&!window._soundAlertsEnabled())return;if(typeof window.playNotificationSound==='function'){window.playNotificationSound();return;}var c=new(window.AudioContext||window.webkitAudioContext)(),o=c.createOscillator(),g=c.createGain();o.connect(g);g.connect(c.destination);if(t==='down'){o.frequency.setValueAtTime(440,c.currentTime);o.frequency.setValueAtTime(220,c.currentTime+0.15);o.type='sawtooth';g.gain.setValueAtTime(0.3,c.currentTime);g.gain.exponentialRampToValueAtTime(0.001,c.currentTime+0.4);}else{o.frequency.setValueAtTime(880,c.currentTime);o.frequency.setValueAtTime(1320,c.currentTime+0.08);o.type='sine';g.gain.setValueAtTime(0.25,c.currentTime);g.gain.exponentialRampToValueAtTime(0.001,c.currentTime+0.25);}o.start();o.stop(c.currentTime+(t==='down'?0.4:0.25));}catch(e){}}
function isNodeIdle(ip){var h=rH[ip];if(!h||h.length<2)return false;for(var i=0;i<h.length;i++){var v=h[i].rtt!==undefined?h[i].rtt:h[i].ttl;if(v>0||v===null)return false;}return true;}
document.querySelectorAll('.noc-tab-item').forEach(function(t){t.onclick=function(){document.querySelectorAll('.noc-tab-item').forEach(function(x){x.classList.remove('active');});document.querySelectorAll('.noc-tab-content').forEach(function(x){x.style.display='none';});t.classList.add('active');var tab=document.getElementById('tab-'+t.dataset.tab);if(tab)tab.style.display='block';if(t.dataset.tab==='system'){setTimeout(function(){loadSystemInfo();loadAppMetrics();window.dispatchEvent(new Event('resize'));},50);}};});if(document.querySelector('.noc-tab-item.active[data-tab="system"]')){setTimeout(function(){loadSystemInfo();loadAppMetrics();window.dispatchEvent(new Event('resize'));},50);}
async function hb(){
if(bs)return;bs=true;
try{var r=await fetch(gw+'?endpoint=monitoring_api&_='+Date.now()),d=await r.json();
if(d.success){var ns=d.servers||[],sn=JSON.stringify(ns.map(function(s){return s.ip+s.status+s.avg_ttl+s.packet_loss;}));
if(sn!==pS||Object.keys(rH).length===0){pS=sn;sS=ns;renderNodes(sS);for(var si=0;si<ns.length;si++){if(!pStates[ns[si].ip])pStates[ns[si].ip]=ns[si].status;}}if(ai)updateFocus();
var nw=new Date(),ts=('0'+nw.getHours()).slice(-2)+':'+('0'+nw.getMinutes()).slice(-2)+':'+('0'+nw.getSeconds()).slice(-2);
for(var ni=0;ni<sS.length;ni++){var n2=sS[ni];
if(!rH[n2.ip]){rH[n2.ip]=[];if(n2.history&&n2.history.length){for(var hi=0;hi<n2.history.length;hi++){rH[n2.ip].push({time:n2.history[hi].time,rtt:n2.history[hi].ttl,status:n2.history[hi].up?'up':'down'});}}}
rH[n2.ip].push({time:ts,rtt:n2.avg_ttl,status:n2.status});if(rH[n2.ip].length>1000){rH[n2.ip]=rH[n2.ip].slice(-500);}
if(!cl[n2.ip]){cl[n2.ip]=pl[Object.keys(cl).length%pl.length];}}
populateNodeFilter();
if(!_rttPaused)renderMultiChart();
fetch(gw+'?endpoint=monitoring_api&action=refresh',{method:'POST'}).then(function(r){return r.json();}).then(function(rd){if(rd.success){var ns2=rd.servers||[],sn2=JSON.stringify(ns2.map(function(s){return s.ip+s.status+s.avg_ttl+s.packet_loss;}));if(sn2!==pS){pS=sn2;sS=ns2;renderNodes(sS);}
for(var nr=0;nr<ns2.length;nr++){var n3=ns2[nr],oldS=pStates[n3.ip];pStates[n3.ip]=n3.status;if(oldS&&oldS!==n3.status&&n3.status==='down'){playMonAlertSound('down');}else if(n3.status==='up'&&n3.avg_ttl>100){playMonAlertSound('high');}
if(rH[n3.ip]&&rH[n3.ip].length){var le=rH[n3.ip][rH[n3.ip].length-1];if(le&&le.time===ts){le.rtt=n3.avg_ttl;le.status=n3.status;}}}
if(!_rttPaused)renderMultiChart();if(ai)updateFocus();}}).catch(function(){});}}catch(e){}finally{bs=false;}}
function renderNodes(ss){var up=0,dn=0,wn=0,f=document.createDocumentFragment();
for(var i=0;i<ss.length;i++){var s=ss[i];if(s.status==='up'){up++;}else if(s.status==='down'){dn++;}else{wn++;}
var tr=document.createElement('tr');tr.className='node-row'+(ai===s.ip?' active':'');tr.onclick=function(ip){return function(){focusNode(ip);};}(s.ip);
var hc='text-secondary';if(s.status==='up'){hc='text-success';}else if(s.status==='down'){hc='text-danger';}else{hc='text-warning';}
var rttD=rttLabel(s.avg_ttl);var hs=healthScore(s);var idle=isNodeIdle(s.ip);
var since=s.assigned_at?((function(d){var diff=Math.floor((Date.now()-new Date(d.replace(' ','T')).getTime())/1000);if(diff<60)return diff+'s';if(diff<3600)return Math.floor(diff/60)+'m';if(diff<86400)return Math.floor(diff/3600)+'h';return Math.floor(diff/86400)+'d';})(s.assigned_at)):'--';
tr.innerHTML='<td><span class="node-status-pulse '+s.status+'"></span></td><td><span class="node-ip">'+s.ip+(idle?' <span class="badge bg-secondary font-tech" style="font-size:0.5rem;vertical-align:middle;">IDLE</span>':'')+'</span></td><td><span class="node-host">'+(s.dns_name||'...')+'</span></td><td><span class="node-owner">'+s.owner_name+'</span></td><td><span class="latency-val val-'+s.status+'">'+rttD+'</span></td><td class="font-tech small fw-bold '+(s.packet_loss>0?'text-danger':'text-success')+'">'+s.packet_loss+'%</td><td><div class="d-flex align-items-center gap-1"><div class="health-dot" style="width:6px;height:6px;border-radius:50%;background:'+(hs>=80?'#22c55e':hs>=50?'#f59e0b':'#ef4444')+';"></div><span class="font-tech fw-bold small" style="font-size:0.6rem;color:'+(hs>=80?'#22c55e':hs>=50?'#f59e0b':'#ef4444')+';">'+hs+'%</span></div></td><td class="text-center font-tech small fw-bold '+hc+'">'+s.status.toUpperCase()+'</td><td><span class="font-tech" style="font-size:0.6rem;color:var(--text-muted);" title="'+s.assigned_at+'">'+since+'</span></td><td class="text-end"><button class="btn btn-link text-danger p-0" style="font-size:0.7rem;" onclick="event.stopPropagation();window.deleteNocNode(\''+s.ip+'\')"><i class="fas fa-trash-alt"></i></button></td>';
f.appendChild(tr);}
L.innerHTML='';L.appendChild(f);
document.getElementById('nocTotalUp').textContent=up;document.getElementById('nocTotalDown').textContent=dn;document.getElementById('nocTotalWarn').textContent=wn;document.getElementById('nocTotalAll').textContent=ss.length;
var bd=document.getElementById('logCountBadge');if(bd){bd.textContent=ss.length;}}
window.focusNode=function(ip){ai=ip;var ph=document.getElementById('focusPlaceholder'),fa=document.getElementById('focusArea');if(ph){ph.style.display='none';}if(fa){fa.style.display='block';}document.getElementById('focusIp').textContent=ip;
requestAnimationFrame(function(){updateFocus();MC=null;SC=null;updateFocus();});
var lf=document.getElementById('logNodeFilter');if(lf){lf.value=ip;}loadLogs();
fetch(u('get_node_summary'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ip:ip})}).then(function(r){return r.json();}).then(renderNodeSummary).catch(function(){});
setTimeout(function(){fa.scrollIntoView({behavior:'smooth',block:'start'});},100);};
function updateFocus(){var s=null;for(var i=0;i<sS.length;i++){if(sS[i].ip===ai){s=sS[i];break;}}if(!s)return;
var sb=document.getElementById('focusStatusBadge');if(sb){var sbc=s.status==='up'?'bg-success':s.status==='down'?'bg-danger':'bg-warning';sb.className='badge '+sbc;sb.textContent=s.status.toUpperCase();}
document.getElementById('focusAvg').textContent=rttLabel(s.avg_ttl);document.getElementById('focusLoss').textContent=s.packet_loss+'%';
var hi=rH[ai]||s.history||[],rt=document.getElementById('focusRecentRtt');
var ttls=[];for(var ui=0;ui<hi.length;ui++){var rv=rttVal(hi[ui].rtt!==undefined?hi[ui].rtt:hi[ui].ttl);ttls.push(rv);}
var avg=ttls.length?ttls.reduce(function(a,b){return a+b;},0)/ttls.length:0;
var variance=ttls.length?ttls.reduce(function(a,b){return a+(b-avg)*(b-avg);},0)/ttls.length:0;
var jitter=Math.round(Math.sqrt(variance));
document.getElementById('focusJitter').textContent=jitter+'ms';
document.getElementById('focusHealth').textContent=healthScore(s)+'%';
document.getElementById('focusHealth').style.color=healthScore(s)>=80?'#22c55e':healthScore(s)>=50?'#f59e0b':'#ef4444';
document.getElementById('focusSamples').textContent=hi.length;
var uptimeSpan=document.getElementById('focusUptime');
if(s.status==='down'){uptimeSpan.textContent='DOWN';uptimeSpan.className='val text-danger font-tech';}
else{
var dur=0;
if(s.assigned_at){dur=Math.floor((Date.now()-new Date(s.assigned_at.replace(' ','T')).getTime())/1000);}
if(dur<=0)dur=60;
var days=Math.floor(dur/86400),hours=Math.floor((dur%86400)/3600),mins=Math.floor((dur%3600)/60);
var txt='';if(days>0){txt+=days+'d ';}if(hours>0||days>0){txt+=hours+'h ';}txt+=mins+'m';
uptimeSpan.textContent=txt;uptimeSpan.className='val text-success font-tech';}
if(hi.length){var h5=hi.slice(-5),hh='';for(var ri=0;ri<h5.length;ri++){var rv2=h5[ri].rtt!==undefined?h5[ri].rtt:h5[ri].ttl;var rtLbl=rttLabel(rv2);var stCl=h5[ri].status==='down'?'#ef4444':'#22c55e';hh+='<div><span class="font-tech" style="font-size:0.62rem;">'+h5[ri].time+'</span> &rarr; <span style="color:'+stCl+';font-weight:600;">'+rtLbl+'</span> <span style="font-size:0.6rem;color:'+stCl+';">('+h5[ri].status+')</span></div>';}rt.innerHTML=hh;}else{rt.innerHTML='<div class="opacity-50">Collecting...</div>';}
var tb=document.getElementById('focusTrace');if(s.traceroute&&s.traceroute.length){tb.innerHTML=s.traceroute.map(function(h){return '<div>'+h+'</div>';}).join('');}else{tb.innerHTML='<div class="opacity-50">Scanning...</div>';}
renderLine(rH[ai]||s.history||[]);renderSecondaryChart(rH[ai]||s.history||[]);}
function renderNodeSummary(d){if(!d.success)return;
var container=document.getElementById('nodeSummaryContainer');if(!container)return;
var html='';
html+='<div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;"><div class="card-body no-padding"><div class="p-2">';
html+='<div class="row g-1 mb-1"><div class="col-6"><span class="text-muted" style="font-size:var(--font-xs);">Monitored Since</span><br><span class="font-tech fw-bold" style="font-size:var(--font-sm);">'+d.assigned_at+'</span></div>';
html+='<div class="col-3"><span class="text-muted" style="font-size:var(--font-xs);">Uptime</span><br><span class="font-tech fw-bold text-success" style="font-size:var(--font-sm);">'+d.uptime_percent+'%</span></div>';
html+='<div class="col-3"><span class="text-muted" style="font-size:var(--font-xs);">Downtimes</span><br><span class="font-tech fw-bold '+(d.down_count>0?'text-danger':'text-muted')+'" style="font-size:var(--font-sm);">'+d.down_count+'x</span></div></div>';
if(d.recent_downs&&d.recent_downs.length){html+='<div style="font-size:var(--font-xs);border-top:1px solid var(--border-color);padding-top:4px;"><span class="text-muted fw-bold">Recent Outages:</span>';for(var di=0;di<d.recent_downs.length;di++){var rd=d.recent_downs[di];var durMin=Math.round(rd.duration_seconds/60);html+='<div class="d-flex justify-content-between"><span>'+rd.down_at+'</span><span class="text-danger">&rarr; '+rd.up_at+'</span><span class="text-warning">('+durMin+'m)</span></div>';}html+='</div>';}
html+='</div></div></div>';
html+='<div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;"><div class="card-body no-padding"><h3 class="log-title-wrapper app-table-title"><span><i class="fas fa-clock me-1"></i>Hourly Availability (Today)</span></h3><div class="p-2"><div class="d-flex flex-wrap gap-1">';
function fmtHr2(h){var p=h<12?'AM':'PM';var h12=h%12||12;return h12+p;}
for(var hi=0;hi<24;hi++){var hd=d.hourly[hi];var hp=hd.total>0?Math.round(hd.up/hd.total*100):-1;var hc=hp>=100?'#22c55e':hp>=50?'#f59e0b':'#ef4444';var hb2=hp>=100?'#22c55e22':hp>=50?'#f59e0b22':'#ef444422';html+='<div style="flex:1;min-width:28px;text-align:center;font-size:var(--font-xs);border-radius:3px;background:'+(hp>=0?hb2:'transparent')+';border:1px solid '+(hp>=0?hc+'44':'var(--border-color)')+';" title="'+fmtHr2(hi)+' - '+(hp>=0?hp+'%':'N/A')+'">';
html+='<div style="font-size:var(--font-xs);color:var(--text-muted);">'+fmtHr2(hi)+'</div>';html+='<div class="font-tech fw-bold" style="font-size:var(--font-xs);color:'+hc+';">'+(hp>=0?hp+'%':'--')+'</div>';html+='</div>';}
html+='</div></div></div></div>';
 if(d.monthly&&d.monthly.length){var mn=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
 html+='<div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;"><div class="card-body no-padding"><h3 class="log-title-wrapper app-table-title"><span><i class="fas fa-calendar-alt me-1"></i>Monthly Availability Calendar</span></h3><div class="p-2"><div class="monthly-grid">';
 for(var mi=0;mi<d.monthly.length;mi++){var mo=d.monthly[mi],yr=parseInt(mo.month.slice(0,4)),mth=parseInt(mo.month.slice(5,7))-1,daysInMo=new Date(yr,mth+1,0).getDate();
 var mp=mo.up+mo.down>0?Math.round(mo.up/(mo.up+mo.down)*100):-1;html+='<div class="month-row"><div class="month-label">'+mn[mth]+' '+yr+'</div><div class="month-cells">';
 for(var di=1;di<=daysInMo;di++){var ds=mo.month+'-'+('0'+di).slice(-2),dd=mo.days&&mo.days[ds],dp2=-1;
 if(dd){dp2=dd.up+dd.down>0?Math.round(dd.up/(dd.up+dd.down)*100):0;}
 var dc=dp2>=99?'#22c55e':dp2>=90?'#f59e0b':dp2>=80?'#f97316':dp2>=0?'#ef4444':'transparent';
 html+='<div class="day-cell" style="background:'+dc+';opacity:'+(dp2>=99?1:dp2>=90?0.7:dp2>=80?0.5:dp2>=0?0.35:0.1)+';" title="'+ds+': '+(dp2>=0?dp2+'%':'N/A')+'"></div>';}
 html+='</div><div class="month-pct font-tech fw-bold" style="font-size:var(--font-xs);color:'+(mp>=99?'#22c55e':mp>=90?'#f59e0b':mp>=80?'#f97316':mp>=0?'#ef4444':'var(--text-muted)')+';">'+(mp>=0?mp+'%':'--')+'</div></div>';}
 html+='</div><div class="d-flex gap-2 mt-1 align-items-center" style="font-size:var(--font-xs);"><span class="text-muted">Availability:</span><span class="day-cell" style="display:inline-block;width:10px;height:10px;background:#22c55e;border-radius:2px;"></span><span class="text-muted">&ge;99%</span><span class="day-cell" style="display:inline-block;width:10px;height:10px;background:#f59e0b;border-radius:2px;"></span><span class="text-muted">&ge;90%</span><span class="day-cell" style="display:inline-block;width:10px;height:10px;background:#f97316;border-radius:2px;"></span><span class="text-muted">&ge;80%</span><span class="day-cell" style="display:inline-block;width:10px;height:10px;background:#ef4444;border-radius:2px;"></span><span class="text-muted">&lt;80%</span><span class="day-cell" style="display:inline-block;width:10px;height:10px;background:transparent;border:1px solid var(--border-color);border-radius:2px;"></span><span class="text-muted">No data</span></div></div></div></div>';}
 container.innerHTML=html;}
function renderLine(hi){var ctx=document.getElementById('mainStreamChart');if(!ctx)return;
var la=hi.map(function(h){return h.time;}),da=hi.map(function(h){var v=h.rtt!==undefined?h.rtt:h.ttl;return v!==null&&v!==undefined?v:null;});
var pc=hi.map(function(h){return h.status==='down'?'#ef4444':h.status==='up'?'#22c55e':cl[ai]||'#3b82f6';});
var nodeColor=cl[ai]||'#3b82f6';
if(MC){MC.data.labels=la;MC.data.datasets[0].data=da;MC.data.datasets[0].borderColor=nodeColor;MC.data.datasets[0].pointBackgroundColor=pc;MC.data.datasets[0].pointBorderColor=pc;MC.update('none');return;}
if(la.length===0||da.every(function(v){return v===null;}))return;
var gr=function(c){var g=c.chart.ctx.createLinearGradient(0,0,0,160);g.addColorStop(0,nodeColor+'55');g.addColorStop(1,nodeColor+'08');return g;};
var dc=hi.some(function(h){return h.status==='down';})?true:false;
MC=new Chart(ctx,{type:'line',data:{labels:la,datasets:[{label:'RTT',data:da,borderColor:dc?'#ef4444':nodeColor,backgroundColor:gr,fill:true,tension:0.3,borderWidth:2,pointRadius:3,pointHoverRadius:6,pointBackgroundColor:pc,pointBorderColor:pc,pointBorderWidth:1,spanGaps:false}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},datalabels:{display:false}},scales:{x:{display:false},y:{beginAtZero:true,suggestedMax:10,grid:{color:'rgba(0,0,0,0.04)'},ticks:{font:{size:11},color:'#6b7280',callback:function(v){return v+'ms';}}}},animation:{duration:400,easing:'easeOutQuart'}}});}
var SC=null;function renderSecondaryChart(hi){var ctx=document.getElementById('secondaryStreamChart');if(!ctx||!hi||hi.length<2)return;
var ld=hi.map(function(h){var v=h.rtt!==undefined?h.rtt:h.ttl;return v!==null&&v!==undefined?v:0;}),lb=hi.map(function(h){return h.time;});
var nodeColor=cl[ai]||'#3b82f6';
if(SC){SC.data.labels=lb;SC.data.datasets[0].data=ld;SC.data.datasets[0].backgroundColor=nodeColor+'4d';SC.data.datasets[0].borderColor=nodeColor;SC.update('none');return;}
SC=new Chart(ctx,{type:'bar',data:{labels:lb,datasets:[{label:'RTT',data:ld,backgroundColor:nodeColor+'4d',borderColor:nodeColor,borderWidth:1,borderRadius:3}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},datalabels:{display:false}},scales:{x:{display:false},y:{beginAtZero:true,suggestedMax:10,grid:{color:'rgba(0,0,0,0.04)'},ticks:{font:{size:11},color:'#6b7280',callback:function(v){return v+'ms';}}}},animation:{duration:400,easing:'easeOutQuart'}}});}
function renderMultiChart(){var ctx=document.getElementById('multiNodeChart');if(!ctx)return;
var rm=parseInt(document.getElementById('rttTimeRange').value)||15,ct=new Date(Date.now()-rm*60*1000),ips=Object.keys(rH);
var multiSeries=[];
for(var mi=0;mi<ips.length;mi++){var ip=ips[mi],pt=rH[ip];
var dp=[];for(var pi=0;pi<pt.length;pi++){var p=pt[pi];if(p.time){var d=parseTime(p.time);if(d&&d>=ct){var mv=p.rtt!==undefined?p.rtt:p.ttl;if(mv!==null&&mv!==undefined)dp.push({value:mv,time:p.time});}}}
if(dp.length<2)continue;
var c=cl[ip]||pl[mi%pl.length];
var idleLabel=isNodeIdle(ip)?' (idle)':'';
multiSeries.push({label:ip+idleLabel,data:dp,color:c,unit:'ms',lineWidth:2.5});}
// Legend badges in title bar (system trend chart style)
var titleEl=ctx.parentElement.parentElement.previousElementSibling;
if(titleEl){
    var lgHtml='';
    for(var si=0;si<multiSeries.length;si++){
        var s=multiSeries[si];var cv=s.data.length?s.data[s.data.length-1].value:null;
        lgHtml+='<span style="display:inline-flex;align-items:center;gap:3px;margin:0 3px;">'+
            '<span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:'+s.color+';"></span>'+
            '<span style="color:'+s.color+';font-size:10px;font-weight:600;">'+s.label+(cv!==null&&cv!==undefined?' '+cv+'ms':'')+'</span></span>';
    }
    if(lgHtml){
        var lgEl=titleEl.querySelector('.chart-legend-inline');
        if(!lgEl){lgEl=document.createElement('span');lgEl.className='chart-legend-inline';titleEl.appendChild(lgEl);}
        lgEl.innerHTML=lgHtml;
    }
}
renderSysTrendChart('multiNodeChart',multiSeries,{unit:'ms',minMax:10,bottomPad:3});
// Tooltip (one-time setup)
if(!ctx._multiTip){
ctx._multiTip=true;
ctx.addEventListener('mousemove',function(e){
var rect=ctx.getBoundingClientRect();var mx=e.clientX-rect.left;
if(!ctx._cdata)return;var cd=ctx._cdata;
if(mx<cd.pad.l||mx>cd.pad.l+cd.pw)return;
var idx=Math.round((mx-cd.pad.l)/cd.sx);if(idx<0)idx=0;if(idx>=cd.ptsCount)idx=cd.ptsCount-1;
var tip=document.getElementById('multiNodeChart_tip');
if(!tip){tip=document.createElement('div');tip.id='multiNodeChart_tip';tip.style.cssText='position:fixed;background:rgba(0,0,0,0.85);color:#fff;font:bold 11px monospace;padding:4px 8px;border-radius:4px;pointer-events:none;z-index:9999;white-space:nowrap;line-height:1.5;';document.body.appendChild(tip);}
var lines='';
for(var si=0;si<cd.series.length;si++){var s=cd.series[si];if(s.data&&s.data.length>idx){var dp=s.data[idx];var valStr=dp.value+(s.unit||'ms');var clr=s.color||'#3b82f6';lines+='<span style="color:'+clr+'">\u25CF</span> '+s.label+': '+valStr+(dp.time?' ('+dp.time+')':'')+'\n';}}
tip.innerHTML=lines.replace(/\n/g,'<br>');
var tx=e.clientX+14,ty=e.clientY-24;if(tx+120>window.innerWidth)tx=e.clientX-130;if(ty<0)ty=e.clientY+24;
tip.style.left=tx+'px';tip.style.top=ty+'px';});
ctx.addEventListener('mouseout',function(){var tip=document.getElementById('multiNodeChart_tip');if(tip)tip.remove();});
}}
function parseTime(t){if(!t)return null;var p=t.split(':');if(p.length<2)return null;var d=new Date();d.setHours(parseInt(p[0])||0,parseInt(p[1])||0,parseInt(p[2])||0,0);return d;}
var rf=document.getElementById('rttTimeRange');if(rf){rf.onchange=renderMultiChart;}
var _rttPaused=false;
var pauseBtn=document.getElementById('btnRttPause');if(pauseBtn){pauseBtn.onclick=function(){_rttPaused=!_rttPaused;pauseBtn.innerHTML=_rttPaused?'<i class=\"fas fa-play\"></i>':'<i class=\"fas fa-pause\"></i>';pauseBtn.classList.toggle('btn-warning',_rttPaused);};}
var exportBtn=document.getElementById('btnRttExport');if(exportBtn){exportBtn.onclick=function(){var ips=Object.keys(rH);if(!ips.length)return;var rows=[['Time'].concat(ips)];var maxLen=0;for(var ei=0;ei<ips.length;ei++){if(rH[ips[ei]].length>maxLen)maxLen=rH[ips[ei]].length;}for(var ri=0;ri<maxLen;ri++){var row=[rH[ips[0]][ri]?rH[ips[0]][ri].time:''];for(var ei=0;ei<ips.length;ei++){var dp=rH[ips[ei]][ri];row.push(dp?dp.rtt!==undefined?dp.rtt:dp.ttl||'':'');}rows.push(row);}var csv=rows.map(function(r){return r.join(',')}).join('\n');var b=new Blob([csv],{type:'text/csv'});var a=document.createElement('a');a.href=URL.createObjectURL(b);a.download='multi_node_rtt_export.csv';document.body.appendChild(a);a.click();document.body.removeChild(a);URL.revokeObjectURL(a.href);};}

var logTimer=null;
function loadLogs(){var ip=document.getElementById('logNodeFilter').value,dt=document.getElementById('logDateFilter').value||new Date().toISOString().slice(0,10);
var tf=document.getElementById('logTimeFrom'),tt=document.getElementById('logTimeTo');
var fr=tf?tf.value:'00:00',to=tt?tt.value:'23:59';
fetch(u('get_logs'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ip:ip,date:dt})}).then(function(r){return r.json();}).then(function(d){
var ct=document.getElementById('logEntriesContainer');if(!ct)return;
if(!d.success||!d.logs.length){ct.innerHTML='<div class="text-center py-3 opacity-50 small" style="font-size:0.7rem;">No logs for selected filters.</div>';return;}
var html='',all=[];
for(var li=0;li<d.logs.length;li++){for(var ei=0;ei<d.logs[li].entries.length;ei++){var e=d.logs[li].entries[ei];
if(e.time<fr||e.time>to)continue;
all.push({ip:d.logs[li].ip,dns:d.logs[li].dns||'',owner:d.logs[li].owner||'',time:e.time,status:e.status,rtt:e.rtt,loss:e.loss});}}
all.sort(function(a,b){return a.time.localeCompare(b.time);});all.reverse();
for(var si=0;si<Math.min(all.length,100);si++){var ev=all[si];
var stCl=ev.status==='up'?'#22c55e':ev.status==='down'?'#ef4444':'#f59e0b';
var rttD=rttLabel(ev.rtt);var icon=ev.status==='up'?'fa-check-circle':'fa-times-circle';
html+='<div style="border-left:3px solid '+stCl+';padding:6px 8px;margin-bottom:4px;border-radius:0 4px 4px 0;background:'+stCl+'08;font-size:0.65rem;">';
html+='<div class="d-flex justify-content-between align-items-center"><span><i class="fas '+icon+'" style="color:'+stCl+';font-size:0.6rem;"></i> <span class="fw-bold font-tech" style="color:'+stCl+';">'+ev.status.toUpperCase()+'</span></span><span class="font-tech text-muted">'+ev.time+'</span></div>';
html+='<div class="d-flex justify-content-between mt-1"><span><span class="text-muted">Node:</span> <span class="font-tech">'+ev.ip+'</span>'+(ev.dns?' <span class="text-muted">('+ev.dns+')</span>':'')+'</span><span><span class="text-muted">RTT:</span> <span class="font-tech fw-bold">'+rttD+'</span> <span class="text-muted">Loss:</span> <span class="font-tech">'+ev.loss+'%</span></span></div>';
html+='</div>';}
ct.innerHTML=html||'<div class="text-center py-3 opacity-50 small" style="font-size:0.7rem;">No entries matching time range.</div>';window._logExportData=all;}).catch(function(){});}
document.getElementById('btnLoadLogs').onclick=loadLogs;
var lf2=document.getElementById('logNodeFilter');if(lf2){lf2.onchange=function(){loadLogs();};}
var logDate=document.getElementById('logDateFilter');if(logDate){var td=new Date();logDate.valueAsDate=td;logDate.onchange=function(){loadLogs();};}
var logFrom=document.getElementById('logTimeFrom');if(logFrom)logFrom.onchange=loadLogs;
var logTo=document.getElementById('logTimeTo');if(logTo)logTo.onchange=loadLogs;
if(logTimer)clearInterval(logTimer);logTimer=setInterval(function(){loadLogs();},30000);loadLogs();
var logExportBtn=document.getElementById('btnExportLogs');if(logExportBtn){logExportBtn.onclick=function(){var data=window._logExportData;if(!data||!data.length)return;var rows=[['Time','IP','DNS','Owner','Status','RTT','Loss']];for(var ei=0;ei<data.length;ei++){var e=data[ei];rows.push([e.time,e.ip,e.dns||'',e.owner||'',e.status,e.rtt!==undefined?e.rtt:'',e.loss]);}var csv=rows.map(function(r){return r.join(',')}).join('\n');var a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'}));a.download='event_logs_export.csv';document.body.appendChild(a);a.click();document.body.removeChild(a);};}
function populateNodeFilter(){var lf=document.getElementById('logNodeFilter');if(!lf)return;
var cur=lf.value;lf.innerHTML='<option value="">All Nodes</option>';for(var fi=0;fi<sS.length;fi++){var o=document.createElement('option');o.value=sS[fi].ip;o.textContent=sS[fi].ip+' ('+(sS[fi].dns_name||'?')+')';if(sS[fi].ip===cur)o.selected=true;lf.appendChild(o);}}

// ========== SYSTEM MONITOR ==========
function drawGauge(canvasId,pct,label,color,maxSize){
var c=document.getElementById(canvasId);if(!c)return;
var p=c.parentElement||c,rect=p.getBoundingClientRect();
if(rect.width<10)return;
var size=Math.min(maxSize||120,Math.floor(rect.width)),dpr=window.devicePixelRatio||1;
c.width=size*dpr;c.height=size*dpr;c.style.width=size+'px';c.style.height=size+'px';
var cx=c.getContext('2d');cx.scale(dpr,dpr);
var cx2=size/2,cy2=size/2,r=size/2-8,lw=Math.max(8,Math.min(size/4,18));
var start=Math.PI*0.75,end=Math.PI*0.25,range=end-start+2*Math.PI;
cx.clearRect(0,0,size,size);
var bands=[{p:40,clr:'#22c55e'},{p:70,clr:'#f59e0b'},{p:100,clr:'#ef4444'}];var prevP=0;
for(var bi=0;bi<bands.length;bi++){var bandAng=start+range*(bands[bi].p/100);var prevAng=start+range*(prevP/100);cx.beginPath();cx.arc(cx2,cy2,r-lw/2,prevAng,bandAng);cx.strokeStyle=bands[bi].clr+'44';cx.lineWidth=lw;cx.lineCap='butt';cx.stroke();prevP=bands[bi].p;}
var ang=start+range*(pct/100);
cx.beginPath();cx.arc(cx2,cy2,r-lw/2,start,ang);cx.strokeStyle=color;cx.lineWidth=lw;cx.lineCap='round';cx.stroke();
    var fs=Math.max(10,Math.min(size/5,16));cx.fillStyle='#fff';cx.font='bold '+fs+'px monospace';cx.textAlign='center';cx.textBaseline='middle';cx.fillText(pct+'%',cx2,cy2-4);
    cx.fillStyle='rgba(255,255,255,0.35)';cx.font='7px monospace';cx.fillText(label,cx2,cy2+Math.max(7,fs/2+3));}
function drawDoughnut(canvasId,pct,color){
var c=document.getElementById(canvasId);if(!c)return;
var size=80,dpr=window.devicePixelRatio||1;
c.width=size*dpr;c.height=size*dpr;c.style.width=size+'px';c.style.height=size+'px';
var cx=c.getContext('2d');cx.scale(dpr,dpr);
var cx2=size/2,cy2=size/2,r=size/2-5,lw=10;
cx.clearRect(0,0,size,size);
function hexToRgba(h,a){h=h.replace('#','');var r=parseInt(h.substring(0,2),16);var g=parseInt(h.substring(2,4),16);var b=parseInt(h.substring(4,6),16);return'rgba('+r+','+g+','+b+','+a+')';}
// Full track ring
cx.beginPath();cx.arc(cx2,cy2,r-lw/2,0,Math.PI*2);cx.strokeStyle=hexToRgba(color,0.25);cx.lineWidth=lw;cx.lineCap='round';cx.stroke();
// Used arc
if(pct>0){var ang=pct/100*Math.PI*2;cx.beginPath();cx.arc(cx2,cy2,r-lw/2,-Math.PI/2,-Math.PI/2+ang);cx.strokeStyle=color;cx.lineWidth=lw;cx.lineCap='round';cx.stroke();}
// Center text
if(pct>=0){var fs=12;cx.fillStyle='#fff';cx.font='bold '+fs+'px monospace';cx.textAlign='center';cx.textBaseline='middle';cx.fillText(pct+'%',cx2,cy2);}if(pct<0){var fs=9;cx.fillStyle='#64748b';cx.font=fs+'px monospace';cx.textAlign='center';cx.textBaseline='middle';cx.fillText('--',cx2,cy2);}}
function renderSysTrendChart(canvasId,series,opts){
opts=opts||{};
var c=document.getElementById(canvasId);if(!c)return;
var hasData=false;for(var si=0;si<series.length;si++){if(series[si].data&&series[si].data.length>=2){hasData=true;break;}}
if(!hasData){c._cdata=null;return;}
var p=c.parentElement,rect=p.getBoundingClientRect();
if(rect.width<10||rect.height<10)return;
var w=Math.floor(rect.width),h=Math.floor(rect.height);
var dpr=window.devicePixelRatio||1;
c.width=w*dpr;c.height=h*dpr;c.style.width=w+'px';c.style.height=h+'px';
var cx=c.getContext('2d');cx.scale(dpr,dpr);cx.clearRect(0,0,w,h);
var pad={t:15,b:36,l:34,r:16},pw=w-pad.l-pad.r,ph=h-pad.t-pad.b;
var allVals=[];for(var si=0;si<series.length;si++){if(series[si].data){for(var vi=0;vi<series[si].data.length;vi++){allVals.push(series[si].data[vi].value);}}}
var minMaxY=opts.minMax||100;var yUnit=opts.unit||(series.length&&series[0].unit)||'%';
var maxV=allVals.length?Math.max(minMaxY,Math.ceil(Math.max.apply(null,allVals)/20)*20):minMaxY;
var bottomPad=opts.bottomPad||0;var yRange=maxV+bottomPad;var sy=ph/yRange;
var ptsCount=0;for(var si=0;si<series.length;si++){if(series[si].data&&series[si].data.length>ptsCount)ptsCount=series[si].data.length;}
var sx=ptsCount>1?pw/(ptsCount-1):pw;

// --- HTML overlay container ---
var ovId=c.id+'_overlay';
var ov=document.getElementById(ovId);
if(!ov){ov=document.createElement('div');ov.id=ovId;ov.style.cssText='position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:1;font-family:Roboto,Kalpurush,sans-serif;font-size:10px;';c.parentElement.style.position='relative';c.parentElement.appendChild(ov);}
ov.innerHTML='';

// Y-axis labels (left)
for(var i=0;i<=2;i++){
var y=pad.t+10+(ph-10)/2*i;
var val=Math.round(maxV-(maxV/2)*i)+yUnit;
var el=document.createElement('div');el.style.cssText='position:absolute;left:'+(pad.l-32)+'px;top:'+(y-6)+'px;color:#6b7280;font-size:10px;text-align:right;width:30px;user-select:text;';
el.textContent=val;ov.appendChild(el);}

// --- Draw lines/fills on canvas ---
var bg=cx.createLinearGradient(0,pad.t+10,0,pad.t+ph);bg.addColorStop(0,'rgba(0,0,0,0.04)');bg.addColorStop(1,'rgba(0,0,0,0.01)');
cx.fillStyle=bg;cx.fillRect(pad.l,pad.t,pw,ph);
cx.strokeStyle='rgba(0,0,0,0.08)';cx.lineWidth=0.5;
for(var i=0;i<=2;i++){var y=pad.t+10+(ph-10)/2*i;cx.beginPath();cx.moveTo(pad.l,y);cx.lineTo(pad.l+pw,y);cx.stroke();}

for(var si=0;si<series.length;si++){
var s=series[si];if(!s.data||s.data.length<2)continue;
var vals=s.data.map(function(p){return p.value;});var color=s.color||'#3b82f6';var curV=vals[vals.length-1];var unit=s.unit||'%';
var ag=cx.createLinearGradient(0,pad.t+10,0,pad.t+ph);
if(s.dashed){ag.addColorStop(0,color+'10');ag.addColorStop(1,color+'03');}
else{ag.addColorStop(0,color+'25');ag.addColorStop(1,color+'05');}
cx.fillStyle=ag;cx.beginPath();cx.moveTo(pad.l,pad.t+ph);
for(var i=0;i<vals.length;i++){cx.lineTo(pad.l+i*sx,pad.t+ph-(vals[i]+bottomPad)*sy);}
cx.lineTo(pad.l+(vals.length-1)*sx,pad.t+ph);cx.closePath();cx.fill();
cx.strokeStyle=color;cx.lineWidth=s.lineWidth||1.5;cx.lineJoin='miter';cx.lineCap='butt';
if(s.dashed)cx.setLineDash([4,3]);else cx.setLineDash([]);
cx.beginPath();
for(var i=0;i<vals.length;i++){var px=pad.l+i*sx,py=pad.t+ph-(vals[i]+bottomPad)*sy;if(i===0){cx.moveTo(px,py);}else{cx.lineTo(px,py);}}
cx.stroke();cx.setLineDash([]);

// Data dots + spike labels (HTML)
var lx=pad.l+(vals.length-1)*sx,ly=pad.t+ph-(curV+bottomPad)*sy;
var _scratch=vals.slice(0).sort(function(a,b){return b-a;});
var _thr=Math.max(90,_scratch[Math.min(1,_scratch.length-1)]*0.8);
var _labelSet={};
for(var _i=0;_i<vals.length;_i++){if(vals[_i]>=_thr)_labelSet[_i]=1;}
_labelSet[vals.length-1]=1;
var _lyList=[];
for(var _i=0;_i<vals.length;_i++){if(_labelSet[_i]){var _py=pad.t+ph-(vals[_i]+bottomPad)*sy;_lyList.push({idx:_i,py:_py});}}
_lyList.sort(function(a,b){return b.py-a.py;});
for(var _j=0;_j<_lyList.length;_j++){var _cur=_lyList[_j];if(_j>0&&Math.abs(_cur.py-_lyList[_j-1].py)<28)_cur.py=_lyList[_j-1].py-28;}
var _lyMap={};for(var _j=0;_j<_lyList.length;_j++)_lyMap[_lyList[_j].idx]=_lyList[_j].py;

var _spikeYOff=si*12;

for(var i=0;i<vals.length;i++){var px=pad.l+i*sx,py=pad.t+ph-(vals[i]+bottomPad)*sy;
var _dotR=s.dashed?1.2:2;
cx.beginPath();cx.arc(px,py,_dotR,0,Math.PI*2);cx.fillStyle=color;cx.strokeStyle='rgba(0,0,0,0.1)';cx.lineWidth=0.5;cx.stroke();cx.fill();

if(_labelSet[i]){
var vl=vals[i]+unit;
var _lOff=si%2===0?-(30+si*6):4+si*6;
var _lblTop=_lyMap[i]-20+_spikeYOff;
if(_lblTop<pad.t+2)_lblTop=_lyMap[i]+8+_spikeYOff;
if(_lblTop>pad.t+ph-14)_lblTop=pad.t+ph-14;
var lbl=document.createElement('div');lbl.style.cssText='position:absolute;left:'+(px+_lOff)+'px;top:'+_lblTop+'px;color:'+color+';font-size:10px;font-weight:600;'+(si%2===0?'text-align:right;width:28px;':'text-align:left;width:auto;')+'user-select:text;white-space:nowrap;';
lbl.textContent=vl;ov.appendChild(lbl);}}
var _epR=s.dashed?1.5:2.5;
cx.beginPath();cx.arc(lx,ly,_epR,0,Math.PI*2);cx.fillStyle=color;cx.strokeStyle='rgba(0,0,0,0.15)';cx.lineWidth=1;cx.stroke();cx.fill();}

// Time labels (bottom)
var lc=Math.min(3,ptsCount);var ls=Math.max(2,Math.floor(ptsCount/lc));
for(var i=0;i<ptsCount;i+=ls){var t='';for(var si=0;si<series.length;si++){if(series[si].data&&series[si].data[i]){t=series[si].data[i].time||'';break;}}
var el=document.createElement('div');el.style.cssText='position:absolute;left:'+(pad.l+i*sx-22)+'px;top:'+(pad.t+ph+4)+'px;color:#9ca3af;font-size:10px;text-align:center;width:44px;user-select:text;';
el.textContent=t;ov.appendChild(el);}

// Legend in chart title
var titleEl=c.parentElement.previousElementSibling;
if(titleEl){
    var lgHtml='';
    for(var si=0;si<series.length;si++){var s=series[si];if(!s.data||s.data.length<2)continue;
        var vals=s.data.map(function(p){return p.value;});var cv=vals[vals.length-1];var u=s.unit||'%';
        lgHtml+='<span style="display:inline-flex;align-items:center;gap:3px;margin:0 3px;">'+
            '<span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:'+s.color+';"></span>'+
            '<span style="color:'+s.color+';font-size:10px;font-weight:600;">'+s.label+': '+cv+u+'</span></span>';
    }
    if(lgHtml){
        var lgEl=titleEl.querySelector('.chart-legend-inline');
        if(!lgEl){lgEl=document.createElement('span');lgEl.className='chart-legend-inline';titleEl.appendChild(lgEl);}
        lgEl.innerHTML=lgHtml;
    }
}

c._cdata={series:series,pad:pad,sx:sx,pw:pw,ptsCount:ptsCount,ov:ov};}
// --- Trend chart controller (range filter + mouse + export) ---
var _trendRanges=[{l:'30m',v:30},{l:'3h',v:180},{l:'24h',v:1440},{l:'3d',v:4320},{l:'1w',v:10080}];
var _trendControllers=[];
function filterByRange(arr,minutes){if(!minutes||minutes<=0)return arr;var cut=Date.now()-minutes*60000;var r=[];for(var i=0;i<arr.length;i++){if(arr[i].ts>=cut)r.push(arr[i]);}return r;}
function exportChartCSV(label,series){
    function fmtTime(t){
        if(!t)return'';
        var m=t.match(/^(\d{1,2}):(\d{2}):(\d{2})\s*(AM|PM)?$/i);
        if(!m)return t;
        var h=parseInt(m[1],10),mi=m[2],s=m[3];
        if(m[4])return ('0'+h).slice(-2)+':'+mi+':'+s+' '+m[4].toUpperCase();
        var ampm=h>=12?'PM':'AM';var h12=h%12||12;
        return ('0'+h12).slice(-2)+':'+mi+':'+s+' '+ampm;
    }
    function fmtVal(v,u){
        if(v===''||v==null)return'';
        var n=parseFloat(v);
        if(!u||u==='%')return n.toFixed(1);
        if(u==='KB/s'){
            if(n>=1048576)return (n/1048576).toFixed(2)+' GB/s';
            if(n>=1024)return (n/1024).toFixed(2)+' MB/s';
            return n.toFixed(1)+' KB/s';
        }
        return n.toFixed(1)+' '+u;
    }
    var headers=['Timestamp','Time'];
    for(var si=0;si<series.length;si++){
        var s=series[si];headers.push(s.label+(s.unit?' ('+s.unit+')':''));
    }
    var rows=[headers];
    var maxLen=0;
    for(var si=0;si<series.length;si++){
        if(series[si].data&&series[si].data.length>maxLen)maxLen=series[si].data.length;
    }
    for(var i=0;i<maxLen;i++){
        var ref=i<series[0].data.length?series[0].data[i]:null;
        var ts=ref?ref.ts||'':'';
        var t=ref?fmtTime(ref.time):'';
        var row=[ts,t];
        for(var si=0;si<series.length;si++){
            var dp=i<series[si].data.length?series[si].data[i]:null;
            row.push(fmtVal(dp?dp.value:'',series[si].unit));
        }
        rows.push(row);
    }
    var csv=rows.map(function(r){return r.join(',')}).join('\n');
    var blob=new Blob([csv],{type:'text/csv'});
    var a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download=label+'_trend.csv';
    a.click();
    URL.revokeObjectURL(a.href);
}
function initRangeButtons(containerId){var parentEl=document.getElementById(containerId);if(!parentEl||parentEl.dataset.inited)return;parentEl.dataset.inited='true';var activeBtn=parentEl.querySelector('.range-btn.active');if(activeBtn)_trendRange=parseInt(activeBtn.dataset.minutes)||30;parentEl.addEventListener('click',function(e){var b=e.target.closest('.range-btn');if(!b)return;parentEl.querySelectorAll('.range-btn').forEach(function(x){x.classList.remove('active');});b.classList.add('active');_trendRange=parseInt(b.dataset.minutes)||0;for(var ci=0;ci<_trendControllers.length;ci++)if(_trendControllers[ci].refresh)_trendControllers[ci].refresh();});}
function setupTrendChart(opts){
var c=document.getElementById(opts.canvasId);if(!c)return null;
var controller={canvasId:opts.canvasId,getSeries:opts.getSeries,exportBtn:null};
if(opts.exportBtnId){var eb=document.getElementById(opts.exportBtnId);if(eb){eb.classList.remove('app-hidden');eb.onclick=function(){var res=controller.getSeries();exportChartCSV(opts.canvasId,res);};controller.exportBtn=eb;}}
c.addEventListener('mousemove',function(e){var rect=c.getBoundingClientRect();var mx=e.clientX-rect.left;if(!c._cdata)return;var cd=c._cdata;if(!cd||!cd.series||mx<cd.pad.l||mx>cd.pad.l+cd.pw)return;var idx=Math.round((mx-cd.pad.l)/cd.sx);if(idx<0)idx=0;if(idx>=cd.ptsCount)idx=cd.ptsCount-1;var tooltipEl=document.getElementById(opts.canvasId+'_tip');if(!tooltipEl){tooltipEl=document.createElement('div');tooltipEl.id=opts.canvasId+'_tip';tooltipEl.style.cssText='position:fixed;background:rgba(0,0,0,0.85);color:#fff;font:bold 11px monospace;padding:4px 8px;border-radius:4px;pointer-events:none;z-index:9999;white-space:nowrap;line-height:1.5;';document.body.appendChild(tooltipEl);}var lines='';for(var si=0;si<cd.series.length;si++){var s=cd.series[si];if(s.data&&s.data.length>idx){var dp=s.data[idx];var unit=s.unit||'%';var valStr=s.unit==='KB/s'?(dp.value>1024?(dp.value/1024).toFixed(1)+'MB/s':dp.value+'KB/s'):dp.value+unit;var clr=s.color||'#3b82f6';lines+='<span style="color:'+clr+'">\u25CF</span> '+s.label+': '+valStr+(dp.time?'  ('+dp.time+')':'')+'\n';}}tooltipEl.innerHTML=lines.replace(/\n/g,'<br>');var tx=e.clientX+14,ty=e.clientY-24;if(tx+120>window.innerWidth)tx=e.clientX-130;if(ty<0)ty=e.clientY+24;tooltipEl.style.left=tx+'px';tooltipEl.style.top=ty+'px';});
c.addEventListener('mouseout',function(){var el=document.getElementById(opts.canvasId+'_tip');if(el)el.remove();});
controller.refresh=function(){var res=controller.getSeries();if(res&&res.length)renderSysTrendChart(controller.canvasId,res);};
_trendControllers.push(controller);
return controller;}
// ----------------------------------------------------------------
function pushSysHistory(d){
var nw=new Date(),_ts=nw.getTime();
var h=nw.getHours(),m=nw.getMinutes(),s=nw.getSeconds();
var ampm=h>=12?'PM':'AM';var h12=h%12||12;
var ts=('0'+h12).slice(-2)+':'+('0'+m).slice(-2)+':'+('0'+s).slice(-2)+' '+ampm;
var cpuPct=0,memPct=0,diskPct=0,netRate=0;
if(d.cpu){cpuPct=d.cpu.usage||0;memPct=d.memory&&d.memory.total>0?Math.round(d.memory.used/d.memory.total*100):0;diskPct=d.disk_overall&&d.disk_overall.total>0?Math.round(d.disk_overall.used/d.disk_overall.total*100):0;sH.cpu.push({ts:_ts,time:ts,value:cpuPct});sH.mem.push({ts:_ts,time:ts,value:memPct});sH.disk.push({ts:_ts,time:ts,value:diskPct});if(d.blocks&&d.mounts){var _usedMap={};for(var _mi=0;_mi<d.mounts.length;_mi++){var _mx=d.mounts[_mi];if(_mx.type!=='tmpfs'&&_mx.type!=='devtmpfs'&&_mx.type!=='overlay'&&_mx.type!=='proc'&&_mx.type!=='sysfs'&&_mx.type!=='cgroup2'&&_mx.type!=='cgroup'&&_mx.type!=='devpts'&&_mx.type!=='autofs'&&_mx.type!=='mqueue'&&_mx.type!=='pstore'&&_mx.type!=='bpf'&&_mx.type!=='debugfs'&&_mx.type!=='tracefs'&&_mx.type!=='configfs'&&_mx.type!=='hugetlbfs'&&_mx.type!=='fusectl'&&_mx.type!=='ramfs'&&_mx.size>0){var _f=_mx.fs;if(!_usedMap[_f]||_mx.size>_usedMap[_f].s)_usedMap[_f]={s:_mx.size,u:_mx.used};}}var _tUsed=0;for(var _fk in _usedMap)_tUsed+=_usedMap[_fk].u;var _rawDisks=[],_parts=[];for(var _bi=0;_bi<d.blocks.length;_bi++){var _bx=d.blocks[_bi];if(_bx.type==='disk')_rawDisks.push(_bx);else if(_bx.type==='part')_parts.push(_bx);}for(var _ri=0;_ri<_rawDisks.length;_ri++){var _rd=_rawDisks[_ri];var _pn=_rd.name,_ps=0;for(var _pi=0;_pi<_parts.length;_pi++){if(_parts[_pi].name.indexOf(_pn)===0)_ps+=_parts[_pi].size_bytes||0;}var _db=_rd.size_bytes||_ps||1,_up=_db>0?Math.round(_tUsed/_db*100):0;if(!sH.perDisk[_pn])sH.perDisk[_pn]=[];sH.perDisk[_pn].push({ts:_ts,time:ts,value:_up});}var _mntMap={};for(var _mi2=0;_mi2<d.mounts.length;_mi2++){var _mx2=d.mounts[_mi2];if(_mx2.type!=='tmpfs'&&_mx2.type!=='devtmpfs'&&_mx2.type!=='overlay'&&_mx2.type!=='proc'&&_mx2.type!=='sysfs'&&_mx2.type!=='cgroup2'&&_mx2.type!=='cgroup'&&_mx2.type!=='devpts'&&_mx2.type!=='autofs'&&_mx2.type!=='mqueue'&&_mx2.type!=='pstore'&&_mx2.type!=='bpf'&&_mx2.type!=='debugfs'&&_mx2.type!=='tracefs'&&_mx2.type!=='configfs'&&_mx2.type!=='hugetlbfs'&&_mx2.type!=='fusectl'&&_mx2.type!=='ramfs')_mntMap[_mx2.mnt]=_mx2;}for(var _li=0;_li<d.blocks.length;_li++){var _lx=d.blocks[_li];if(_lx.type==='lvm'&&_lx.mount&&_mntMap[_lx.mount]){var _ln=_lx.lv_name||_lx.name;var _lp=parseInt(_mntMap[_lx.mount].use)||0;if(!sH.perLvm[_ln])sH.perLvm[_ln]=[];sH.perLvm[_ln].push({ts:_ts,time:ts,value:_lp});}}}}
if(d.interfaces&&d.interfaces.length){var totalRate=0;for(var i=0;i<d.interfaces.length;i++){if(d.interfaces[i].name!=='lo')totalRate+=d.interfaces[i].rx_rate||0;}netRate=Math.round(totalRate/1024);sH.net.push({ts:_ts,time:ts,value:netRate});}
if(d.disk_io&&d.disk_io.length){var ioR=0,ioW=0;for(var io=0;io<d.disk_io.length;io++){ioR+=d.disk_io[io].read_rate||0;ioW+=d.disk_io[io].write_rate||0;}sH.diskIoR.push({ts:_ts,time:ts,value:Math.round(ioR/1024)});sH.diskIoW.push({ts:_ts,time:ts,value:Math.round(ioW/1024)});for(var _dio=0;_dio<d.disk_io.length;_dio++){var _d=d.disk_io[_dio];var _dn=_d.device;if(!sH.perDiskIO[_dn]){sH.perDiskIO[_dn]={read:[],write:[]};}sH.perDiskIO[_dn].read.push({ts:_ts,time:ts,value:Math.round((_d.read_rate||0)/1024)});sH.perDiskIO[_dn].write.push({ts:_ts,time:ts,value:Math.round((_d.write_rate||0)/1024)});}}if(d.php_fpm){sH.fpm.push({ts:_ts,time:ts,active:d.php_fpm.active||0,idle:d.php_fpm.idle||0,total:d.php_fpm.total||0});}
if(d.docker){var dkrCpuPct=d.docker.cpu_usage_pct!==undefined?Math.round(d.docker.cpu_usage_pct):0;var dkrMemPct=d.docker.memory_limit_bytes>0?Math.round(d.docker.memory_usage_bytes/d.docker.memory_limit_bytes*100):0;sH.dkrCPU.push({ts:_ts,time:ts,value:dkrCpuPct});sH.dkrMEM.push({ts:_ts,time:ts,value:dkrMemPct});}
var maxPts=500;['cpu','mem','disk','net','fpm','dkrCPU','dkrMEM','diskIoR','diskIoW'].forEach(function(k){if(sH[k].length>maxPts)sH[k]=sH[k].slice(-maxPts);});for(var _dn in sH.perDisk){if(sH.perDisk[_dn].length>maxPts)sH.perDisk[_dn]=sH.perDisk[_dn].slice(-maxPts);}for(var _ln in sH.perLvm){if(sH.perLvm[_ln].length>maxPts)sH.perLvm[_ln]=sH.perLvm[_ln].slice(-maxPts);}for(var _dn in sH.perDiskIO){if(sH.perDiskIO[_dn].read.length>maxPts){sH.perDiskIO[_dn].read=sH.perDiskIO[_dn].read.slice(-maxPts);sH.perDiskIO[_dn].write=sH.perDiskIO[_dn].write.slice(-maxPts);}}
    var fpmActive=d.php_fpm?d.php_fpm.active||0:0;var fpmIdle=d.php_fpm?d.php_fpm.idle||0:0;var fpmTotal=d.php_fpm?d.php_fpm.total||0:0;var dkrCpu=d.docker?Math.round(d.docker.cpu_usage_pct||0):0;var dkrMem=d.docker&&d.docker.memory_limit_bytes>0?Math.round(d.docker.memory_usage_bytes/d.docker.memory_limit_bytes*100):0;
    fetch(u('record_history'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({cpu:cpuPct,mem:memPct,disk:diskPct,net:netRate,fpm_active:fpmActive,fpm_idle:fpmIdle,fpm_total:fpmTotal,dkr_cpu:dkrCpu,dkr_mem:dkrMem})});
var trendSets=[{key:'cpu',id:'cpu',clr:'#a855f7',unit:'%'},{key:'mem',id:'mem',clr:'#22c55e',unit:'%'},{key:'net',id:'net',clr:'#06b6d4',unit:'KB/s'}];
for(var si=0;si<trendSets.length;si++){var st=trendSets[si];var arr=sH[st.key];var curEl=document.getElementById(st.id+'TrendCurrent');if(arr&&arr.length>0&&curEl){var vals=arr.map(function(p){return p.value;});var cur=vals[vals.length-1];curEl.textContent=st.unit==='KB/s'?(cur>1024?(cur/1024).toFixed(1)+'MB/s':cur+'KB/s'):cur+st.unit;}}
var _dcEl=document.getElementById('diskTrendCurrent');if(_dcEl){var _ioDn='';for(var _ioDk in sH.perDiskIO){_ioDn=_ioDk;break;}if(_ioDn&&sH.perDiskIO[_ioDn].read.length>0){var _ioV=sH.perDiskIO[_ioDn].read[sH.perDiskIO[_ioDn].read.length-1].value;_dcEl.textContent=_ioV>1024?(_ioV/1024).toFixed(1)+'MB/s':_ioV+'KB/s';}else _dcEl.textContent='--';}

for(var ci=0;ci<_trendControllers.length;ci++)if(_trendControllers[ci].refresh)_trendControllers[ci].refresh();
var dkrNetEl=document.getElementById('dockerNetCurrent');if(dkrNetEl&&sH.net.length>0){var nv=sH.net[sH.net.length-1].value;dkrNetEl.textContent=nv>1024?(nv/1024).toFixed(1)+'MB/s':nv+'KB/s';}
}
function renderMemDoughnut(d){
var c=document.getElementById('memDoughnut');if(!c||!d.memory)return;
var p=c.parentElement,rect=p.getBoundingClientRect();
if(rect.width<20||rect.height<20)return;
var w=Math.min(Math.floor(rect.width),400),h=160,dpr=window.devicePixelRatio||1;
c.width=w*dpr;c.height=h*dpr;c.style.width=w+'px';c.style.height=h+'px';
var cx=c.getContext('2d');cx.scale(dpr,dpr);
var tot=d.memory.total,used=d.memory.used,fr=d.memory.free,cached=d.memory.cached,buf=d.memory.buffers,avail=d.memory.available||0;
if(!tot)return;
var segs=[{label:'Used',val:used,clr:'#ef4444'},{label:'Cached',val:cached,clr:'#3b82f6'},{label:'Buf',val:buf,clr:'#f59e0b'},{label:'Free',val:fr,clr:'#22c55e'}];
var cx2=w/2,cy2=72,or=60,ir=30;
var totalAngle=-Math.PI/2;
cx.clearRect(0,0,w,h);
for(var si=0;si<segs.length;si++){
var s=segs[si],ang=(s.val/tot)*Math.PI*2;
if(ang>0){cx.beginPath();cx.arc(cx2,cy2,or,totalAngle,totalAngle+ang);cx.arc(cx2,cy2,ir,totalAngle+ang,totalAngle,true);cx.closePath();cx.fillStyle=s.clr;cx.fill();
totalAngle+=ang;}}
cx.shadowBlur=0;
    cx.fillStyle='rgba(255,255,255,0.9)';cx.font='bold 12px monospace';cx.textAlign='center';cx.textBaseline='middle';
    cx.fillText(Math.round(used/tot*100)+'%',cx2,cy2-4);
    cx.fillStyle='rgba(255,255,255,0.4)';cx.font='6px monospace';cx.fillText('used',cx2,cy2+8);
    var lx=4,ly=120;cx.font='6px monospace';cx.textBaseline='top';
    for(var si=0;si<segs.length;si++){
        cx.fillStyle=segs[si].clr;cx.fillRect(lx,ly,10,8);
        cx.fillStyle='rgba(255,255,255,0.6)';cx.font='5px monospace';cx.fillText(segs[si].label+' '+Math.round(segs[si].val/1048576)+'MB',lx+14,ly);
        ly+=9;}
    cx.fillStyle='rgba(255,255,255,0.3)';cx.font='5px monospace';cx.textAlign='right';cx.textBaseline='top';
    cx.fillText('Avail: '+Math.round(avail/1048576)+'MB',w-2,120);
    cx.fillText('Total: '+Math.round(tot/1048576)+'MB',w-2,130);}

function renderDiskUsage(d){
var c=document.getElementById('diskUsageChart');if(!c||!d.disk_overall)return;
var p=c.parentElement,rect=p.getBoundingClientRect();
if(rect.width<20)return;
var w=Math.min(Math.floor(rect.width),200),h=50,dpr=window.devicePixelRatio||1;
c.width=w*dpr;c.height=h*dpr;c.style.width=w+'px';c.style.height=h+'px';
var cx=c.getContext('2d');cx.scale(dpr,dpr);
cx.clearRect(0,0,w,h);
var total=d.disk_overall.total||0,used=d.disk_overall.used||0;
if(!total)return;
var free=total-used,usedPct=Math.round(used/total*100),freePct=100-usedPct;
var barW=w-50;
cx.fillStyle='rgba(255,255,255,0.06)';cx.fillRect(0,18,barW,14);
cx.fillStyle='#ef4444';cx.fillRect(0,18,barW*usedPct/100,14);
cx.fillStyle='#22c55e';cx.fillRect(barW*usedPct/100,18,barW*freePct/100,14);
    cx.fillStyle='rgba(255,255,255,0.7)';cx.font='6px monospace';cx.textAlign='left';
    cx.fillText('Used '+Math.round(used/1073741824)+'GB ('+usedPct+'%)',0,14);
    cx.fillStyle='rgba(255,255,255,0.4)';cx.textAlign='right';
    cx.fillText('Free '+Math.round(free/1073741824)+'GB',w-2,14);
    cx.font='5px monospace';cx.textAlign='center';
    cx.fillStyle='rgba(255,255,255,0.2)';cx.fillText('Total: '+Math.round(total/1073741824)+'GB',barW/2,44);}


function renderDockerGauges(d,cname){
var cpuPct=d.docker&&d.docker.cpu_usage_pct!==undefined?Math.round(d.docker.cpu_usage_pct):0;
var memPct=0;if(d.docker&&d.docker.memory_limit_bytes>0)memPct=Math.round(d.docker.memory_usage_bytes/d.docker.memory_limit_bytes*100);
var cpuClr=cpuPct>50?'#ef4444':cpuPct>20?'#f59e0b':'#22c55e';
var memClr=memPct>50?'#ef4444':memPct>20?'#f59e0b':'#22c55e';
drawGauge('dockerCpuGauge',cpuPct,'CPU',cpuClr,100);
drawGauge('dockerMemGauge',memPct,'MEM',memClr,100);
var totalNetBytes=0;if(d.interfaces){for(var i=0;i<d.interfaces.length;i++)if(d.interfaces[i].name!=='lo')totalNetBytes+=d.interfaces[i].rx_rate+d.interfaces[i].tx_rate;}
var netMbps=totalNetBytes>0?(totalNetBytes/125000).toFixed(1):'0.0';
var netPct=Math.min(100,Math.round(totalNetBytes/1250000));
var netClr=netPct>50?'#ef4444':netPct>20?'#f59e0b':'#06b6d4';
drawGauge('dockerNetGauge',netPct,'NET',netClr,100);
var netEl=document.getElementById('dockerNetCurrent');if(netEl)netEl.textContent=netMbps+' Mbps';}

function renderFpmWorkerChart(){
var c=document.getElementById('fpmWorkerChart');if(!c||!sH.fpm||sH.fpm.length<2)return;
var arr=sH.fpm;var labels=[],actives=[],idles=[],totals=[];
for(var i=0;i<arr.length;i++){labels.push(arr[i].time);actives.push(arr[i].active);idles.push(arr[i].idle);totals.push(arr[i].active+arr[i].idle);}
if(window._fpmChart){window._fpmChart.data.labels=labels;window._fpmChart.data.datasets[0].data=actives;window._fpmChart.data.datasets[1].data=idles;window._fpmChart.data.datasets[2].data=totals;window._fpmChart.update('none');return;}
window._fpmChart=new Chart(c,{type:'line',data:{labels:labels,datasets:[{label:'Active',data:actives,borderColor:'#3b82f6',backgroundColor:'rgba(59,130,246,0.1)',fill:true,tension:0.3,borderWidth:2,pointRadius:2.5,pointHoverRadius:5,pointBackgroundColor:'#3b82f6',pointBorderColor:'#fff',pointBorderWidth:1},{label:'Idle',data:idles,borderColor:'#22c55e',backgroundColor:'rgba(34,197,94,0.08)',fill:true,tension:0.3,borderWidth:2,pointRadius:2.5,pointHoverRadius:5,pointBackgroundColor:'#22c55e',pointBorderColor:'#fff',pointBorderWidth:1},{label:'Total',data:totals,borderColor:'#6b7280',backgroundColor:'transparent',fill:false,tension:0.3,borderWidth:1,borderDash:[3,3],pointRadius:0}]},options:{responsive:true,maintainAspectRatio:false,layout:{padding:{top:6}},interaction:{mode:'index',intersect:false},plugins:{legend:{position:'top',labels:{font:{size:9},boxWidth:8,boxHeight:6,padding:6,color:'#374151'}},datalabels:{display:false},tooltip:{backgroundColor:'#1e293b',titleFont:{size:10},bodyFont:{size:9},padding:6,callbacks:{label:function(it){return it.dataset.label+': '+it.parsed.y;}}}},scales:{x:{display:true,ticks:{font:{size:8},color:'#6b7280',maxTicksLimit:6},grid:{display:false}},y:{beginAtZero:true,ticks:{font:{size:8},color:'#6b7280',precision:0},grid:{color:'rgba(0,0,0,0.06)'}}},animation:{duration:400,easing:'easeOutQuart'}}});}

function renderContainerStats(containers){
var el=document.getElementById('containerStatsList');if(!el||!containers||!containers.length){if(el)el.innerHTML='';return;}
var html='<div style="font-size:var(--font-xs);font-weight:600;margin-bottom:2px;">CONTAINERS</div>';
for(var ci=0;ci<containers.length;ci++){
var ct=containers[ci];if(!ct.container_id)continue;
var memPct=ct.memory_limit_bytes>0&&ct.memory_limit_bytes<9e18?Math.round(ct.memory_usage_bytes/ct.memory_limit_bytes*100):0;
var memUsed=Math.round(ct.memory_usage_bytes/1048576);
var memTot=ct.memory_limit_bytes>0&&ct.memory_limit_bytes<9e18?Math.round(ct.memory_limit_bytes/1048576)+'MB':'unl.';
var cpu=ct.cpu_usage_pct!==undefined?ct.cpu_usage_pct.toFixed(2)+'%':'--';
var cpuClr=ct.cpu_usage_pct>50?'#ef4444':ct.cpu_usage_pct>20?'#f59e0b':'#a855f7';
html+='<div class="d-flex align-items-center gap-2 py-1" style="border-bottom:1px solid rgba(255,255,255,0.04);font-size:var(--font-xs);">';
html+='<span class="font-tech fw-bold" style="color:'+(ct.is_current?'#3b82f6':'#8b5cf6')+';">'+ct.container_name+'</span>';
html+='<span class="font-tech" style="color:'+cpuClr+';">CPU: '+cpu+'</span>';
html+='<span class="font-tech" style="color:#22c55e;">MEM: '+memUsed+'MB / '+memTot+' ('+memPct+'%)</span>';
html+='<span class="font-tech text-muted">PIDS: '+ct.pids_current+'</span>';
html+='</div>';}
el.innerHTML=html;}
function renderContainerResourceCards(containers){
var el=document.getElementById('containerResourceCards');if(!el)return;
if(!containers||!containers.length){el.innerHTML='<div style="font-size:10px;color:var(--text-muted);">No containers</div>';return;}
var html='';
for(var ci=0;ci<containers.length;ci++){
var ct=containers[ci];if(!ct.container_id)continue;
var memPct=ct.memory_limit_bytes>0&&ct.memory_limit_bytes<9e18?Math.round(ct.memory_usage_bytes/ct.memory_limit_bytes*100):0;
var memUsed=Math.round(ct.memory_usage_bytes/1048576);
var memTot=ct.memory_limit_bytes>0&&ct.memory_limit_bytes<9e18?Math.round(ct.memory_limit_bytes/1048576)+'MB':'unl.';
var cpuPct=ct.cpu_usage_pct!==undefined?Math.round(ct.cpu_usage_pct):0;
var cpuClr=cpuPct>50?'#ef4444':cpuPct>20?'#f59e0b':'#22c55e';
var memClr=memPct>50?'#ef4444':memPct>20?'#f59e0b':'#22c55e';
html+='<div style="flex:1;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:4px;padding:4px 6px;display:flex;align-items:center;gap:6px;">';
html+='<div style="width:2px;height:28px;border-radius:2px;background:'+(ct.is_current?'#3b82f6':'#8b5cf6')+';flex-shrink:0;"></div>';
html+='<div style="flex:1;min-width:0;">';
html+='<div class="font-tech fw-bold" style="font-size:10px;color:'+(ct.is_current?'#3b82f6':'#8b5cf6')+';">'+ct.container_name+'</div>';
html+='<div style="display:flex;gap:8px;margin-top:1px;">';
html+='<span style="font-size:10px;display:flex;align-items:center;gap:2px;"><span style="color:var(--text-muted);">CPU</span><span class="font-tech" style="color:'+cpuClr+';font-weight:600;">'+cpuPct+'%</span></span>';
html+='<span style="font-size:10px;display:flex;align-items:center;gap:2px;"><span style="color:var(--text-muted);">MEM</span><span class="font-tech" style="color:'+memClr+';font-weight:600;">'+memUsed+'/'+memTot+'</span></span>';
html+='<span style="font-size:10px;display:flex;align-items:center;gap:2px;"><span style="color:var(--text-muted);">PIDS</span><span class="font-tech" style="color:var(--text-muted);font-weight:600;">'+ct.pids_current+'</span></span>';
html+='</div></div></div>';}
el.innerHTML=html;}

function loadSystemInfo(){fetch(u('system_info'),{method:'POST',headers:{'Content-Type':'application/json'}}).then(function(r){return r.json();}).then(function(d){
if(!d.success)return;
pushSysHistory(d);
    if(!window._histLoaded){window._histLoaded=true;fetch(u('get_history'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({hours:48})}).then(function(r){return r.json();}).then(function(h){if(h.success&&h.entries&&h.entries.length){for(var hi=0;hi<h.entries.length;hi++){var he=h.entries[hi];var _ts2=he.ts*1000;var ht=new Date(_ts2);var hts=('0'+ht.getHours()).slice(-2)+':'+('0'+ht.getMinutes()).slice(-2)+':'+('0'+ht.getSeconds()).slice(-2);if(he.cpu!=null)sH.cpu.push({ts:_ts2,time:hts,value:he.cpu});if(he.mem!=null)sH.mem.push({ts:_ts2,time:hts,value:he.mem});if(he.disk!=null)sH.disk.push({ts:_ts2,time:hts,value:he.disk});if(he.net!=null)sH.net.push({ts:_ts2,time:hts,value:he.net});if(he.fpm_active!=null&&he.fpm_idle!=null&&he.fpm_total!=null)sH.fpm.push({ts:_ts2,time:hts,active:he.fpm_active,idle:he.fpm_idle,total:he.fpm_total});if(he.dkr_cpu!=null)sH.dkrCPU.push({ts:_ts2,time:hts,value:he.dkr_cpu});if(he.dkr_mem!=null)sH.dkrMEM.push({ts:_ts2,time:hts,value:he.dkr_mem});}for(var ci=0;ci<_trendControllers.length;ci++)if(_trendControllers[ci].refresh)_trendControllers[ci].refresh();}});}
if(d.display_containers)renderContainerResourceCards(d.display_containers);
var cidEl=document.getElementById('appContainerId');if(cidEl)cidEl.textContent=d.docker&&d.docker.container_id?d.docker.container_id.substr(0,12):'--';
var imgEl=document.getElementById('appContainerImage');if(imgEl&&d.docker&&d.docker.image_name)imgEl.textContent=d.docker.image_name;
var nameEl=document.getElementById('appContainerName');if(nameEl&&d.docker&&d.docker.container_name)nameEl.textContent=d.docker.container_name;
var cname=d.hostname||(d.docker&&d.docker.container_id?d.docker.container_id.substr(0,12):'app');
var fpmEl=document.getElementById('appFpmWorkers');if(fpmEl&&d.php_fpm)fpmEl.textContent='total: '+d.php_fpm.total+' | active: '+d.php_fpm.active+' | idle: '+d.php_fpm.idle+' | master: '+d.php_fpm.master_pid;
renderFpmWorkerChart();
if(!window._tcInit){window._tcInit=true;
initRangeButtons('systemTrendRange');
initRangeButtons('containerTrendRange');
var _tcCfgs=[
{canvasId:'cpuTrendChart',exportBtnId:'systemTrendExport',getSeries:function(){var rng=_trendRange||30;return[{data:filterByRange(sH.cpu,rng),color:'#a855f7',label:'CPU',unit:'%'},{data:filterByRange(sH.mem,rng),color:'#22c55e',label:'MEM',unit:'%'}];}},
{canvasId:'netTrendChart',exportBtnId:'systemTrendExport',getSeries:function(){var rng=_trendRange||30;return[{data:filterByRange(sH.net,rng),color:'#06b6d4',label:'NET',unit:'KB/s'}];}},
{canvasId:'diskTrendChart',exportBtnId:'systemTrendExport',getSeries:function(){var rng=_trendRange||30,dp=['#22c55e','#3b82f6','#a855f7','#f59e0b','#ec4899','#14b8a6','#f97316','#6366f1','#84cc16','#06b6d4','#d946ef','#8b5cf6'],di=0,series=[];for(var dn in sH.perDiskIO){var d=sH.perDiskIO[dn];if(d.read.length>=2){series.push({data:filterByRange(d.read,rng),color:dp[di%dp.length],label:dn+'-R',unit:'KB/s',lineWidth:2});}if(d.write.length>=2){series.push({data:filterByRange(d.write,rng),color:dp[di%dp.length],label:dn+'-W',unit:'KB/s',lineWidth:1.5,dashed:true});}di++;}return series;}},
{canvasId:'diskUsageTrendChart',exportBtnId:'systemTrendExport',getSeries:function(){var rng=_trendRange||30,dp=['#3b82f6','#a855f7','#ec4899','#14b8a6','#f97316','#6366f1','#84cc16','#06b6d4','#d946ef','#8b5cf6','#10b981','#e11d48'],di=0,series=[];for(var dn in sH.perDisk){if(sH.perDisk[dn].length>=2){series.push({data:filterByRange(sH.perDisk[dn],rng),color:dp[di%dp.length],label:dn,unit:'%',lineWidth:2.2});di++;}}var _dmC=['#fb923c','#c084fc','#f472b6','#2dd4bf','#a78bfa','#34d399','#fbbf24','#818cf8','#fb7185','#38bdf8'];var _dmi=0;for(var ln in sH.perLvm){if(sH.perLvm[ln].length>=2){series.push({data:filterByRange(sH.perLvm[ln],rng),color:_dmC[_dmi%_dmC.length],label:ln,unit:'%',lineWidth:1,dashed:true});_dmi++;}}return series;}},
{canvasId:'dockerCpuTrend',exportBtnId:'containerTrendExport',getSeries:function(){var rng=_trendRange||30;return[{data:filterByRange(sH.dkrCPU,rng),color:'#a855f7',label:'CPU',unit:'%'},{data:filterByRange(sH.dkrMEM,rng),color:'#22c55e',label:'MEM',unit:'%'},{data:filterByRange(sH.net,rng),color:'#06b6d4',label:'NET',unit:'KB/s'}];}}];
for(var _tci=0;_tci<_tcCfgs.length;_tci++){var _tc=setupTrendChart(_tcCfgs[_tci]);if(_tc)_tc.refresh();}
}
var appCpuEl=document.getElementById('appCpuUsage');if(appCpuEl&&d.docker){var cpuPct=d.docker.cpu_usage_pct!==undefined?d.docker.cpu_usage_pct+'%':'--';appCpuEl.textContent=cpuPct;}
var appMemEl=document.getElementById('appMemUsage');if(appMemEl&&d.docker){var mu=Math.round(d.docker.memory_usage_bytes/1048576);var ml=d.docker.memory_limit_bytes>0?Math.round(d.docker.memory_limit_bytes/1048576)+'MB':'--';var mp=d.docker.memory_limit_bytes>0?Math.round(d.docker.memory_usage_bytes/d.docker.memory_limit_bytes*100):0;appMemEl.textContent=mu+'/'+ml+' ('+mp+'%)';}
var vt=document.getElementById('appVolTable');if(vt&&d.docker&&d.docker.volumes){var vh='';var _volColors=['#3b82f6','#8b5cf6','#ec4899','#f59e0b','#14b8a6','#f97316','#6366f1','#84cc16','#06b6d4','#d946ef'];var _vcIdx=0;for(var vi=0;vi<d.docker.volumes.length;vi++){var v=d.docker.volumes[vi];if(!v.size)continue;var vs=Math.round(v.size/1073741824)+'GB';var vp=Math.round((v.size-v.free)/v.size*100);var vc=_volColors[_vcIdx%_volColors.length];_vcIdx++;var src=v.source||'';var parts=src.split('/');var vn=parts.pop()||src;vh+='<div style="display:flex;align-items:center;gap:4px;padding:2px 0;border-bottom:1px solid rgba(255,255,255,0.04);">';vh+='<span class="font-tech" style="min-width:42px;font-size:var(--font-xs);font-weight:600;color:'+vc+';">'+vn+'</span>';vh+='<div style="flex:1;height:5px;background:rgba(255,255,255,0.06);border-radius:3px;"><div style="width:'+Math.min(vp,100)+'%;height:100%;background:'+vc+';border-radius:3px;"></div></div>';vh+='<span class="font-tech" style="width:32px;text-align:right;font-size:var(--font-xs);color:'+vc+';">'+vp+'%</span>';vh+='<span class="font-tech" style="width:36px;text-align:right;font-size:var(--font-xs);color:var(--text-muted);">'+vs+'</span>';vh+='</div>';}vt.innerHTML=vh||'<div style="font-size:var(--font-xs);color:var(--text-muted);">No mapped volumes</div>';}
renderDockerGauges(d,cname);
var hnEl=document.getElementById('sysHostname');if(hnEl)hnEl.textContent=d.hostname||'--';
var osEl=document.getElementById('sysOs');if(osEl)osEl.textContent=d.os||'--';
var cidEl2=document.getElementById('appContainerId');if(cidEl2)cidEl2.textContent=d.docker&&d.docker.container_id?d.docker.container_id.substr(0,12):'--';
var appSt2=document.getElementById('appContainerStatus2');if(appSt2&&d.container_uptime_seconds){var s=d.container_uptime_seconds;var days=Math.floor(s/86400),hr=Math.floor((s%86400)/3600),min=Math.floor((s%3600)/60);appSt2.textContent='Up '+days+'d '+hr+'h '+min+'m';}else if(appSt2)appSt2.textContent='Up --';
var hsDot=document.getElementById('sysHealthDot2'),hsScore=document.getElementById('sysHealthScore'),hsLabel=document.getElementById('sysHealthLabel');
if(hsDot||hsScore||hsLabel){var cpuPct=d.cpu&&d.cpu.usage||0;var memPct=d.memory&&d.memory.total>0?Math.round(d.memory.used/d.memory.total*100):0;var diskPct=d.disk_overall&&d.disk_overall.total>0?Math.round(d.disk_overall.used/d.disk_overall.total*100):0;
var score=100;if(cpuPct>80)score-=30;else if(cpuPct>50)score-=15;else if(cpuPct>30)score-=5;if(memPct>80)score-=30;else if(memPct>50)score-=15;else if(memPct>30)score-=5;if(diskPct>80)score-=20;else if(diskPct>60)score-=10;else if(diskPct>40)score-=5;score=Math.max(0,score);
var sc=score>=80?'#22c55e':score>=50?'#f59e0b':'#ef4444';var sl=score>=80?'Healthy':score>=50?'Warning':'Critical';
if(hsDot)hsDot.style.background=sc;if(hsScore){hsScore.textContent=score+'%';hsScore.style.color=sc;}if(hsLabel)hsLabel.textContent=sl+' | CPU:'+cpuPct+'% MEM:'+memPct+'% DISK:'+diskPct+'%';}

var dkTotal=document.getElementById('dockerDiskTotal');var dkUsed=document.getElementById('dockerDiskUsed');var dkFree=document.getElementById('dockerDiskFree');var dkBar=document.getElementById('dockerDiskBar');var dkPct=document.getElementById('dockerDiskPct');
if(d.disk_overall&&dkTotal&&dkUsed&&dkFree&&dkBar){
var dt=Math.round(d.disk_overall.total/1073741824);var du=Math.round(d.disk_overall.used/1073741824);var df=Math.round(d.disk_overall.free/1073741824);
var dp=d.disk_overall.total>0?Math.round(d.disk_overall.used/d.disk_overall.total*100):0;
var duClr=dp>80?'#ef4444':dp>50?'#f59e0b':'#3b82f6';
dkTotal.textContent='Total: '+dt+'GB';dkUsed.textContent='Used: '+du+'GB';dkUsed.style.color=duClr;dkFree.textContent='Free: '+df+'GB';
if(dkPct)dkPct.textContent=dp+'%';dkPct.style.color=duClr;
dkBar.style.width=Math.min(dp,100)+'%';dkBar.style.background=duClr;}
var upEl=document.getElementById('sysUptime');if(upEl&&d.uptime_seconds){var s=d.uptime_seconds;var days=Math.floor(s/86400),hr=Math.floor((s%86400)/3600),min=Math.floor((s%3600)/60);upEl.textContent=days+'d '+hr+'h '+min+'m';}else if(upEl)upEl.textContent='--';
var prEl=document.getElementById('sysProcs');if(prEl)prEl.textContent=d.process_count+' | FPM: '+(d.php_fpm?d.php_fpm.total+'w':'?');if(prEl&&d.tcp_established!==undefined)prEl.textContent+=' | TCP: '+d.tcp_established+'e';
var krEl=document.getElementById('sysKernel');if(krEl&&d.kernel){var m=d.kernel.match(/Linux version [\d.]+/);krEl.textContent=m?m[0]:'--';}else if(krEl)krEl.textContent='--';
if(d.cpu){var cpuEl=document.getElementById('sysCpuTab');if(cpuEl)cpuEl.textContent=d.cpu.usage+'%';var cpuLoad=document.getElementById('cpuLoad');if(cpuLoad){var dkr=d.docker;cpuLoad.textContent='load: '+d.cpu.load.join(', ')+' | cores: '+d.cpu.cores+' | '+(dkr&&dkr.cpu_quota>0?'capped':'unlimited');}var cpuPct=d.cpu.usage||0;var cpuClr=cpuPct>60?'#ef4444':cpuPct>30?'#f59e0b':'#22c55e';drawGauge('sysCpuGauge',cpuPct,'CPU',cpuClr,120);}
if(d.memory){var memEl=document.getElementById('sysMemTab');if(memEl){var pct=Math.round(d.memory.used/d.memory.total*100);memEl.textContent=pct+'%';}var memD=document.getElementById('memDetail');if(memD){var tg=Math.round(d.memory.total/1048576);var ug=Math.round(d.memory.used/1048576);var MemAvail=d.memory.available?Math.round(d.memory.available/1048576):0;memD.textContent=ug+'/'+tg+'GB';}var swEl=document.getElementById('sysSwap');if(swEl&&d.swap){var st=Math.round(d.swap.total/1048576);var su=Math.round(d.swap.used/1048576);swEl.textContent='Swap: '+su+'/'+st+'GB'+(d.swap.total>0?' ('+Math.round(d.swap.used/d.swap.total*100)+'%)':' disabled');}var memPct=Math.round(d.memory.used/d.memory.total*100);var memClr=memPct>60?'#ef4444':memPct>30?'#f59e0b':'#22c55e';drawGauge('sysMemGauge',memPct,'MEM',memClr,120);}
if(d.disk_overall&&d.disk_overall.total){var dtEl=document.getElementById('diskTreeContent');var _diskPct=0;if(dtEl&&d.blocks&&d.mounts){var mntMap={};for(var mi=0;mi<d.mounts.length;mi++){var m=d.mounts[mi];if(m.type!=='tmpfs'&&m.type!=='devtmpfs'&&m.type!=='overlay'&&m.type!=='proc'&&m.type!=='sysfs'&&m.type!=='cgroup2'&&m.type!=='cgroup'&&m.type!=='devpts'&&m.type!=='autofs'&&m.type!=='mqueue'&&m.type!=='pstore'&&m.type!=='bpf'&&m.type!=='debugfs'&&m.type!=='tracefs'&&m.type!=='configfs'&&m.type!=='hugetlbfs'&&m.type!=='fusectl'&&m.type!=='ramfs')mntMap[m.mnt]=m;}var disks=[],lvs=[];for(var bi=0;bi<d.blocks.length;bi++){var b2=d.blocks[bi];if(b2.type==='disk'||b2.type==='part')disks.push(b2);else if(b2.type==='lvm')lvs.push(b2);}if(disks.length){var sda=null,parts=[];for(var di=0;di<disks.length;di++){if(disks[di].type==='disk'){if(!sda)sda=disks[di];}else parts.push(disks[di]);}if(sda){var totalBytes=0;for(var pi=0;pi<parts.length;pi++)totalBytes+=parts[pi].size_bytes||0;var sdaBytes=0;for(var di=0;di<disks.length;di++){if(disks[di].type==='disk'){var _db=disks[di].size_bytes||0;if(_db===0){for(var pi=0;pi<parts.length;pi++)if(parts[pi].name.indexOf(disks[di].name)===0)_db+=parts[pi].size_bytes||0;}sdaBytes+=_db;}}var usedMap={};for(var xi=0;xi<d.mounts.length;xi++){var mx=d.mounts[xi];if(mx.type!=='tmpfs'&&mx.type!=='devtmpfs'&&mx.type!=='overlay'&&mx.type!=='proc'&&mx.type!=='sysfs'&&mx.type!=='cgroup2'&&mx.type!=='cgroup'&&mx.type!=='devpts'&&mx.type!=='autofs'&&mx.type!=='mqueue'&&mx.type!=='pstore'&&mx.type!=='bpf'&&mx.type!=='debugfs'&&mx.type!=='tracefs'&&mx.type!=='configfs'&&mx.type!=='hugetlbfs'&&mx.type!=='fusectl'&&mx.type!=='ramfs'&&mx.size>0){var f=mx.fs;if(!usedMap[f]||mx.size>usedMap[f].s)usedMap[f]={s:mx.size,u:mx.used};}var tUsed=0;for(var fk in usedMap)tUsed+=usedMap[fk].u;var up3=sdaBytes>0?Math.round(tUsed/sdaBytes*100):0;_diskPct=up3;var uc3=up3>80?'#ef4444':up3>50?'#f59e0b':'#22c55e';var sEl=document.getElementById('sysDiskTab');if(sEl)sEl.textContent=up3+'%';var dDt=document.getElementById('diskDetail');if(dDt){var dg=sdaBytes>1073741824?(sdaBytes/1073741824).toFixed(1)+'G':(sdaBytes/1048576).toFixed(1)+'M';var dug=tUsed>1073741824?(tUsed/1073741824).toFixed(1)+'G':tUsed>1048576?(tUsed/1048576).toFixed(1)+'M':(tUsed/1024).toFixed(1)+'K';dDt.textContent=dug+'/'+dg;}var dMnt=document.getElementById('diskMnt');if(dMnt){var mntHtml='sda ('+sda.size+')';if(parts.length&&totalBytes>0){mntHtml+=' &bull; ';for(var pi=0;pi<parts.length;pi++){mntHtml+=parts[pi].name+' '+parts[pi].size;if(pi<parts.length-1)mntHtml+=' &bull; ';}}}}}
// LVM-only gauges for PHYSICAL DISKS & LVM card
var lvmItems=[];for(var li=0;li<lvs.length;li++){var b3=lvs[li];var pct=-1;if(b3.mount&&mntMap[b3.mount])pct=mntMap[b3.mount].use_pct;var lvName=b3.lv_name||b3.name;lvmItems.push({name:lvName,size:b3.size,pct:pct,id:'lv_'+lvName.replace(/[^a-zA-Z0-9]/g,'_'),mount:b3.mount,type:'lvm'});}
if(lvmItems.length){var gHtml='<div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;">';for(var gi=0;gi<lvmItems.length;gi++){var gi2=lvmItems[gi];var gClr=gi2.pct>=0?(gi2.pct>80?"#ef4444":gi2.pct>50?"#f59e0b":"#22c55e"):"#475569";gHtml+='<div style="flex:1;min-width:110px;max-width:160px;padding:6px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:6px;text-align:center;">';gHtml+='<div class="font-tech fw-bold" style="font-size:10px;color:#8b5cf6;word-break:break-all;max-width:100%;margin:0 auto 2px;">'+gi2.name+'</div>';gHtml+='<div style="width:90px;height:90px;margin:0 auto;"><canvas id="dg_'+gi2.id+'" style="width:90px;height:90px;"></canvas></div>';if(gi2.pct>=0)gHtml+='<div class="font-tech" style="font-size:12px;color:'+gClr+';margin:2px 0;">'+gi2.pct+'%</div>';else gHtml+='<div class="font-tech" style="font-size:10px;color:#64748b;">--</div>';gHtml+='<div style="font-size:9px;color:#94a3b8;">'+gi2.size+'</div>';if(gi2.mount)gHtml+='<div style="font-size:8px;color:#64748b;word-break:break-all;max-width:100%;margin:0 auto;">'+gi2.mount+'</div>';gHtml+='</div>';}gHtml+='</div>';dtEl.innerHTML=gHtml;}else{dtEl.innerHTML='<div class="text-muted small">No LVM devices.</div>';}
requestAnimationFrame(function(){for(var gi=0;gi<lvmItems.length;gi++){var gi3=lvmItems[gi];var gClr2=gi3.pct>=0?(gi3.pct>80?"#ef4444":gi3.pct>50?"#f59e0b":"#22c55e"):"#475569";drawGauge("dg_"+gi3.id,gi3.pct>=0?gi3.pct:0,gi3.name,gClr2,100);}});
// Per-disk gauges in top row (sysDiskGaugesContainer)
var sdgEl=document.getElementById('sysDiskGaugesContainer');if(sdgEl&&disks.length){var dHtml='';var pl2=['#3b82f6','#ef4444','#22c55e','#f59e0b','#8b5cf6','#14b8a6','#f97316'];for(var di=0;di<disks.length;di++){var rd=disks[di];var _db=rd.size_bytes||0;var _pu=0;var _pt=0;for(var pi=0;pi<d.blocks.length;pi++){var bp=d.blocks[pi];if(bp.type==='part'&&bp.name.indexOf(rd.name)===0){_pt+=bp.size_bytes||0;if(bp.mount&&mntMap[bp.mount]){var _fs=mntMap[bp.mount].fs;for(var xi=0;xi<d.mounts.length;xi++){var mx=d.mounts[xi];if(mx.type!=='tmpfs'&&mx.type!=='devtmpfs'&&mx.type!=='overlay'&&mx.type!=='proc'&&mx.type!=='sysfs'&&mx.type!=='cgroup2'&&mx.type!=='cgroup'&&mx.type!=='devpts'&&mx.type!=='autofs'&&mx.type!=='mqueue'&&mx.type!=='pstore'&&mx.type!=='bpf'&&mx.type!=='debugfs'&&mx.type!=='tracefs'&&mx.type!=='configfs'&&mx.type!=='hugetlbfs'&&mx.type!=='fusectl'&&mx.type!=='ramfs'&&mx.size>0&&mx.fs===_fs)_pu+=mx.used;}}}}if(_db===0)_db=_pt||1;var dp=_db>0?Math.round(_pu/_db*100):0;var dc=dp>80?'#ef4444':dp>50?'#f59e0b':'#22c55e';dHtml+='<div style="text-align:center;width:65px;"><canvas id="sdg_'+rd.name+'" style="width:60px;height:60px;"></canvas><div class="font-tech" style="font-size:9px;color:'+dc+';font-weight:600;">'+rd.name+' '+dp+'%</div></div>';}sdgEl.innerHTML=dHtml;requestAnimationFrame(function(){for(var di=0;di<disks.length;di++){var rd=disks[di];var _db=rd.size_bytes||0;var _pu=0;var _pt=0;for(var pi=0;pi<d.blocks.length;pi++){var bp=d.blocks[pi];if(bp.type==='part'&&bp.name.indexOf(rd.name)===0){_pt+=bp.size_bytes||0;if(bp.mount&&mntMap[bp.mount]){var _fs=mntMap[bp.mount].fs;for(var xi=0;xi<d.mounts.length;xi++){var mx=d.mounts[xi];if(mx.type!=='tmpfs'&&mx.type!=='devtmpfs'&&mx.type!=='overlay'&&mx.type!=='proc'&&mx.type!=='sysfs'&&mx.type!=='cgroup2'&&mx.type!=='cgroup'&&mx.type!=='devpts'&&mx.type!=='autofs'&&mx.type!=='mqueue'&&mx.type!=='pstore'&&mx.type!=='bpf'&&mx.type!=='debugfs'&&mx.type!=='tracefs'&&mx.type!=='configfs'&&mx.type!=='hugetlbfs'&&mx.type!=='fusectl'&&mx.type!=='ramfs'&&mx.size>0&&mx.fs===_fs)_pu+=mx.used;}}}}if(_db===0)_db=_pt||1;var dp=_db>0?Math.round(_pu/_db*100):0;var dc=dp>80?'#ef4444':dp>50?'#f59e0b':'#22c55e';drawGauge('sdg_'+rd.name,dp,rd.name,dc,60);}});}}
var nt=document.getElementById('netTable');if(nt&&d.interfaces){var nh='';for(var i=0;i<d.interfaces.length;i++){var ni=d.interfaces[i];var rx=ni.rx_bytes>1073741824?(ni.rx_bytes/1073741824).toFixed(1)+'GB':ni.rx_bytes>1048576?(ni.rx_bytes/1048576).toFixed(1)+'MB':(ni.rx_bytes/1024).toFixed(1)+'KB';var tx=ni.tx_bytes>1073741824?(ni.tx_bytes/1073741824).toFixed(1)+'GB':ni.tx_bytes>1048576?(ni.tx_bytes/1048576).toFixed(1)+'MB':(ni.tx_bytes/1024).toFixed(1)+'KB';var rxRate=ni.rx_rate!==undefined?(ni.rx_rate>1048576?(ni.rx_rate/1048576).toFixed(2)+'MB/s':(ni.rx_rate/1024).toFixed(1)+'KB/s'):'-';var txRate=ni.tx_rate!==undefined?(ni.tx_rate>1048576?(ni.tx_rate/1048576).toFixed(2)+'MB/s':(ni.tx_rate/1024).toFixed(1)+'KB/s'):'-';nh+='<tr><td class="font-tech fw-bold">'+ni.name+'</td><td class="font-tech">'+rx+'</td><td class="font-tech">'+tx+'</td><td class="font-tech text-info">'+rxRate+'</td><td class="font-tech text-warning">'+txRate+'</td></tr>';}nt.innerHTML=nh;}
var gwEl=document.getElementById('netGatewayInfo');if(gwEl){var gwTxt=d.gateway_ip?'GW: '+d.gateway_ip:'GW: --';if(d.listening_ports&&d.listening_ports.length)gwTxt+=' | Listening: '+d.listening_ports.join(', ');gwEl.textContent=gwTxt;}

var tcpTot=document.getElementById('sysTcpTotal');if(tcpTot&&d.tcp_states){var tcpS=d.tcp_states;tcpTot.textContent='Total: '+d.tcp_connections;(function(id,v){var e=document.getElementById(id);if(e)e.textContent=v||'0';})('tcpEstablished',tcpS.established);(function(id,v){var e=document.getElementById(id);if(e)e.textContent=v||'0';})('tcpTimeWait',tcpS.time_wait);(function(id,v){var e=document.getElementById(id);if(e)e.textContent=v||'0';})('tcpCloseWait',tcpS.close_wait);(function(id,v){var e=document.getElementById(id);if(e)e.textContent=v||'0';})('tcpFinWait',tcpS.fin_wait);(function(id,v){var e=document.getElementById(id);if(e)e.textContent=v||'0';})('tcpSynSent',tcpS.syn_sent);}
var dIoBody=document.getElementById('diskIoBody');if(dIoBody&&d.disk_io){var dih='';for(var di=0;di<d.disk_io.length;di++){var di2=d.disk_io[di];dih+='<div class="d-flex justify-content-between py-1 border-bottom border-secondary border-opacity-10"><span class="fw-bold font-tech">'+di2.device+'</span><span class="text-muted">R:'+di2.reads+'</span><span class="text-info">'+di2.read_rate+'/s</span><span class="text-muted">W:'+di2.writes+'</span><span class="text-warning">'+di2.write_rate+'/s</span></div>';}dIoBody.innerHTML=dih;var dIoTot=document.getElementById('sysDiskIoTotal');if(dIoTot&&d.disk_io.length){var totR=0,totW=0;for(var di=0;di<d.disk_io.length;di++){totR+=d.disk_io[di].read_rate;totW+=d.disk_io[di].write_rate;}dIoTot.textContent=totR+totW>0?totR+'/'+totW+' IOPS':'idle';}}
var lrEl=document.getElementById('sysLastRefreshed');if(lrEl){var now2=new Date();lrEl.textContent=('0'+now2.getHours()).slice(-2)+':'+('0'+now2.getMinutes()).slice(-2)+':'+('0'+now2.getSeconds()).slice(-2);lrEl.style.transition='color 0.5s';lrEl.style.color='#22c55e';setTimeout(function(){lrEl.style.color='#94a3b8';},1000);}
renderProcs(d.processes_top_cpu,d.processes_top_mem);
var mt=document.getElementById('diskMountTable');if(mt&&d.mounts){var mh='';for(var i=0;i<d.mounts.length;i++){var m=d.mounts[i];var sz=m.size_fmt||m.size,us=m.used_fmt||m.used,av=m.avail_fmt||m.avail;mh+='<tr'+(parseInt(m.use)>90?' class="table-danger"':'')+'><td class="font-tech">'+m.fs+'</td><td>'+m.type+'</td><td class="font-tech">'+sz+'</td><td class="font-tech">'+us+'</td><td class="font-tech">'+av+'</td><td class="font-tech fw-bold '+(parseInt(m.use)>90?'text-danger':'text-success')+'">'+m.use+'</td><td>'+m.mnt+'</td></tr>';}mt.innerHTML=mh;}
var bt=document.getElementById('diskBlockTable');if(bt&&d.blocks){var bh='';for(var i=0;i<d.blocks.length;i++){var b=d.blocks[i];if(b.type==='disk'||b.type==='part'){bh+='<tr><td class="font-tech fw-bold">'+b.name+'</td><td class="font-tech">'+b.size+'</td><td>'+b.type+'</td><td>'+b.fstype+'</td><td class="small">'+b.mount+'</td></tr>';}}bt.innerHTML=bh||'<tr><td colspan="5" class="text-center text-muted py-2">No block devices</td></tr>';}
var lv=document.getElementById('lvmInfoText');if(lv&&d.lvm){var lh='';for(var i=0;i<d.lvm.length;i++){lh+='<span class="font-tech fw-bold text-info me-2">'+d.lvm[i].name+'</span><span class="text-muted me-3">'+d.lvm[i].size_gb+'GB</span>';}lv.innerHTML=lh||'<span class="text-muted">No LVM devices</span>';}else if(lv)lv.innerHTML='<span class="text-muted">No LVM devices</span>';
_sysData=d;renderAdvancedAnalytics();}}}).catch(function(e){console.error('[systemInfo]',e);});}
var procSort='mem',_cpuProcs=[],_memProcs=[];
function renderProcs(cpuProcs,memProcs){var pt=document.getElementById('procTable');if(!pt)return;_cpuProcs=cpuProcs;_memProcs=memProcs;
var data=procSort==='cpu'?_cpuProcs:_memProcs;var ph='';for(var i=0;i<data.length;i++){var p=data[i];var mb=(p.mem_kb/1024).toFixed(1);ph+='<tr><td class="font-tech">'+p.pid+'</td><td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'+(p.cmd||'[kernel]')+'</td><td class="font-tech">'+p.cpu_ticks+'</td><td class="font-tech">'+mb+'MB</td></tr>';}pt.innerHTML=ph;}
var cpuBtn=document.getElementById('btnTopCpu');if(cpuBtn)cpuBtn.onclick=function(){procSort='cpu';document.getElementById('btnTopCpu').classList.add('active-sort');document.getElementById('btnTopMem').classList.remove('active-sort');renderProcs();};
var memBtn=document.getElementById('btnTopMem');if(memBtn)memBtn.onclick=function(){procSort='mem';document.getElementById('btnTopMem').classList.add('active-sort');document.getElementById('btnTopCpu').classList.remove('active-sort');renderProcs();};

function loadAppMetrics(){fetch(u('app_metrics'),{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'}).then(function(r){return r.json();}).then(function(d){
if(!d.success||!d.metrics)return;
var m=d.metrics;
document.getElementById('appMetricOps').textContent=m.total_actions||0;
document.getElementById('appMetricLogins').textContent=m.logins||0;
document.getElementById('appMetricFailures').textContent=m.failures||0;
document.getElementById('appMetricRate').textContent=(m.success_rate||100)+'%';
document.getElementById('appMetricUsers').textContent=m.unique_user_count||0;
var ta=document.getElementById('appTopActions');
if(ta&&m.top_actions){
var keys=Object.keys(m.top_actions);var maxVal=Math.max.apply(null,keys.map(function(k){return m.top_actions[k];}))||1;
var ah='<div class="d-flex flex-wrap gap-1" style="font-size:0.55rem;">';
for(var ai=0;ai<Math.min(keys.length,8);ai++){var k=keys[ai];var v=m.top_actions[k];var pct=Math.round(v/maxVal*100);
ah+='<div style="flex:1;min-width:70px;"><div class="d-flex justify-content-between"><span class="text-muted">'+k.replace(/_/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase();})+'</span><span class="font-tech fw-bold">'+v+'</span></div><div class="progress" style="height:4px;"><div class="progress-bar" style="width:'+pct+'%;background:'+(pct>50?'#f59e0b':pct>25?'#3b82f6':'#22c55e')+';"></div></div></div>';}
ah+='</div>';ta.innerHTML=ah;}
var hg=document.getElementById('appHourlyGrid');
if(hg&&m.hourly_activity){
var maxH=Math.max.apply(null,m.hourly_activity)||1;
function fmtHr(h){var p=h<12?'AM':'PM';var h12=h%12||12;return h12+p;}
var hh='';for(var hi=0;hi<24;hi++){var hv=m.hourly_activity[hi];var hp=maxH>0?Math.round(hv/maxH*100):0;
var hc=hp>=70?'#ef4444':hp>=40?'#f59e0b':hp>0?'#22c55e':'transparent';
var hb2=hp>=70?'#ef444422':hp>=40?'#f59e0b22':hp>0?'#22c55e22':'transparent';
hh+='<div style="flex:1;min-width:28px;text-align:center;font-size:var(--font-xs);background:'+hb2+';border:1px solid '+(hp>0?hc+'44':'var(--border-color)')+';border-radius:2px;" title="'+fmtHr(hi)+' - '+hv+' actions"><div style="font-size:var(--font-xs);color:'+(hp>70?hc:'var(--text-muted)')+';">'+fmtHr(hi)+'</div><div style="font-size:var(--font-xs);color:'+hc+';" class="font-tech">'+hv+'</div></div>';}
hg.innerHTML=hh;}
var ar=document.getElementById('appMetricsRefresh');if(ar){var nw=new Date();ar.textContent=('0'+nw.getHours()).slice(-2)+':'+('0'+nw.getMinutes()).slice(-2);}
_metricsData=m;renderAdvancedAnalytics();}).catch(function(e){console.error('[appMetrics]',e);});}

// ========== ADVANCED ANALYTICS (Chart.js) ==========
var _advCharts={};
function renderAdvancedAnalytics(){
    if(!document.getElementById('advCpuMemBar'))return;
    var d=_sysData;
    if(!d)return;
    renderCpuMemBarChart();
    renderNetPieChart(d);
    renderDiskTreemapChart(d);
    renderRadarChart(d);
    renderHeatmapChart();
    renderBubbleChart(d);
    var sl=document.getElementById('advSysLoad');if(sl&&d.cpu&&d.cpu.load){sl.textContent=d.cpu.load.join(' / ');}
    var pr=document.getElementById('advProcesses');if(pr){pr.textContent=d.process_count||'0';}
    var co=document.getElementById('advConnections');if(co){co.textContent=d.tcp_connections||'0';}
    var cs=document.getElementById('advCtxSwitches');if(cs&&d.cpu){cs.textContent=d.cpu.context_switches!==undefined?d.cpu.context_switches.toLocaleString():'--';}
    var arEl=document.getElementById('advAnalyticsRefresh');if(arEl){var nw=new Date();arEl.textContent=('0'+nw.getHours()).slice(-2)+':'+('0'+nw.getMinutes()).slice(-2)+':'+('0'+nw.getSeconds()).slice(-2);}
}
function renderCpuMemBarChart(){
var c=document.getElementById('advCpuMemBar');if(!c)return;
var cpuArr=sH.cpu,memArr=sH.mem;
var maxPts=Math.min(cpuArr.length,24);
var labels=[],cpuVals=[],memVals=[];
var step=Math.max(1,Math.floor(cpuArr.length/maxPts));
for(var i=cpuArr.length-maxPts*step;i<cpuArr.length;i+=step){
if(i<0)continue;
labels.push(cpuArr[i]?cpuArr[i].time:'');
cpuVals.push(cpuArr[i]?cpuArr[i].value:0);
memVals.push(memArr[i]?memArr[i].value:0);
}
if(_advCharts.cpuMemBar){_advCharts.cpuMemBar.data.labels=labels;_advCharts.cpuMemBar.data.datasets[0].data=cpuVals;_advCharts.cpuMemBar.data.datasets[1].data=memVals;_advCharts.cpuMemBar.update('none');return;}
_advCharts.cpuMemBar=new Chart(c,{type:'bar',data:{labels:labels,datasets:[{label:'CPU %',data:cpuVals,backgroundColor:'rgba(168,85,247,0.6)',borderColor:'#a855f7',borderWidth:1,borderRadius:3,barPercentage:0.5},{label:'MEM %',data:memVals,backgroundColor:'rgba(34,197,94,0.6)',borderColor:'#22c55e',borderWidth:1,borderRadius:3,barPercentage:0.5}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top',labels:{font:{size:10},boxWidth:12,boxHeight:8,padding:6,color:'#374151'}},datalabels:{display:false},tooltip:{backgroundColor:'#0f172a',bodyFont:{size:10},padding:8}},scales:{x:{grid:{display:false},ticks:{font:{size:9},color:'#6b7280',maxTicksLimit:8}},y:{beginAtZero:true,max:100,grid:{color:'rgba(0,0,0,0.06)'},ticks:{font:{size:9},color:'#6b7280',callback:function(v){return v+'%'}}}},animation:{duration:400,easing:'easeOutQuart'}}});
}
function renderNetPieChart(d){
var c=document.getElementById('advNetPie');if(!c||!d.interfaces||!d.interfaces.length)return;
var labels=[],vals=[],colors=[];
for(var i=0;i<d.interfaces.length;i++){
var ni=d.interfaces[i];
var totalTraffic=(ni.rx_bytes||0)+(ni.tx_bytes||0);
if(totalTraffic>0){
labels.push(ni.name);
vals.push(Math.round(totalTraffic/1048576));
colors.push(ni.name==='lo'?'#64748b':pl[i%pl.length]);
}
}
if(!vals.length){vals=[1];labels=['No data'];colors=['#475569'];}
if(_advCharts.netPie){_advCharts.netPie.data.labels=labels;_advCharts.netPie.data.datasets[0].data=vals;_advCharts.netPie.data.datasets[0].backgroundColor=colors;_advCharts.netPie.update('none');return;}
_advCharts.netPie=new Chart(c,{type:'doughnut',data:{labels:labels,datasets:[{data:vals,backgroundColor:colors,borderWidth:0,},]},options:{responsive:true,maintainAspectRatio:false,cutout:'50%',plugins:{legend:{position:'right',labels:{font:{size:10},boxWidth:10,boxHeight:8,padding:5,color:'#374151'}},datalabels:{display:false},tooltip:{backgroundColor:'#0f172a',titleFont:{size:11},bodyFont:{size:10},padding:8,callbacks:{label:function(it){return it.label+': '+it.parsed+'MB';}}}},animation:{duration:400,easing:'easeOutQuart'}}});
}
function renderDiskTreemapChart(d){
var c=document.getElementById('advDiskTreemap');if(!c||!d.mounts||!d.mounts.length)return;
var labels=[],vals=[],colors=[];
var _dtClrs=['#3b82f6','#a855f7','#ec4899','#f59e0b','#14b8a6','#f97316','#6366f1','#84cc16','#06b6d4'];
var _di=0;
for(var i=0;i<d.mounts.length;i++){
var m=d.mounts[i];
if(m.type==='tmpfs'||m.type==='devtmpfs'||m.type==='overlay'||m.type==='proc'||m.type==='sysfs'||m.type==='cgroup'||m.type==='cgroup2'||m.type==='devpts'||m.type==='autofs'||m.type==='mqueue'||m.type==='pstore'||m.type=='ramfs'||m.size<=0)continue;
var mountName=m.mnt.split('/').pop()||m.mnt;
labels.push(mountName+' ('+m.use+')');
vals.push(m.size);
colors.push(_dtClrs[_di++%_dtClrs.length]);
}
if(!vals.length){labels=['No data'];vals=[1];colors=['#475569'];}
if(_advCharts.diskTreemap){_advCharts.diskTreemap.data.labels=labels;_advCharts.diskTreemap.data.datasets[0].data=vals;_advCharts.diskTreemap.data.datasets[0].backgroundColor=colors;_advCharts.diskTreemap.update('none');return;}
_advCharts.diskTreemap=new Chart(c,{type:'pie',data:{labels:labels,datasets:[{data:vals,backgroundColor:colors,borderWidth:1,borderColor:'rgba(0,0,0,0.2)'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right',labels:{font:{size:9},boxWidth:9,boxHeight:7,padding:4,color:'#374151'}},datalabels:{display:false},tooltip:{backgroundColor:'#0f172a',titleFont:{size:11},bodyFont:{size:10},padding:8,callbacks:{label:function(it){var total=it.dataset.data.reduce(function(a,b){return a+b;},0);var pct=total>0?Math.round(it.parsed/total*100):0;return it.label+' ('+pct+'%)';}}}},animation:{duration:400,easing:'easeOutQuart'}}});
}
function renderRadarChart(d){
var c=document.getElementById('advRadar');if(!c)return;
var cpuPct=d.cpu?d.cpu.usage||0:0;
var memPct=d.memory&&d.memory.total>0?Math.round(d.memory.used/d.memory.total*100):0;
var diskPct=d.disk_overall&&d.disk_overall.total>0?Math.round(d.disk_overall.used/d.disk_overall.total*100):0;
var netScore=d.interfaces&&d.interfaces.length>1?Math.min(100,Math.round(d.interfaces.reduce(function(a,i){return a+((i.rx_rate||0)+(i.tx_rate||0));},0)/1048576*10)):0;
var fpmScore=d.php_fpm&&d.php_fpm.total>0?Math.min(100,Math.round((d.php_fpm.active/(d.php_fpm.total||1))*100)):0;
var procScore=d.process_count?Math.min(100,Math.round(d.process_count/5)):0;
var labels=['CPU','Memory','Disk','Network','FPM','Processes'];
var vals=[Math.min(100,cpuPct),Math.min(100,memPct),Math.min(100,diskPct),Math.min(100,netScore),Math.min(100,fpmScore),Math.min(100,procScore)];
var idealVals=[20,40,40,20,30,20];
if(_advCharts.radar){_advCharts.radar.data.datasets[0].data=vals;_advCharts.radar.update('none');return;}
_advCharts.radar=new Chart(c,{type:'radar',data:{labels:labels,datasets:[{label:'Current',data:vals,backgroundColor:'rgba(6,182,212,0.15)',borderColor:'#06b6d4',pointBackgroundColor:'#06b6d4',pointBorderColor:'#fff',pointBorderWidth:1.5,pointRadius:4,pointHoverRadius:6,borderWidth:1.5},{label:'Ideal',data:idealVals,backgroundColor:'rgba(34,197,94,0.05)',borderColor:'rgba(34,197,94,0.3)',pointBackgroundColor:'rgba(34,197,94,0.3)',pointRadius:2,pointHoverRadius:4,borderWidth:0.5,borderDash:[3,3]}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top',labels:{font:{size:10},boxWidth:12,boxHeight:8,padding:5,color:'#374151'}},datalabels:{display:false},tooltip:{backgroundColor:'#0f172a',bodyFont:{size:10},padding:8}},scales:{r:{beginAtZero:true,max:100,grid:{color:'rgba(0,0,0,0.06)'},angleLines:{color:'rgba(0,0,0,0.06)'},pointLabels:{font:{size:10},color:'#374151'},ticks:{font:{size:9},color:'#6b7280',stepSize:20,backdropColor:'transparent'}}},animation:{duration:400,easing:'easeOutQuart'}}});
}
function renderHeatmapChart(){
var c=document.getElementById('advHeatmap');if(!c||!_metricsData)return;
var ha=_metricsData.hourly_activity;
if(!ha||!ha.length)return;
var labels=[],vals=[],clrs=[];
for(var hi=0;hi<24;hi++){
var h12=hi%12||12;
var ampm=hi<12?'AM':'PM';
labels.push(h12+ampm);
var v=ha[hi]||0;
vals.push(v);
var maxH=Math.max.apply(null,ha)||1;
var pct=maxH>0?v/maxH:0;
if(pct>=0.8)clrs.push('rgba(239,68,68,0.7)');
else if(pct>=0.5)clrs.push('rgba(245,158,11,0.7)');
else if(pct>=0.2)clrs.push('rgba(34,197,94,0.6)');
else clrs.push('rgba(100,116,139,0.3)');
}
if(_advCharts.heatmap){_advCharts.heatmap.data.labels=labels;_advCharts.heatmap.data.datasets[0].data=vals;_advCharts.heatmap.data.datasets[0].backgroundColor=clrs;_advCharts.heatmap.data.datasets[0].borderColor=clrs.map(function(c){return c.replace('0.7','0.9').replace('0.6','0.8').replace('0.3','0.5');});_advCharts.heatmap.update('none');return;}
_advCharts.heatmap=new Chart(c,{type:'bar',data:{labels:labels,datasets:[{label:'Actions',data:vals,backgroundColor:clrs,borderColor:clrs.map(function(c){return c.replace('0.7','0.9').replace('0.6','0.8').replace('0.3','0.5');}),borderWidth:1,borderRadius:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},datalabels:{display:false},tooltip:{backgroundColor:'#0f172a',bodyFont:{size:10},padding:6,callbacks:{label:function(it){return it.label+': '+it.parsed+' actions';}}}},scales:{x:{grid:{display:false},ticks:{font:{size:9},color:'#6b7280',maxTicksLimit:12}},y:{beginAtZero:true,grid:{color:'rgba(0,0,0,0.04)'},ticks:{font:{size:9},color:'#6b7280',precision:0}}},animation:{duration:400,easing:'easeOutQuart'}}});
}
function renderBubbleChart(d){
var c=document.getElementById('advBubble');if(!c)return;
var procs=d.processes_top_cpu||[];
if(!procs.length){procs=d.processes_top_mem||[];}
if(!procs.length)return;
var data=[];
var maxCpu=1,maxMem=1;
for(var i=0;i<procs.length;i++){
var p=procs[i];
if(p.cpu_ticks>maxCpu)maxCpu=p.cpu_ticks;
var mb=(p.mem_kb||0)/1024;
if(mb>maxMem)maxMem=mb;
}
for(var i=0;i<procs.length;i++){
var p=procs[i];
var mb=(p.mem_kb||0)/1024;
var r=Math.max(4,Math.min(20,mb/maxMem*20));
data.push({x:p.cpu_ticks||1,y:mb||0.1,r:r});
}
if(_advCharts.bubble){_advCharts.bubble.data.datasets[0].data=data;_advCharts.bubble.update('none');return;}
_advCharts.bubble=new Chart(c,{type:'bubble',data:{datasets:[{label:'Processes',data:data,backgroundColor:data.map(function(d){return d.r>15?'rgba(239,68,68,0.6)':d.r>10?'rgba(245,158,11,0.6)':'rgba(59,130,246,0.6)';}),borderColor:data.map(function(d){return d.r>15?'#ef4444':d.r>10?'#f59e0b':'#3b82f6';}),borderWidth:1}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},datalabels:{display:false},tooltip:{backgroundColor:'#0f172a',bodyFont:{size:10},padding:8,callbacks:{label:function(it){var p=procs[it.dataIndex];return(p?p.cmd:'')+' | CPU: '+(p?p.cpu_ticks:0)+' MEM: '+(p?Math.round((p.mem_kb||0)/1024):0)+'MB';}}}},scales:{x:{grid:{color:'rgba(0,0,0,0.06)'},ticks:{font:{size:9},color:'#6b7280'},title:{display:true,text:'CPU Ticks',font:{size:9},color:'#6b7280'}},y:{beginAtZero:true,grid:{color:'rgba(0,0,0,0.06)'},ticks:{font:{size:9},color:'#6b7280'},title:{display:true,text:'Memory (MB)',font:{size:9},color:'#6b7280'}}},animation:{duration:400,easing:'easeOutQuart'}}});
}

async function loadAll(){hb();loadSystemInfo();loadAppMetrics();}
var _btnCalc=document.getElementById('btnCalculateNet');if(_btnCalc)_btnCalc.onclick=async function(){var cidr=document.getElementById('cidrInput').value;if(!cidr)return;var r=await fetch(u('calculate_net'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({cidr:cidr})});var d=await r.json();if(d.success){document.getElementById('netIntelSummary').style.display='block';document.getElementById('valNet').textContent=d.data.network;document.getElementById('valMask').textContent=d.data.mask;document.getElementById('valBc').textContent=d.data.broadcast;document.getElementById('valGw').textContent=d.data.gateway;document.getElementById('valCidr').textContent=d.data.cidr;}};
var _btnScan=document.getElementById('btnScanNet');if(_btnScan)_btnScan.onclick=async function(){if(sc)return;var cidr=document.getElementById('cidrInput').value;if(!cidr)return;_btnScan.innerHTML='<i class="fas fa-circle-notch fa-spin me-1"></i>Scanning...';_btnScan.disabled=true;var cr=await fetch(u('calculate_net'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({cidr:cidr})}),cd=await cr.json();if(!cd.success){_btnScan.innerHTML='<i class="fas fa-search me-1"></i>Scan';_btnScan.disabled=false;return;}sc=true;_btnScan.style.display='none';var _btnCancel=document.getElementById('btnCancelScan');if(_btnCancel)_btnCancel.classList.remove('d-none');var _btnExport=document.getElementById('btnExportScan');if(_btnExport)_btnExport.classList.add('app-hidden');var tb=document.getElementById('scanResultBody');tb.innerHTML='';var si=document.getElementById('scanIndicator');if(si)si.style.display='block';var ips=genRange(cd.data.first_ip,cd.data.last_ip);for(var i=0;i<ips.length;i++){if(!sc)break;var r=await fetch(u('scan_single'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ip:ips[i]})}),it=await r.json();if(it.success){var row=document.createElement('tr');row.innerHTML='<td class="ps-3 font-tech fw-bold text-primary">'+it.result.ip+'</td><td><span class="scan-status-'+it.result.status+'">'+it.result.status.toUpperCase()+'</span></td><td class="font-tech">'+it.result.latency+'ms</td><td class="small text-muted">'+it.result.dns+'</td><td class="x-small font-tech">'+(it.result.status==='used'?'ACTIVE_NODE':'AVAILABLE_PORT')+'</td>';tb.appendChild(row);}}stopScan();};var _btnCancel=document.getElementById('btnCancelScan');if(_btnCancel)_btnCancel.onclick=function(){stopScan();};
function stopScan(){sc=false;var _bs=document.getElementById('btnScanNet');if(_bs){_bs.style.display='block';_bs.innerHTML='<i class="fas fa-search me-1"></i>Scan';_bs.disabled=false;}var _bc=document.getElementById('btnCancelScan');if(_bc)_bc.classList.add('d-none');var _be=document.getElementById('btnExportScan');if(_be)_be.classList.remove('app-hidden');var _si=document.getElementById('scanIndicator');if(_si)_si.style.display='none';}
function genRange(s,e){var a=ip2int(s),b=ip2int(e),l=[];for(var i=a;i<=b;i++){l.push(int2ip(i));}return l.slice(0,255);}function ip2int(ip){return ip.split('.').reduce(function(r,o){return(r<<8)+parseInt(o,10);},0)>>>0;}
function int2ip(i){return[(i>>>24)&0xFF,(i>>>16)&0xFF,(i>>>8)&0xFF,i&0xFF].join('.');}
var _btnExport=document.getElementById('btnExportScan');if(_btnExport)_btnExport.onclick=function(){
var csv="IP Address,Status,Latency,DNS,Comments\n";document.querySelectorAll('#scanResultBody tr').forEach(function(row){var c=row.querySelectorAll('td');if(c.length>=5){csv+='"'+c[0].innerText+'","'+c[1].innerText+'","'+c[2].innerText+'","'+c[3].innerText+'","'+c[4].innerText+'"\n';}});
var b=new Blob([csv],{type:'text/csv'});var u2=window.URL.createObjectURL(b);var a=document.createElement('a');a.href=u2;a.download='noc_discovery_scan.csv';document.body.appendChild(a);a.click();document.body.removeChild(a);};
var pingTargets=[],pingTimer=null,pingRunning=false;
var _togBtns=document.querySelectorAll('.noc-toggle-btn');_togBtns.forEach(function(btn){btn.onclick=function(){_togBtns.forEach(function(b){b.classList.remove('active');});this.classList.add('active');var show=this.dataset.mode==='ping'?'pingModeSection':'dnsModeSection';var ps=document.getElementById('pingModeSection');if(ps)ps.style.display=show==='pingModeSection'?'block':'none';var ds=document.getElementById('dnsModeSection');if(ds)ds.style.display=show==='dnsModeSection'?'block':'none';if(show==='dnsModeSection'&&pingRunning)stopPing();};});
var _sp=document.getElementById('btnStopPing');if(_sp)_sp.onclick=stopPing;
var _mp=document.getElementById('btnManualPing');if(_mp)_mp.onclick=async function(){var ips=document.getElementById('manualPingIp').value;if(!ips)return;var nw=ips.split(/[\s,;]+/).filter(function(s){return s.trim();});if(!nw.length)return;for(var i=0;i<nw.length;i++){var ip=nw[i].trim();if(pingTargets.indexOf(ip)===-1)pingTargets.push(ip);}document.getElementById('manualPingIp').value='';if(!pingRunning){pingRunning=true;var _sp2=document.getElementById('btnStopPing');if(_sp2)_sp2.classList.remove('d-none');if(_mp)_mp.disabled=true;doPing();pingTimer=setInterval(doPing,3000);}};
async function doPing(){if(!pingTargets.length){stopPing();return;}var ips=pingTargets.join(',');var r=await fetch(u('manual_ping'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ip:ips,count:1})});var d=await r.json();if(!d.success||!d.results)return;var lEl=document.getElementById('pingLiveResult');var lh='';for(var i=0;i<d.results.length;i++){var res=d.results[i];lh+='<div class="multi-ping-row"><span class="font-tech fw-bold">'+res.ip+'</span> <span class="'+(res.success?'text-success':'text-danger')+' float-end">'+(res.success?res.latency:'TIMEOUT')+'</span></div>';}lEl.innerHTML=lh;}
function stopPing(){if(pingTimer){clearInterval(pingTimer);pingTimer=null;}pingRunning=false;var sp=document.getElementById('btnStopPing');if(sp)sp.classList.add('d-none');var bp=document.getElementById('btnManualPing');if(bp)bp.disabled=false;}
var _dlBtn=document.getElementById('btnDnsLookup');if(_dlBtn)_dlBtn.onclick=async function(){var h=document.getElementById('dnsLookupInput').value.trim();if(!h)return;var ds=document.getElementById('dnsServerInput').value.trim();var dr=document.getElementById('dnsResult');dr.className+=' loading';dr.innerHTML='<span>Resolving '+h+'...</span>';var isIp=/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(h)||h.includes(':');var body={host:h,type:isIp?'ip':'host'};if(ds)body.dns_server=ds;var r=await fetch(u('dns_lookup'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});var d=await r.json();dr.className=dr.className.replace(' loading','');if(!d.success){dr.innerHTML='<span class="text-danger">'+d.message+'</span>';return;}var html='';if(d.server)html+='<div class="dns-row"><span class="text-muted">DNS Server:</span> <span class="font-tech">'+d.server+'</span></div>';if(d.dig_output){html+='<div style="margin-top:4px;border-top:1px solid rgba(255,255,255,0.06);padding-top:4px;">';for(var di=0;di<d.dig_output.length;di++){html+='<div class="dns-rec-item font-tech" style="font-size:0.62rem;">'+d.dig_output[di]+'</div>';}html+='</div>';}else{if(d.filter==='ip'){html+='<div class="dns-rec-row"><span class="text-muted">PTR Record:</span> <span class="text-info fw-bold">'+(d.ptr||'<span class="text-warning">No PTR record</span>')+'</span></div>';if(d.records&&d.records.length){html+='<div class="dns-rec-section">';for(var di=0;di<d.records.length;di++){var rec=d.records[di];html+='<div class="dns-rec-item"><span class="text-info">'+rec.type+'</span> <span>'+(rec.target||rec.ip||rec.mx||rec.txt||'')+'</span></div>';}html+='</div>';}}else{html+='<div class="dns-rec-row"><span class="text-muted">Resolved IPs:</span></div>';if(!d.records||!d.records.length){html+='<div class="dns-rec-item"><span class="text-warning">No records found.</span></div>';}else{for(var di=0;di<d.records.length;di++){var rec=d.records[di];html+='<div class="dns-rec-item"><span class="text-info">'+rec.type+'</span> <span>'+(rec.target||rec.ip||rec.mx||rec.txt||'')+'</span></div>';}}}}dr.innerHTML=html;};
var _pcBtn=document.getElementById('btnPortCheck');if(_pcBtn)_pcBtn.onclick=async function(){var ip=document.getElementById('portCheckIp').value.trim(),port=document.getElementById('portCheckPort').value.trim();if(!ip)return;var pr=document.getElementById('portResult');if(port){pr.className+=' loading';pr.innerHTML='<spanChecking '+ip+':'+port+'...</span>';var r=await fetch(u('port_check'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ip:ip,port:parseInt(port)})}),d=await r.json();pr.className=pr.className.replace(' loading','');if(d.success&&d.open){pr.innerHTML='<div><span class="text-success fw-bold">&#9679; OPEN</span> <span class="text-muted">port '+port+' responded in '+d.latency+'ms</span></div>';}else{pr.innerHTML='<div><span class="text-danger fw-bold">&#9679; CLOSED</span> <span class="text-muted">port '+port+' ('+(d.error||'no response')+')</span></div>';}}else{var commonPorts=[21,22,23,25,53,80,110,143,443,465,587,993,995,3306,3389,5432,5900,6379,8080,8443,9090,27017];pr.innerHTML='<div style="color:var(--text-muted);margin-bottom:4px;"><i class="fas fa-circle-notch fa-spin me-1"></i>Scanning common ports on '+ip+'...</div>';var openPorts=[];for(var pi=0;pi<commonPorts.length;pi++){var cp=commonPorts[pi];try{var r2=await fetch(u('port_check'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ip:ip,port:cp})}),d2=await r2.json();if(d2.success&&d2.open)openPorts.push({port:cp,latency:d2.latency});}catch(e){}if(openPorts.length){var html='<div style="color:var(--text-muted);margin-bottom:4px;">Scanning common ports on '+ip+'... <span class="text-success">'+openPorts.length+' open</span></div>';for(var oi=0;oi<openPorts.length;oi++){html+='<div><span class="text-success fw-bold">&#9679; OPEN</span> <span class="font-tech">port '+openPorts[oi].port+' ('+openPorts[oi].latency+'ms)</span></div>';}pr.innerHTML=html;}}if(!openPorts.length)pr.innerHTML='<div><span class="text-warning">No common ports open on '+ip+'</span></div>';}};
var _trBtn=document.getElementById('btnTraceroute');if(_trBtn)_trBtn.onclick=async function(){var ip=document.getElementById('routeTarget').value.trim();if(!ip)return;var rr=document.getElementById('routeResult');rr.className+=' loading';rr.innerHTML='<span class="text-info">Tracing route to '+ip+'...</span>';var r=await fetch(u('traceroute'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ip:ip})}),d=await r.json();rr.className=rr.className.replace(' loading','');if(!d.success){rr.innerHTML='<span class="text-danger">Failed.</span>';return;}var h='';for(var hi=0;hi<d.hops.length;hi++){h+='<div class="trace-hop">'+d.hops[hi]+'</div>';}rr.innerHTML=h||'<span class="text-warning">No route data.</span>';};
var _mrBtn=document.getElementById('btnMtrReport');if(_mrBtn)_mrBtn.onclick=async function(){var ip=document.getElementById('routeTarget').value.trim();if(!ip)return;var rr=document.getElementById('routeResult');rr.className+=' loading';rr.innerHTML='<span class="text-info">Generating MTR report for '+ip+'...</span>';var r=await fetch(u('mtr_report'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ip:ip})}),d=await r.json();rr.className=rr.className.replace(' loading','');if(!d.success){rr.innerHTML='<span class="text-danger">Failed.</span>';return;}var h='<div class="mtr-header">MTR Report: '+ip+'</div>';for(var hi=0;hi<d.report.length;hi++){h+='<div class="mtr-line">'+d.report[hi]+'</div>';}rr.innerHTML=h||'<span class="text-warning">No data.</span>';};
var _wlBtn=document.getElementById('btnWhoisLookup');if(_wlBtn)_wlBtn.onclick=async function(){var h=document.getElementById('whoisInput').value.trim();if(!h)return;var wr=document.getElementById('whoisResult');wr.className+=' loading';wr.innerHTML='<span class="text-info">Looking up WHOIS for '+h+'...</span>';var r=await fetch(u('whois_lookup'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({host:h})}),d=await r.json();wr.className=wr.className.replace(' loading','');if(!d.success){wr.innerHTML='<span class="text-danger">Failed.</span>';return;}var h2='';for(var di=0;di<d.data.length;di++){h2+='<div class="whois-line">'+d.data[di]+'</div>';}wr.innerHTML=h2||'<span class="text-warning">No WHOIS data returned.</span>';};
window.deleteNocNode=async function(ip){if(!confirm('Remove node '+ip+'?'))return;await fetch(u('delete'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ip:ip})});loadAll();};
var _af=document.getElementById('addNodeForm');if(_af)_af.onsubmit=async function(e){e.preventDefault();var btn=e.target.querySelector('button[type="submit"]');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>Adding...';try{var r=await fetch(u('upsert'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.fromEntries(new FormData(e.target).entries()))});if((await r.json()).success){var modal=bootstrap.Modal.getInstance(document.getElementById('addNodeModal'));modal.hide();e.target.reset();loadAll();}}finally{btn.disabled=false;btn.innerHTML='Initiate Heartbeat Monitor';}};
var _rsBtn=document.getElementById('btnRunSweep');if(_rsBtn)_rsBtn.onclick=async function(){_rsBtn.disabled=true;_rsBtn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>Sweeping...';await fetch(u('sweep'),{method:'POST',headers:{'Content-Type':'application/json'}});_rsBtn.disabled=false;_rsBtn.innerHTML='<i class="fas fa-sync-alt me-1"></i>Sweep';loadAll();};
loadAll();if(tm){clearInterval(tm);}tm=setInterval(loadAll,10000);
}
document.addEventListener('DOMContentLoaded',initNocSuite);document.addEventListener('spaContentUpdated',initNocSuite);if(document.readyState==='complete'||document.readyState==='interactive')initNocSuite();
})();