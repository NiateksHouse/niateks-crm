'use strict';
(() => {
  const $ = (s, root = document) => root.querySelector(s);
  const $$ = (s, root = document) => [...root.querySelectorAll(s)];
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const state = { boot:null, locale:'tr-TR', timezone:'Europe/Istanbul', view:'today', type:null, page:1, q:'', filter:'', generation:0, dirty:false, record:null };
  const t = (key, fallback) => window.KOZA_I18N[key]?.[state.locale.startsWith('tr')?'tr':'en'] ?? fallback ?? key.replaceAll('_',' ');
  const label = cfg => cfg?.label?.[state.locale.startsWith('tr')?'tr':'en'] ?? '';
  const stage = key => t('state_'+key, key?.replaceAll('_',' ') ?? '—');
  const num = value => value == null || value === '' ? '—' : new Intl.NumberFormat(state.locale,{maximumFractionDigits:4}).format(Number(value));
  const money = (value, currency) => value == null ? '—' : new Intl.NumberFormat(state.locale,{style:'currency',currency:currency||'GBP'}).format(Number(value));
  function date(value, time = false) {
    if (!value) return '—';
    const parsed = new Date(value.length===10 ? value+'T12:00:00Z' : value.replace(' ','T') + (/Z$|[+-]\d\d:\d\d$/.test(value)?'':'Z'));
    if (isNaN(parsed.getTime())) return value;
    return new Intl.DateTimeFormat(state.locale,{dateStyle:'medium',...(time?{timeStyle:'short',timeZone:state.timezone}:{timeZone:'UTC'})}).format(parsed);
  }
  const badge = value => `<span class="badge ${['approved','verified','released','delivered','resolved','won','active','pass'].includes(value)?'good':['held','stopped','critical','lost','fail','degraded'].includes(value)?'danger':'info'}">${esc(stage(value))}</span>`;
  const btn = (action,text,classes='btn',attrs='') => `<button class="${classes}" data-action="${action}" ${attrs}>${esc(text)}</button>`;
  const recordButton = r => `<button class="text-button" data-record="${r.id}">${esc(r.title)}</button>`;
  const cat = type => state.boot.catalog[type];
  const domain = d => state.boot.user.domains.includes(d);
  const page = $('#page');
  const detail = $('#detailDialog');
  const formDialog = $('#formDialog');
  const picker = $('#pickerDialog');
  let editContext = null, pickerResolve = null;
  const iconPaths = {"today": "M3 11 12 3l9 8 M5 10v11h5v-7h4v7h5V10", "myfocus": "M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18 M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10", "accounts": "M9 3a3 3 0 1 0 0 6 3 3 0 0 0 0-6 M3 21v-5a6 6 0 0 1 12 0v5H3 M17 4a3 3 0 0 1 0 6 M18 13c3 0 4 2 4 5v3h-4", "accountdev": "M4 20 20 4 M10 4h10v10", "opportunities": "M4 21 19 3 M7 16C1 8 8 6 10 12 M11 12c0-7 6-10 9-10-1 7-4 10-9 10 M12 12c6-1 9 0 10 3-6 3-9 2-10-3", "products": "M4 3v18 M8 3v18 M12 3v18 M16 3v18 M20 3v18 M3 4h18 M3 8h18 M3 12h18 M3 16h18 M3 20h18", "samples": "M4 6h15c5 0 5 13 0 13H4 M4 6c-5 0-5 13 0 13s5-13 0-13 M8 6c4 2 4 11 0 13 M12 6c4 2 4 11 0 13", "quotes": "M5 2h9l5 5v15H5V2 M14 2v6h5 M8 12h8 M8 16h6", "orders": "M3 7 12 2l9 5v11l-9 5-9-5V7 M3 7l9 5 9-5 M12 12v11 M7 5l10 5", "production": "M4 2v20 M9 2v20 M14 2v20 M19 2v20 M2 5h20 M2 10h20 M2 15h20 M2 20h20", "quality": "M12 2 3 6v7c0 5 9 9 9 9s9-4 9-9V6L12 2 M7 12l3 3 7-7", "shipments": "M2 5h13v12H2V5 M15 10h4l3 4v3h-7 M5 17a2 2 0 1 0 0 4 2 2 0 0 0 0-4 M18 17a2 2 0 1 0 0 4 2 2 0 0 0 0-4", "service": "M4 14v-3a8 8 0 0 1 16 0v3 M4 12H2v7h4v-7H4 M20 12h2v7h-4v-7h2 M18 19c0 3-2 3-6 3", "commercial": "M3 13h4v9H3v-9 M10 8h4v14h-4V8 M17 2h4v20h-4V2", "insights": "M8 18h8 M9 22h6 M8 18c0-5-4-5-4-10a8 8 0 0 1 16 0c0 5-4 5-4 10", "documents": "M2 5h7l3 3h10v13H2V5", "ask": "M12 2c0 7-3 10-10 10 7 0 10 3 10 10 0-7 3-10 10-10-7 0-10-3-10-10", "system": "M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10 M12 1v4 M12 19v4 M1 12h4 M19 12h4 M4 4l3 3 M17 17l3 3 M4 20l3-3 M17 7l3-3"};
  const icon = key => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${iconPaths[key]||iconPaths.documents}"/></svg>`;
  const menus = [
    ['work', [['today','⌂'],['myfocus','◎']]], ['relationships',[['accounts','◉'],['accountdev','↗'],['opportunities','♧']]],
    ['productDelivery',[['products','▦'],['samples','◫'],['quotes','▤'],['orders','◇'],['production','▥'],['quality','✓'],['shipments','⇢']]],
    ['healthLearning',[['service','◔'],['commercial','▥'],['insights','◌'],['documents','□']]], ['systemGroup',[['ask','✦'],['system','⚙']]]
  ];
  const types = {accounts:['account','contact'],accountdev:['account'],opportunities:['opportunity'],products:['product','variant','sku','offering'],samples:['sample'],quotes:['quote'],orders:['order'],production:['lot','material','manufacturer','supplier'],quality:['qc','certification'],shipments:['shipment'],service:['service'],insights:['insight','assumption','experiment','scenario','capability'],system:['dependency','right'],myfocus:['task']};
  async function api(path, options={}) {
    const headers={Accept:'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').content,...options.headers};
    if (options.body && !(options.body instanceof FormData)) {headers['Content-Type']='application/json';options.body=JSON.stringify(options.body);}
    const res=await fetch('/koza/api'+path,{...options,headers,credentials:'same-origin'});
    const data=await res.json().catch(()=>({}));
    if (!res.ok) {
      const error=new Error(data.message||'error'); error.status=res.status;error.errors=data.errors||{};throw error;
    }
    return data;
  }
  function errorMessage(error) {
    if(error.status===403)return t('unauthorised');
    if([401,419].includes(error.status))return t('expiredSession');
    if(error.status===409)return t('conflict');
    if(Object.keys(error.errors||{}).length)return Object.entries(error.errors).map(([field,messages])=>{
      const f=field.replace(/^data\./,''); const cfg=Object.values(state.boot?.catalog||{}).map(c=>c.fields[f]).find(Boolean);
      return (cfg?label(cfg)+': ':['workflow','state'].includes(f)?'':t(f,f)+': ')+messages.map(m=>t('error_'+m,t(m,m.startsWith('validation.')?t('required'):m))).join(' ');
    }).join('\n');
    return t('error_'+error.message, t('error'));
  }
  function showError(error, root = page) {let box=$('[data-errors]',root);if(!box){box=document.createElement('div');box.dataset.errors='';root.prepend(box);}box.className='error';box.setAttribute('role','alert');box.textContent=errorMessage(error);box.scrollIntoView({block:'nearest'});}
  function toast(message){const node=$('#toast');node.textContent=message;node.classList.remove('hidden');clearTimeout(toast.timer);toast.timer=setTimeout(()=>node.classList.add('hidden'),4500);}
  function bindRecords(root){$$('[data-record]',root).forEach(el=>el.addEventListener('click',()=>openRecord(Number(el.dataset.record))));}
  function baseHeader(title,subtitle,actions=''){return `<div class="head"><div><div class="eyebrow">KOZA · Niateks House</div><h1>${esc(title)}</h1>${subtitle?`<div class="subtle">${esc(subtitle)}</div>`:''}</div><div class="actions">${actions}</div></div>`;}
  function shell(){
    document.documentElement.lang=state.locale;
    $('#navigation').innerHTML=menus.map(([group,items])=>`<div class="nav-title">${esc(t(group))}</div>${items.map(([key,ico])=>`<button data-view="${key}" class="${state.view===key?'active':''}" ${state.view===key?'aria-current="page"':''} title="${esc(t(key))}"><span class="ico" aria-hidden="true">${icon(key)}</span><span class="txt">${esc(t(key))}</span></button>`).join('')}`).join('');
    $$('[data-view]',$('#navigation')).forEach(b=>b.addEventListener('click',()=>navigate(b.dataset.view)));
    $$('[data-i18n]').forEach(el=>el.textContent=t(el.dataset.i18n));
    $('#search').placeholder=t('searchPlaceholder');$('#search').setAttribute('aria-label',t('searchPlaceholder'));$('#localeSelect').value=state.locale;
    $('#profileButton .avatar').textContent=state.boot.user.name.slice(0,1);$('#profileButton .profile-name').textContent=state.boot.user.name;$('#profileButton').title=state.boot.user.name;
  }
  async function bootstrap(){state.boot=await api('/bootstrap');state.locale=state.boot.locale;state.timezone=state.boot.timezone;shell();}
  function navigate(view,type=null){
    if(state.dirty&&!confirm(t('unsaved')))return;
    state.dirty=false;state.view=view;state.type=type||types[view]?.[0]||null;state.page=1;state.q='';state.filter='';
    $('#sidebar').classList.remove('mobile-open');$('#menuToggle').setAttribute('aria-expanded','false');
    history.replaceState(null,'','#'+view+(type?'/'+type:''));shell();render();
  }
  async function render(){
    const generation=++state.generation;page.setAttribute('aria-busy','true');page.innerHTML=`<div class="card" role="status">${esc(t('loading'))}</div>`;
    try{
      if(state.view==='today')await today(generation);
      else if(state.view==='documents')await documents(generation);
      else if(state.view==='ask')ask();
      else if(state.view==='commercial')await commercial(generation);
      else if(state.view==='settings')await settings(generation);
      else if(state.view==='search')await list(generation,null);
      else if(state.view==='myfocus')await focus(generation);
      else await list(generation,state.type);
    }catch(error){if(generation===state.generation){page.innerHTML=`<div class="card">${btn('retry',t('retry'))}</div>`;showError(error);$('[data-action=retry]',page).onclick=render;}}
    finally{if(generation===state.generation)page.setAttribute('aria-busy','false');}
  }
  function rows(records){return records.map(r=>`<tr><td>${recordButton(r)}<div class="row-meta">#${r.id} · ${esc(label(cat(r.type)))} · V${r.edition}</div></td><td>${esc(r.company||'—')}</td><td>${badge(r.state)}</td><td>${esc(r.owner||'—')}</td><td>${esc(r.data.next_step||r.data.need||r.data.purpose||'—')}</td><td>${esc(date(r.due_at))}</td></tr>`).join('');}
  function table(records){return records.length?`<div class="table-wrap"><table><thead><tr>${['name','company','stage','owner','next','due'].map(k=>`<th scope="col">${esc(t(k))}</th>`).join('')}</tr></thead><tbody>${rows(records)}</tbody></table></div>`:`<div class="card empty">${esc(t(state.q?'noResults':'empty'))}</div>`;}
  function pagination(data){return `<div class="pagination">${btn('previous',t('previous'),'btn',data.page<=1?'disabled':'')}<span class="subtle">${esc(t('page'))} ${data.page} / ${data.pages}</span>${btn('nextPage',t('nextPage'),'btn',data.page>=data.pages?'disabled':'')}</div>`;}
  function bindPagination(data){$('[data-action=previous]',page)?.addEventListener('click',()=>{state.page--;render();});$('[data-action=nextPage]',page)?.addEventListener('click',()=>{state.page++;render();});}
  async function list(generation,type){
    const params=new URLSearchParams({page:state.page,q:state.q});if(type)params.set('type',type);if(state.filter)params.set('state',state.filter);
    const data=await api('/records?'+params);if(generation!==state.generation)return;
    const tabs=types[state.view]||[];
    let title=t(state.view),subtitle='';
    if(['accounts','accountdev'].includes(state.view))subtitle=t('accountHelp');
    if(state.view==='opportunities')subtitle=t('oppHelp');if(state.view==='products')subtitle=t('productHelp');if(state.view==='service')subtitle=t('serviceHelp');
    let actions=type&&cat(type).can_create?btn('new',t('new'),'btn primary'):'';
    if(state.view==='accounts')actions+=btn('newCompany',t('newCompany'),'btn soft');
    if(state.view==='system'&&state.boot.user.business_owner)actions+=btn('settings',t('settings'),'btn soft');
    page.innerHTML=baseHeader(title,subtitle,actions)+(tabs.length>1?`<div class="tabs" role="tablist">${tabs.map(k=>`<button class="tab ${k===type?'active':''}" role="tab" aria-selected="${k===type}" data-type="${k}">${esc(label(cat(k)))}</button>`).join('')}</div>`:'')+
      ((type==='quote'||type==='sample')&&!state.boot.policy_configured?`<div class="callout">${esc(t('policyMissing'))}</div>`:'')+
      `<form class="toolbar" id="filterForm"><input type="search" name="q" value="${esc(state.q)}" placeholder="${esc(t('searchPlaceholder'))}" aria-label="${esc(t('searchPlaceholder'))}">${type?`<select name="state" aria-label="${esc(t('stage'))}"><option value="">${esc(t('all'))}</option>${cat(type).states.map(s=>`<option value="${s}" ${state.filter===s?'selected':''}>${esc(stage(s))}</option>`).join('')}</select>`:''}<button class="btn">${esc(t('filter'))}</button><span class="result-count">${num(data.total)} ${esc(t('records'))}</span><a class="chip" href="/koza/api/export${type?'?type='+encodeURIComponent(type):''}">${esc(t('export'))}</a></form>`+
      (state.view==='accountdev'?`<div class="workflow">${cat('account').states.slice(0,6).map(s=>`<button class="flow-step ${state.filter===s?'active':''}" data-stage="${s}">${esc(stage(s))}</button>`).join('')}</div><div class="section-gap"></div>`:'')+table(data.records)+pagination(data);
    if(state.view==='accounts'&&type==='account'){
      const linked=new Set(data.records.map(r=>r.company_id));
      const companies=state.boot.companies.filter(c=>!linked.has(c.id));
      page.insertAdjacentHTML('beforeend',`<details><summary>${esc(t('company'))} · ${companies.length}</summary><div class="card">${companies.map(c=>`<div class="row"><div><b>${esc(c.name)}</b><div class="row-meta">${esc(c.country_code)} · ${esc(c.city||'')}</div></div><div class="actions">${btn('edit-company',t('edit'),'btn',`data-company="${c.id}"`)}${cat('account').can_create?btn('add-account',t('createAccount'),'btn soft',`data-company="${c.id}"`):''}</div></div>`).join('')}</div></details>`);
      $$('[data-action=add-account]',page).forEach(b=>b.onclick=()=>openEditor(null,'account',Number(b.dataset.company)));
      $$('[data-action=edit-company]',page).forEach(b=>b.onclick=()=>companyForm(state.boot.companies.find(c=>c.id===Number(b.dataset.company))));
    }
    if(state.view==='accounts'&&type==='contact'&&state.boot.contacts.length){
      page.insertAdjacentHTML('beforeend',`<details><summary>${esc(t('existingContacts'))}</summary><div class="card">${state.boot.contacts.map(c=>`<div class="row"><div><b>${esc(c.name)}</b><div class="row-meta">${esc(c.company?.name||'')} · ${esc(c.email||'')} · ${esc(c.phone||'')}</div></div><a class="btn" href="/contacts/${c.id}">${esc(t('open'))}</a></div>`).join('')}</div></details>`);
    }
    bindRecords(page);bindPagination(data);
    $$('[data-type]',page).forEach(b=>b.onclick=()=>navigate(state.view,b.dataset.type));
    $$('[data-stage]',page).forEach(b=>b.onclick=()=>{state.filter=b.dataset.stage;state.page=1;render();});
    $('#filterForm').onsubmit=e=>{e.preventDefault();const f=new FormData(e.target);state.q=f.get('q');state.filter=f.get('state')||'';state.page=1;render();};
    $('[data-action=new]',page)?.addEventListener('click',()=>openEditor(null,type));
    $('[data-action=newCompany]',page)?.addEventListener('click',()=>companyForm());
    $('[data-action=settings]',page)?.addEventListener('click',()=>navigate('settings'));
  }
  async function today(generation){
    const data=await api('/dashboard');if(generation!==state.generation)return;
    const openLink=(view,text)=>`<button class="text-button" data-go="${view}">${esc(text)} <span aria-hidden="true">→</span></button>`;
    const meta=r=>`<div class="priority-meta">${esc(r.owner||t('unassigned'))}${r.due_at?' · '+esc(date(r.due_at)):''}</div>`;
    const configs=[['customer','accounts','service'],['decision','quotes','quotes'],['commercial','commercial','commercial']];
    const cards=configs.map(([kind,ico,view])=>{const r=data.priorities[kind];return `<article class="decision-card ${kind}"><span class="decision-icon">${icon(ico)}</span><div class="decision-content"><div class="label">${esc(t('priority_'+kind))}</div><h2>${esc(r?.title||t('clear_'+kind))}</h2><p>${esc(r?t('reason_'+r.reason):t(kind==='commercial'&&!data.can_review_commercial?'noPermission':'empty_'+kind))}</p>${r?meta(r):''}<div class="decision-actions">${r?recordButton(r):openLink(view,t('open_'+kind))}</div></div><span class="textile-thumb textile-${kind}" aria-hidden="true"></span></article>`;}).join('');
    const healthRow=(ico,title,value,description,tone='neutral',view='myfocus')=>`<button class="pulse-row" data-go="${view}"><span class="pulse-icon ${tone}">${icon(ico)}</span><strong>${esc(t(title))}</strong><span class="pulse-state ${tone}">${esc(value)}</span><span class="pulse-description">${esc(description)}</span><span aria-hidden="true">›</span></button>`;
    const item=r=>`<div class="schedule-row"><span class="schedule-date">${esc(date(r.due_at))}</span><span class="schedule-icon">${icon(r.type==='task'?'myfocus':r.type==='order'?'orders':'documents')}</span><div>${recordButton(r)}<div class="row-meta">${esc(r.owner||t('unassigned'))}</div></div></div>`;
    const suggestion=data.suggestion;
    page.innerHTML=`<section class="today-dashboard"><header class="welcome"><div class="welcome-copy"><div class="welcome-date">— &nbsp; ${esc(new Intl.DateTimeFormat(state.locale,{weekday:'short',day:'numeric',month:'short',year:'numeric',timeZone:'UTC'}).format(new Date(data.date+'T12:00:00Z')))}</div><h1>${esc(t('hello'))}, ${esc(state.boot.user.name.split(' ')[0])}.</h1><p>${esc(t('attentionToday'))}</p></div><div class="smile-art" role="img" aria-label="Always Smile"></div></header>
    <div class="decision-grid">${cards}</div>
    <div class="dashboard-grid"><section class="card pulse-panel"><h2>${esc(t('housePulse'))}</h2><p class="subtle">${esc(t('balancedHouse'))}</p>
    ${healthRow('accounts','people',data.health.blockers?num(data.health.blockers)+' '+t('blockers'):t('noBlockers'),t('ownVisibleTasks'),data.health.blockers?'attention':'neutral')}
    ${healthRow('accounts','customers',data.health.open_cases?num(data.health.open_cases)+' '+t('openCases'):t('unknown'),t('serviceHealthReview'),data.health.open_cases?'attention':'neutral','service')}
    ${healthRow('commercial','commercial',t(data.can_review_commercial?'needsReview':'noPermission'),t('economicsNotInferred'),'neutral','commercial')}
    ${healthRow('opportunities','strategic',data.health.assumptions_due?num(data.health.assumptions_due)+' '+t('reviewDue'):t('noDueReview'),t('datedAssumptions'),data.health.assumptions_due?'attention':'neutral','insights')}
    </section><section class="card noticed-panel"><h2><span class="spark">✦</span> ${esc(t('kozaNoticed'))}</h2><div class="noticed-body"><div class="notice-textile" aria-hidden="true"></div><div><h3>${esc(suggestion?suggestion.title:t('noSuggestedAction'))}</h3><p>${esc(t(suggestion?'reason_reorder_due':'suggestionEmpty'))}</p>${suggestion?`<p>${esc(date(suggestion.review_on))}</p>`:''}</div></div><div class="noticed-actions">${suggestion?recordButton(suggestion):openLink('orders',t('orders'))}<span class="subtle">${esc(t('ruleBased'))}</span></div></section>
    <section class="card schedule-panel"><h2>${esc(t('comingNext'))}</h2>${data.upcoming.length?data.upcoming.map(item).join(''):`<div class="designed-empty">${icon('myfocus')}<p>${esc(t('noUpcoming'))}</p>${openLink('myfocus',t('myfocus'))}</div>`}</section>
    <section class="card week-panel"><h2>${esc(t('thisWeek'))}</h2><p class="subtle">${esc(date(data.week_from))} – ${esc(date(data.week_to))}</p><div class="week-content"><div class="week-metrics">${[['progressed','opportunities'],['reordered','orders'],['promises','quality'],['resolved','service']].map(([k,ico])=>`<div class="week-metric">${icon(ico)}<strong>${data.has_records?num(data.weekly[k]):'—'}</strong><span>${esc(t('weekly_'+k))}</span></div>`).join('')}</div><div class="week-brand"><div class="smile-art" role="img" aria-label="Always Smile"></div><p>${esc(t('strongerHouse'))}</p></div></div><p class="footnote">${esc(t(data.has_records?'weeklyDefinition':'noMeasuredData'))}</p></section>
    <section class="card activity-panel"><h2>${esc(t('recentActivity'))}</h2>${data.recent.length?data.recent.map(e=>`<div class="activity-row"><span class="schedule-icon">${icon(e.record.type==='quote'?'quotes':e.record.type==='order'?'orders':'documents')}</span><div>${recordButton(e.record)}<div class="row-meta">${esc(t('event_'+e.action,t('recordChanged')))}</div></div><time>${esc(date(e.occurred_at,true))}</time></div>`).join(''):`<div class="designed-empty">${icon('documents')}<p>${esc(t('noRecentActivity'))}</p></div>`}</section></div>
    <form class="askbar" id="homeAsk"><b><span class="spark">✦</span> ${esc(t('ask'))}</b><input name="question" aria-label="${esc(t('ask'))}" placeholder="${esc(t('searchPlaceholder'))}"><button class="btn" type="submit">${esc(t('sourceSearch'))}</button><button class="chip" type="button" data-go="quotes">${esc(t('decisionsShortcut'))}</button><button class="chip" type="button" data-go="service">${esc(t('serviceShortcut'))}</button></form><p class="footnote ai-note">${esc(t('askHelp'))}</p></section>`;
    bindRecords(page);$$('[data-go]',page).forEach(b=>b.onclick=()=>navigate(b.dataset.go));
    $('#homeAsk').onsubmit=e=>{e.preventDefault();const q=new FormData(e.target).get('question');navigate('ask');$('#askQuestion').value=q;$('#askForm').requestSubmit();};
  }
  async function focus(generation){
    const data=await api('/records?type=task&mine=1&page='+state.page);if(generation!==state.generation)return;
    page.innerHTML=baseHeader(t('myfocus'),t('focusHelp'),btn('new',t('new'),'btn primary'))+`<div class="grid">${['now','next','waiting'].map(s=>`<div class="card"><h2>${esc(t(s))}</h2>${data.records.filter(r=>r.state===s||(s==='next'&&r.state==='draft')).map(r=>`<div class="row"><div>${recordButton(r)}<div class="row-meta">${esc(r.data.reason||r.data.next_step||'')}</div><div class="row-meta">${esc(date(r.due_at))}</div></div>${badge(r.state)}</div>`).join('')||`<div class="focus-empty">${esc(t('empty'))}</div>`}</div>`).join('')}</div>`+pagination(data);
    bindRecords(page);bindPagination(data);$('[data-action=new]',page).onclick=()=>openEditor(null,'task');
  }
  async function openRecord(id){
    $('#detailContent').innerHTML=`<button class="dialog-close" data-close="detailDialog" aria-label="${esc(t('close'))}">×</button><h2 id="detailTitle">${esc(t('loading'))}</h2>`;if(!detail.open)detail.showModal();
    try{const r=await api('/records/'+id);if(!detail.open)return;state.record=r;drawDetail(r);}catch(error){showError(error,$('#detailContent'));}
  }
  function displayValue(value,f){if(value===null||value==='')return '—';if(f.type==='bool')return value?(state.locale.startsWith('tr')?'Evet':'Yes'):(state.locale.startsWith('tr')?'Hayır':'No');if(f.type==='date'||f.type==='datetime')return date(value,f.type==='datetime');if(['money','number'].includes(f.type))return num(value);if(f.type==='select')return stage(value);return String(value);}
  function drawDetail(r){
    const editable=r.editable;const fields=cat(r.type).fields;
    const actions=[];
    if(editable&&!r.immutable)actions.push(btn('edit',t('edit'),'btn primary'));
    if(editable&&['variant','sample','quote','offering','right'].includes(r.type))actions.push(btn('revision',t('revision'),'btn soft'));
    if(domain('operations')&&!r.immutable&&['variant','sample','quote'].includes(r.type))actions.push(btn('verify',t('verify')));
    if(domain('commercial')&&!r.immutable&&['quote','sample'].includes(r.type))actions.push(btn('approve',t('approve')));
    if(editable&&r.state==='approved'&&['sample','quote'].includes(r.type))actions.push(btn('dispatch',t('dispatch')));
    if(editable&&r.type==='quote'&&r.state==='sent')actions.push(btn('accept',t('accept')));
    if(editable&&r.type==='sample'&&r.state==='sent')actions.push(btn('sample_delivered',t('sample_delivered')));
    if(editable&&r.type==='sample'&&['sent','delivered'].includes(r.state))actions.push(btn('sample_feedback',t('sample_feedback')));
    if(editable&&r.type==='opportunity'&&r.state==='negotiation')actions.push(btn('won',t('won'),'btn primary'));
    if(r.type==='order'&&r.state==='delivered'&&(domain('market')||domain('commercial')))actions.push(btn('reorder',t('reorder')));
    if(editable&&!r.immutable&&!['quote','sample','variant','offering','right'].includes(r.type))actions.push(btn('transition',t('transition')));
    if(r.type==='right'){
      actions.push(btn('stop_ai',t('stop_ai'),'btn danger'));
      if(!r.immutable&&domain('commercial'))actions.push(btn('right_business',t('right_business')));
      if(!r.immutable&&domain('system'))actions.push(btn('right_technical',t('right_technical')));
    }
    $('#detailContent').innerHTML=`<button class="dialog-close" data-close="detailDialog" aria-label="${esc(t('close'))}">×</button><div class="eyebrow">${esc(label(cat(r.type)))} · #${r.id} · V${r.edition}</div><h2 id="detailTitle">${esc(r.title)}</h2><div class="subtle">${esc(r.company||'')} · ${esc(r.owner)} · ${esc(t('updated'))} ${esc(date(r.updated_at,true))}</div><div class="stagebar">${badge(r.state)}<span class="chip">${esc(t('version'))} ${r.version}</span></div>
      ${r.immutable?`<div class="callout">${esc(t('immutableNotice'))}</div>`:''}<div class="actions">${actions.join('')}</div>
      ${r.lines.length?lineTable(r):''}
      ${r.economics?`<div class="summary-grid">${['net_sales','cost','contribution'].map((k,i)=>`<div class="kpi"><b>${esc(money(r.economics[k],r.economics.currency))}</b><span>${esc(t(['netSales','costTotal','contribution'][i]))}</span></div>`).join('')}</div><div class="footnote">${esc(t('estimateHelp'))} ${r.economics.rate!=null?num(r.economics.rate)+'%':''}</div>`:''}
      <dl class="detail-fields">${Object.entries(r.data).filter(([k])=>fields[k]).map(([k,v])=>`<div><dt>${esc(label(fields[k]))}</dt><dd>${esc(displayValue(v,fields[k]))}</dd></div>`).join('')}<div><dt>${esc(t('source'))}</dt><dd>${esc(r.source||'—')}</dd></div><div><dt>${esc(t('due'))}</dt><dd>${esc(date(r.due_at))}</dd></div></dl>
      <h3>${esc(t('relations'))}</h3><div class="related-list">${r.links.map(l=>`<div class="related-row"><span class="badge">${esc(relationLabel(l.relation))}</span>${recordButton({id:l.target_id,title:l.title})}<span>${esc(l.quantity?num(l.quantity)+' '+l.unit:'')}</span></div>`).join('')||`<p class="subtle">${esc(t('empty'))}</p>`}</div><div class="actions">${btn('trace',t('trace'),'btn soft')}${btn('print',t('print'))}${editable?btn('attach',t('attachDocument')):''}</div>
      <h3>${esc(t('documents'))}</h3>${(r.documents||[]).map(d=>`<div class="row"><span>${esc(d.name)} · V${d.version}</span>${d.download?`<a class="btn" href="${esc(d.download)}">${esc(t('download'))}</a>`:''}</div>`).join('')||`<p class="subtle">${esc(t('noDocuments'))}</p>`}
      <h3>${esc(t('approvals'))}</h3>${(r.approvals||[]).map(a=>`<div class="row"><div>${esc(t(a.kind==='commercial'?'commercialApproval':a.kind))} · V${a.version}<div class="row-meta">${esc(date(a.created_at,true))}</div></div><span class="badge ${a.current?'good':'warn'}">${esc(t(a.current?'current':'superseded'))}</span></div>`).join('')||`<p class="subtle">${esc(t('empty'))}</p>`}
      <details><summary>${esc(t('company'))} · ${esc(t('relations'))}</summary><div class="related-list">${(r.related||[]).map(v=>`<div class="related-row">${recordButton(v)}<span>${esc(label(cat(v.type)))}</span>${badge(v.state)}</div>`).join('')}</div></details><h3>${esc(t('history'))}</h3><div class="timeline">${r.history.map(ev=>`<div class="event"><b>${esc(stage(ev.action))}</b><p>${esc(ev.name)} · ${esc(date(ev.created_at,true))} · V${ev.version}</p>${btn('history',t('open'),'text-button',`data-event="${ev.id}"`)}</div>`).join('')}</div>`;
    bindRecords($('#detailContent'));
    $$('[data-action]',$('#detailContent')).forEach(b=>b.onclick=()=>{
      const a=b.dataset.action;
      if(a==='edit')openEditor(r,r.type);else if(a==='trace')trace(r);else if(a==='print')window.print();else if(a==='attach')uploadForm(null,r.id);else if(a==='history')showHistory(r,Number(b.dataset.event));else actionForm(r,a);
    });
  }
  function lineTable(r){return `<h3>${esc(t('lines'))}</h3><div class="table-wrap"><table><thead><tr>${['variant','quantity','unit','unitPrice',...(state.boot.user.read_cost?['unitCost']:[])].map(k=>`<th>${esc(t(k))}</th>`).join('')}</tr></thead><tbody>${r.lines.map(l=>`<tr><td>${recordButton({id:l.variant_id,title:l.specification.title})}<div class="row-meta">V${l.specification.edition} · ${esc(l.sku_id?'SKU #'+l.sku_id:'')}</div></td><td>${num(l.quantity)}</td><td>${esc(l.unit)}</td><td class="money">${esc(money(l.unit_price,r.data.currency))}</td>${state.boot.user.read_cost?`<td class="money">${esc(money(l.unit_cost,r.data.currency))}</td>`:''}</tr>`).join('')}</tbody></table></div>`;}
  function relationLabel(key){return cat(key)?label(cat(key)):t(key,key.replaceAll('_',' '));}
  function fieldHTML(key,cfg,value='',prefix='data'){
    const id='f-'+prefix+'-'+key;const name=prefix?prefix+'['+key+']':key;
    const attrs=`id="${esc(id)}" name="${esc(name)}"`;
    if(cfg.type==='bool')return `<label class="field field-check" for="${id}"><input ${attrs} type="checkbox" ${value?'checked':''}>${esc(label(cfg))}</label>`;
    let input;
    if(cfg.type==='textarea')input=`<textarea ${attrs} maxlength="6000">${esc(value)}</textarea>`;
    else if(cfg.type==='select')input=`<select ${attrs}><option value="">—</option>${cfg.options.map(v=>`<option value="${esc(v)}" ${value===v?'selected':''}>${esc(stage(v))}</option>`).join('')}</select>`;
    else {let type={datetime:'datetime-local',money:'text',number:'text'}[cfg.type]||cfg.type;
      let val=value;if(cfg.type==='datetime'&&value)val=String(value).replace(' ','T').slice(0,16);
      input=`<input ${attrs} type="${type}" value="${esc(val)}" ${['money','number'].includes(cfg.type)?'inputmode="decimal"':''} maxlength="1000">`;
    }
    return `<label class="field ${cfg.type==='textarea'?'field-wide':''}" for="${id}"><span>${esc(label(cfg))}${cfg.type==='datetime'?' · UTC':''}</span>${input}</label>`;
  }
  const simpleField=(key,trKey,value='',type='text',extra={})=>fieldHTML(key,{label:{tr:t(trKey),en:t(trKey)},type,...extra},value,'');
  function formFrame(title,content,submitText=t('save')){
    $('#formContent').innerHTML=`<button class="dialog-close" data-close="formDialog" aria-label="${esc(t('close'))}">×</button><h2 id="formTitle">${esc(title)}</h2><form id="editorForm"><div data-errors></div>${content}<div class="sticky-actions">${btn('cancel',t('cancel'),'btn','type="button"')}<button class="btn primary" type="submit">${esc(submitText)}</button></div></form>`;
    $('[data-action=cancel]',$('#formContent')).onclick=closeForm;
    $('#editorForm').addEventListener('input',()=>state.dirty=true);
    if(!formDialog.open)formDialog.showModal();
    state.dirty=false;
  }
  function closeForm(){if(state.dirty&&!confirm(t('unsaved')))return;state.dirty=false;formDialog.close();editContext=null;}
  function companySelect(value){return `<label class="field"><span>${esc(t('company'))}</span><select name="company_id"><option value="">${esc(t('chooseCompany'))}</option>${state.boot.companies.map(c=>`<option value="${c.id}" ${c.id===value?'selected':''}>${esc(c.name)} · ${esc(c.country_code)}</option>`).join('')}</select></label>`;}
  async function openEditor(record,type,companyId=null){
    editContext={record,type,links:structuredClone(record?.links||[]),lines:structuredClone(record?.lines||[])};
    const fields=cat(type).fields;const write=record?.write_fields||cat(type).write_fields;
    formFrame((record?t('edit'):t('new'))+' · '+label(cat(type)),`<p class="subtle">${esc(t('gateHelp'))}</p><div class="form-grid">${simpleField('title','title',record?.title||'')}${companySelect(record?.company_id||companyId)}<label class="field"><span>${esc(t('owner'))}</span><select name="owner_id">${state.boot.people.map(u=>`<option value="${u.id}" ${(record?.owner_id||state.boot.user.id)===u.id?'selected':''}>${esc(u.name)}</option>`).join('')}</select></label>${type==='contact'?`<label class="field"><span>${esc(t('existingContacts'))}</span><select name="contact_id"><option value="">—</option>${state.boot.contacts.map(c=>`<option value="${c.id}" ${record?.contact_id===c.id?'selected':''}>${esc(c.name)} · ${esc(c.company?.name||'')}</option>`).join('')}</select></label>`:''}${simpleField('source','source',record?.source||'')}${simpleField('due_at','due',record?.due_at||'','date')}</div><fieldset class="form-section"><legend>${esc(t('details'))}</legend><div class="form-grid">${Object.entries(fields).filter(([k])=>write.includes(k)).map(([k,cfg])=>fieldHTML(k,cfg,record?.data[k]??(cfg.type==='bool'?false:''))).join('')}</div></fieldset>
      ${type==='quote'?`<fieldset class="form-section"><legend>${esc(t('lines'))}</legend><div id="lineRows"></div>${btn('add-line',t('addLine'),'btn soft','type="button"')}</fieldset>`:''}
      <fieldset class="form-section"><legend>${esc(t('relations'))}</legend><div id="relationRows"></div><div class="actions">${Object.keys(cat(type).relations).map(k=>btn('add-relation',relationLabel(k),'btn soft',`type="button" data-relation="${k}"`)).join('')}</div></fieldset>`);
    if(record?.company_id)$('[name=company_id]',$('#editorForm')).disabled=true;
    renderLinks();if(type==='quote')renderLines();
    $$('[data-action=add-relation]',$('#editorForm')).forEach(b=>b.onclick=async()=>{
      const rel=b.dataset.relation;const selected=await pickRecord(cat(type).relations[rel]);if(!selected)return;
      if(editContext.links.some(l=>l.relation===rel&&l.target_id===selected.id))return;
      editContext.links.push({relation:rel,target_id:selected.id,title:selected.title,type:selected.type,quantity:'',unit:''});state.dirty=true;renderLinks();
    });
    $('[data-action=add-line]',$('#editorForm'))?.addEventListener('click',async()=>{
      captureLines();const selected=await pickRecord(['variant']);if(!selected)return;
      editContext.lines.push({variant_id:selected.id,sku_id:null,quantity:'',unit:selected.data.unit||'',unit_price:'',unit_cost:'',specification:{title:selected.title,edition:selected.edition}});state.dirty=true;renderLines();
    });
    $('#editorForm').onsubmit=async e=>{
      e.preventDefault();const form=e.target;const fd=new FormData(form);const data={};
      Object.entries(fields).filter(([k])=>write.includes(k)).forEach(([k,cfg])=>{let value=fd.get('data['+k+']');if(cfg.type==='bool')value=!!value;else if(['number','money'].includes(cfg.type)&&value)value=value.replace(',','.').trim();data[k]=value===''?null:value;});
      captureLinks();captureLines();
      const payload={type,owner_id:Number(fd.get('owner_id'))||state.boot.user.id,contact_id:Number(fd.get('contact_id'))||null,title:fd.get('title'),company_id:record?.company_id||Number(fd.get('company_id'))||null,source:fd.get('source')||null,due_at:fd.get('due_at')||null,data,links:editContext.links.map(({relation,target_id,quantity,unit})=>({relation,target_id,quantity:quantity?String(quantity).replace(',','.'):null,unit:unit||null}))};
      if(record)payload.version=record.version;
      if(type==='quote')payload.lines=editContext.lines.map(l=>({...(l.id?{id:l.id}:{}),variant_id:l.variant_id,sku_id:l.sku_id||null,quantity:String(l.quantity).replace(',','.'),unit:l.unit,unit_price:String(l.unit_price).replace(',','.'),...(state.boot.user.read_cost?{unit_cost:l.unit_cost===''||l.unit_cost==null?null:String(l.unit_cost).replace(',','.')}: {})}));
      await submit(form,async()=>{const saved=await api('/records'+(record?'/'+record.id:''),{method:record?'PUT':'POST',body:payload});await afterSave(saved);});
    };
  }
  async function submit(form,fn){const button=$('button[type=submit]',form);button.disabled=true;const text=button.textContent;button.textContent=t('saving');try{await fn();}catch(error){showError(error,form);}finally{button.disabled=false;button.textContent=text;}}
  async function afterSave(record){state.dirty=false;formDialog.close();toast(t('saved'));await bootstrap();await render();if(record)await openRecord(record.id);}
  function captureLinks(){if(!editContext)return;$$('[data-link-row]',$('#relationRows')).forEach(row=>{const l=editContext.links[Number(row.dataset.linkRow)];l.quantity=$('[name=quantity]',row).value;l.unit=$('[name=unit]',row).value;});}
  function renderLinks(){const root=$('#relationRows');if(!root)return;root.innerHTML=editContext.links.map((l,i)=>`<div class="related-row" data-link-row="${i}"><span class="badge">${esc(relationLabel(l.relation))}</span><span>${esc(l.title)} · #${l.target_id}</span><label class="field"><span>${esc(t('quantity'))}</span><input name="quantity" inputmode="decimal" value="${esc(l.quantity||'')}"></label><label class="field"><span>${esc(t('unit'))}</span><input name="unit" value="${esc(l.unit||'')}"></label>${btn('remove-link','×','chip',`type="button" data-index="${i}" aria-label="${esc(t('remove'))}"`)}</div>`).join('');$$('[data-action=remove-link]',root).forEach(b=>b.onclick=()=>{captureLinks();editContext.links.splice(Number(b.dataset.index),1);state.dirty=true;renderLinks();});}
  function captureLines(){if(!editContext||!$('#lineRows'))return;$$('[data-line-row]',$('#lineRows')).forEach(row=>{const l=editContext.lines[Number(row.dataset.lineRow)];['quantity','unit','unit_price','unit_cost'].forEach(k=>{if($('[name='+k+']',row))l[k]=$('[name='+k+']',row).value;});});}
  function renderLines(){const root=$('#lineRows');root.innerHTML=editContext.lines.map((l,i)=>`<div class="line-editor" data-line-row="${i}"><div class="variant-select"><b>${esc(l.specification.title)}</b><div class="row-meta">V${l.specification.edition}</div>${btn('select-sku',l.sku_id?'SKU #'+l.sku_id:t('sku'),'chip',`type="button" data-index="${i}"`)}</div>${['quantity','unit','unit_price',...(state.boot.user.read_cost?['unit_cost']:[])].map(k=>`<label class="field"><span>${esc(t({unit_price:'unitPrice',unit_cost:'unitCost'}[k]||k))}</span><input name="${k}" ${k==='unit'?'':'inputmode="decimal"'} value="${esc(l[k]??'')}"></label>`).join('')}${btn('remove-line','×','chip',`type="button" data-index="${i}" aria-label="${esc(t('remove'))}"`)}</div>`).join('');
    $$('[data-action=remove-line]',root).forEach(b=>b.onclick=()=>{captureLines();editContext.lines.splice(Number(b.dataset.index),1);state.dirty=true;renderLines();});
    $$('[data-action=select-sku]',root).forEach(b=>b.onclick=async()=>{captureLines();const r=await pickRecord(['sku']);if(r){editContext.lines[Number(b.dataset.index)].sku_id=r.id;state.dirty=true;renderLines();}});
  }
  function pickRecord(allowed){
    return new Promise(resolve=>{pickerResolve=resolve;let p=1,q='',type=allowed[0],seq=0;
      const load=async()=>{const current=++seq;$('#pickerContent').innerHTML=`<button class="dialog-close" data-close="pickerDialog" aria-label="${esc(t('close'))}">×</button><h2 id="pickerTitle">${esc(t('selectRecord'))}</h2><form class="toolbar" id="pickerSearch"><select name="type" aria-label="${esc(t('category'))}">${allowed.map(k=>`<option value="${k}" ${k===type?'selected':''}>${esc(label(cat(k)))}</option>`).join('')}</select><input type="search" name="q" value="${esc(q)}" aria-label="${esc(t('searchPlaceholder'))}"><button class="btn">${esc(t('filter'))}</button></form><div id="pickerRows">${esc(t('loading'))}</div>`;
        $('#pickerSearch').onsubmit=e=>{e.preventDefault();const fd=new FormData(e.target);q=fd.get('q');type=fd.get('type');p=1;load();};
        try{const data=await api('/records?'+new URLSearchParams({type,q,page:p}));if(seq!==current||!picker.open)return;$('#pickerRows').innerHTML=data.records.map(r=>`<div class="row"><div><b>${esc(r.title)}</b><div class="row-meta">#${r.id} · ${esc(r.company||'')} · ${esc(stage(r.state))} · V${r.edition}</div></div>${btn('choose',t('choose'),'btn',`data-id="${r.id}"`)}</div>`).join('')||`<p>${esc(t('noResults'))}</p>`;$('#pickerRows').insertAdjacentHTML('beforeend',pagination(data));$$('[data-action=choose]',$('#pickerRows')).forEach(b=>b.onclick=()=>{const chosen=data.records.find(r=>r.id===Number(b.dataset.id));pickerResolve=null;picker.close();resolve(chosen);});$('[data-action=previous]',$('#pickerRows')).onclick=()=>{p--;load();};$('[data-action=nextPage]',$('#pickerRows')).onclick=()=>{p++;load();};}catch(error){showError(error,$('#pickerRows'));}
      };picker.showModal();load();
    });
  }
  function actionForm(r,action){
    const stateField=action==='transition'?simpleField('state','stage','', 'select',{options:cat(r.type).states.filter(s=>s!==r.state&&s!=='draft'&&s!=='won')}):'';
    formFrame(t(action),`${action==='dispatch'?`<p class="callout">${esc(t('dispatchHelp'))}</p>`:''}<div class="form-grid">${stateField}${simpleField('reason','reason','','textarea')}${['dispatch','accept','sample_delivered','sample_feedback'].includes(action)?simpleField('evidence','evidence','','textarea'):''}${action==='sample_delivered'?simpleField('occurred_at','eventTime','','datetime'):''}</div>`,t('confirmAction'));
    $('#editorForm').onsubmit=e=>{e.preventDefault();const values=Object.fromEntries(new FormData(e.target));submit(e.target,async()=>{const result=await api('/records/'+r.id+'/actions',{method:'POST',body:{...values,version:r.version,action}});await afterSave(result);});};
  }
  async function trace(r){try{const result=await api('/records/'+r.id+'/trace');const names=Object.fromEntries(result.nodes.map(n=>[n.id,n]));$('#detailContent').innerHTML=`<button class="dialog-close" data-close="detailDialog" aria-label="${esc(t('close'))}">×</button><h2 id="detailTitle">${esc(t('trace'))}</h2><p class="subtle">${esc(t('traceHelp'))}</p>${recordButton(r)}<div class="related-list">${result.edges.map(l=>`<div class="related-row">${recordButton(names[l.record_id])}<span>→ ${esc(relationLabel(l.relation))} →</span>${recordButton(names[l.target_id])}<span class="badge">${l.quantity?num(l.quantity)+' '+esc(l.unit):'—'}</span></div>`).join('')||`<p>${esc(t('empty'))}</p>`}</div>${result.truncated?`<p class="callout">${esc(t('traceLimit'))}</p>`:''}`;bindRecords($('#detailContent'));}catch(error){showError(error,$('#detailContent'));}}
  async function showHistory(r,id){try{const data=await api('/records/'+r.id+'/history/'+id);formFrame(t('history')+' · #'+id,'<pre id="historyText"></pre>',t('close'));$('#historyText').textContent=JSON.stringify(data,null,2);$('#editorForm').onsubmit=e=>{e.preventDefault();closeForm();};}catch(error){showError(error,$('#detailContent'));}}
  function companyForm(company=null){formFrame(t(company?'edit':'newCompany'),`<div class="form-grid">${['name','country_code','city','email','phone','website','tax_number'].map(k=>simpleField(k,{country_code:'country',tax_number:'tax'}[k]||k,company?.[k]||'',k==='email'?'email':k==='website'?'url':'text')).join('')}<label class="field field-check"><input type="checkbox" name="customer" ${!company||company.roles.includes('customer')?'checked':''}>${esc(t('customer'))}</label><label class="field field-check"><input type="checkbox" name="supplier" ${company?.roles.includes('supplier')?'checked':''}>${esc(t('supplier'))}</label></div>`);$('#editorForm').onsubmit=e=>{e.preventDefault();const f=new FormData(e.target),data=Object.fromEntries(f);data.roles=['customer','supplier'].filter(k=>f.get(k));delete data.customer;delete data.supplier;if(company)data.version=company.version;submit(e.target,async()=>{await api('/companies'+(company?'/'+company.id:''),{method:company?'PUT':'POST',body:data});await afterSave();});};}
  async function documents(generation){const data=await api('/documents?'+new URLSearchParams({page:state.page,q:state.q}));if(generation!==state.generation)return;state.documents=data;page.innerHTML=baseHeader(t('documents'),'',data.can_upload?btn('upload',t('upload'),'btn primary'):'')+`<form class="toolbar" id="documentSearch"><input type="search" name="q" value="${esc(state.q)}" aria-label="${esc(t('searchPlaceholder'))}"><button class="btn">${esc(t('filter'))}</button><span class="result-count">${data.total} ${esc(t('records'))}</span></form>`+(data.records.length?`<div class="table-wrap"><table><thead><tr>${['name','category','version','documentDate','visibility','open'].map(k=>`<th>${esc(t(k))}</th>`).join('')}</tr></thead><tbody>${data.records.map(d=>`<tr><td><b>${esc(d.name)}</b><div class="row-meta">${esc(d.description||'')}</div></td><td>${esc(d.category)}</td><td>V${d.version}</td><td>${esc(date(d.date))}</td><td>${esc(t(d.visibility))}</td><td><div class="actions">${d.download?`<a class="btn" href="${esc(d.download)}">${esc(t('download'))}</a>`:''}${d.can_version?btn('doc-version',t('uploadVersion'),'btn soft',`data-id="${d.id}"`):''}</div></td></tr>`).join('')}</tbody></table></div>`:`<div class="card empty">${esc(t('empty'))}</div>`)+pagination(data);bindPagination(data);$('[data-action=upload]',page)?.addEventListener('click',()=>uploadForm());$$('[data-action=doc-version]',page).forEach(b=>b.onclick=()=>uploadForm(data.records.find(d=>d.id===Number(b.dataset.id))));$('#documentSearch').onsubmit=e=>{e.preventDefault();state.q=new FormData(e.target).get('q');state.page=1;render();};}
  async function uploadForm(documentRecord=null,recordId=null){try{const docs=state.documents||await api('/documents');formFrame(t(documentRecord?'uploadVersion':'upload'),`<div class="form-grid">${documentRecord?`<p>${esc(documentRecord.name)} · V${documentRecord.version}</p>`:`${simpleField('name','name')}${simpleField('description','description','','textarea')}<label class="field"><span>${esc(t('category'))}</span><select name="category_id">${docs.categories.map(c=>`<option value="${c.id}">${esc(c.name)}</option>`).join('')}</select></label>${simpleField('document_date','documentDate','','date')}${simpleField('expires_at','expiresAt','','date')}${simpleField('visibility','visibility','private','select',{options:['private','internal']})}`}${simpleField('note','note','','textarea')}<label class="field field-wide"><span>${esc(t('file'))}</span><input name="file" type="file" required></label></div>`);$('#editorForm').onsubmit=e=>{e.preventDefault();const body=new FormData(e.target);if(documentRecord)body.set('revision',documentRecord.revision);if(recordId)body.set('record_id',recordId);submit(e.target,async()=>{await api('/documents'+(documentRecord?'/'+documentRecord.id+'/versions':''),{method:'POST',body});await afterSave(recordId?{id:recordId}:null);});};}catch(error){toast(errorMessage(error));}}
  function ask(){page.innerHTML=baseHeader(t('ask'),t('askHelp'))+`<form class="askbar" id="askForm"><input id="askQuestion" name="question" required maxlength="500" placeholder="${esc(t('searchPlaceholder'))}" aria-label="${esc(t('ask'))}"><button class="btn primary" type="submit">${esc(t('sourceSearch'))}</button></form><div id="askResults" class="section-gap"></div>`;$('#askForm').onsubmit=async e=>{e.preventDefault();const button=$('button',e.target);button.disabled=true;$('#askResults').textContent=t('loading');try{const result=await api('/ask',{method:'POST',body:{question:new FormData(e.target).get('question')}});$('#askResults').innerHTML=table(result.records)+`<p class="footnote">${esc(t('sourceDate'))}: ${esc(date(result.generated_at,true))}</p>`;bindRecords($('#askResults'));}catch(error){showError(error,$('#askResults'));}finally{button.disabled=false;}};}
  async function commercial(generation){if(!state.boot.user.read_finance){page.innerHTML=baseHeader(t('commercial'),'')+`<div class="card">${esc(t('noPermission'))}</div>`;return;}page.innerHTML=baseHeader(t('commercial'),t('financeHelp'),cat('cost').can_create?btn('new-cost',label(cat('cost')),'btn soft'):'')+`<div class="callout">${esc(t('estimateHelp'))}</div><form class="toolbar" id="economicsForm"><label class="field"><span>${esc(t('periodFrom'))}</span><input type="date" name="from" required></label><label class="field"><span>${esc(t('periodTo'))}</span><input type="date" name="to" required></label>${companySelect(null)}<button class="btn primary" type="submit">${esc(t('calculate'))}</button></form><div id="economicResults"></div>`;$('[data-action=new-cost]',page)?.addEventListener('click',()=>openEditor(null,'cost'));$('#economicsForm').onsubmit=async e=>{e.preventDefault();const params=new URLSearchParams(new FormData(e.target));if(!params.get('company_id'))params.delete('company_id');const root=$('#economicResults');root.textContent=t('loading');try{const data=await api('/analytics?'+params);root.innerHTML=data.rows.length?`<div class="table-wrap"><table><thead><tr>${['company','currency','netSales','contribution','serviceCosts','financing','relationshipContribution','acquisition'].map(k=>`<th>${esc(t(k))}</th>`).join('')}</tr></thead><tbody>${data.rows.map(r=>`<tr><td>${esc(r.company||'—')}</td><td>${esc(r.currency)}</td>${['sales','contribution','service','financing','relationship','acquisition'].map(k=>`<td class="money">${esc(money(r[k],r.currency))}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`:`<div class="card empty">${esc(t('empty'))}</div>`;}catch(error){showError(error,root);}};}
  async function settings(generation){const data=await api('/settings');if(generation!==state.generation)return;const policy=data.policy||{};
    page.innerHTML=baseHeader(t('settings'),t('accessHelp'))+`<div class="grid-2"><div class="card"><h2>${esc(t('commercial'))}</h2><p class="callout">${esc(t('policyWarning'))}</p><form id="policyForm"><div data-errors></div><div class="form-grid">${['minimum_contribution','sample_budget','sample_currency','max_payment_days','cost_hours','stock_hours'].map(k=>simpleField(k,k,policy[k]??'',k==='sample_currency'?'select':'number',k==='sample_currency'?{options:['GBP','USD','TRY','EUR']}:{})).join('')}<div class="field field-wide"><span>${esc(t('senders'))}</span>${data.users.map(u=>`<label class="field field-check"><input type="checkbox" name="senders" value="${u.id}" ${policy.senders?.includes(u.id)?'checked':''}>${esc(u.name)}</label>`).join('')}</div>${simpleField('reason','policyReason','','textarea')}</div><div class="actions"><button class="btn primary" type="submit">${esc(t('save'))}</button></div></form></div><div class="card"><h2>${esc(t('owner'))}</h2><div id="accessRows">${data.users.map(u=>{const g=data.grants.find(g=>g.user_id===u.id);return `<div class="row"><div><b>${esc(u.name)}</b><div class="row-meta">${(g?.domains||[]).map(d=>esc(t('domain_'+d))).join(' · ')||'—'}</div></div>${btn('grant',t('edit'),'btn',`data-id="${u.id}"`)}</div>`;}).join('')}</div></div></div>`;
    $('#policyForm').onsubmit=e=>{e.preventDefault();const fd=new FormData(e.target);const body=Object.fromEntries(fd);body.version=policy.version||0;body.senders=fd.getAll('senders').map(Number);submit(e.target,async()=>{await api('/settings/policy',{method:'PUT',body});toast(t('saved'));await bootstrap();render();});};
    $$('[data-action=grant]',page).forEach(b=>b.onclick=()=>{const u=data.users.find(u=>u.id===Number(b.dataset.id)),g=data.grants.find(g=>g.user_id===u.id)||{};formFrame(t('edit')+' · '+u.name,`<div class="form-grid">${['market','operations','commercial','system'].map(d=>`<label class="field field-check"><input type="checkbox" name="domains" value="${d}" ${g.domains?.includes(d)?'checked':''}>${esc(t('domain_'+d))}</label>`).join('')}${simpleField('read_cost','read_cost',!!g.read_cost,'bool')}${simpleField('read_finance','read_finance',!!g.read_finance,'bool')}${simpleField('reason','reason','','textarea')}</div>`);$('#editorForm').onsubmit=e=>{e.preventDefault();const fd=new FormData(e.target);submit(e.target,async()=>{await api('/settings/access',{method:'PUT',body:{user_id:u.id,domains:fd.getAll('domains'),read_cost:!!fd.get('read_cost'),read_finance:!!fd.get('read_finance'),reason:fd.get('reason')}});await afterSave();});};});
  }
  function profile(){formFrame(t('profile')+' · '+state.boot.user.name,`<div class="form-grid"><label class="field"><span>${esc(t('language'))}</span><select name="locale">${[['tr-TR','TR'],['en','ENG']].map(([v,label])=>`<option value="${v}" ${state.locale===v?'selected':''}>${label}</option>`).join('')}</select></label><label class="field"><span>${esc(t('timezone'))}</span><select name="timezone">${['Europe/Istanbul','Europe/London','America/New_York','America/Chicago','America/Denver','America/Los_Angeles'].map(v=>`<option value="${v}" ${state.timezone===v?'selected':''}>${v.replaceAll('_',' ')}</option>`).join('')}</select></label></div><div class="actions">${btn('logout',t('logout'),'btn danger','type="button"')}</div>`);$('#editorForm').onsubmit=e=>{e.preventDefault();submit(e.target,async()=>{await api('/preferences',{method:'PUT',body:Object.fromEntries(new FormData(e.target))});await afterSave();});};$('[data-action=logout]',$('#formContent')).onclick=async()=>{const form=document.createElement('form');form.method='POST';form.action='/logout';const input=document.createElement('input');input.name='_token';input.value=$('meta[name=csrf-token]').content;form.append(input);document.body.append(form);form.submit();};}
  document.addEventListener('click',e=>{const b=e.target.closest('[data-close]');if(!b)return;if(b.dataset.close==='formDialog')closeForm();else $('#'+b.dataset.close).close();});
  formDialog.addEventListener('cancel',e=>{if(state.dirty){e.preventDefault();closeForm();}});
  picker.addEventListener('close',()=>{if(pickerResolve){pickerResolve(null);pickerResolve=null;}});
  window.addEventListener('beforeunload',e=>{if(state.dirty){e.preventDefault();e.returnValue='';}});
  $('#menuToggle').onclick=()=>{const open=$('#sidebar').classList.toggle('mobile-open');$('#menuToggle').setAttribute('aria-expanded',String(open));};
  $('#profileButton').onclick=profile;
  $('#localeSelect').onchange=async e=>{try{await api('/preferences',{method:'PUT',body:{locale:e.target.value,timezone:state.timezone}});state.locale=e.target.value;state.boot.locale=state.locale;shell();render();}catch(error){toast(errorMessage(error));}};
  $('#searchForm').onsubmit=e=>{e.preventDefault();state.q=$('#search').value;state.view='search';state.type=null;state.page=1;shell();render();};
  document.addEventListener('keydown',e=>{if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();$('#search').focus();}});
  (async()=>{try{await bootstrap();const [v,type]=location.hash.slice(1).split('/');if(menus.some(([,items])=>items.some(([key])=>key===v)))navigate(v,types[v]?.includes(type)?type:null);else navigate('today');}catch(error){page.setAttribute('aria-busy','false');page.innerHTML=`<div class="card">${btn('retry',t('retry'))}<a class="btn" href="/login">${esc(t('expiredSession'))}</a></div>`;showError(error);$('[data-action=retry]',page).onclick=()=>location.reload();}})();
})();
