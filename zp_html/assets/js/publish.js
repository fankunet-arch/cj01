/* 发布页：大区→城市联动、实时预览、fetch 提交（成功跳详情页） */
const ICON_PIN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>';
const ICON_TEL = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>';
const ICON_WX  = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 4C5 4 2 6.6 2 9.9c0 1.8 1 3.4 2.6 4.5L4 17l2.7-1.4c.7.2 1.5.3 2.3.3h.6a4.6 4.6 0 0 1-.2-1.3c0-3 2.9-5.3 6.4-5.3h.6C15.8 6 12.7 4 9 4z"/></svg>';

const $=id=>document.getElementById(id);
function toast(msg){
  const t=$('toast'); $('toastMsg').textContent=msg;
  t.classList.add('show'); setTimeout(()=>t.classList.remove('show'),2600);
}

// 大区 → 城市联动（ZP_CITIES 由服务端注入）
function fillCities(){
  const topId = $('fRegionTop').value;
  const cities = (window.ZP_CITIES || {})[topId] || [];
  const sel = $('fRegion');
  sel.innerHTML = '';
  if(cities.length === 0){
    // 无下级城市的大区（休达/梅利利亚）：直接用大区作为地区
    sel.innerHTML = `<option value="${topId}">${$('fRegionTop').selectedOptions[0].textContent}</option>`;
  }else{
    cities.forEach(c=>{ sel.innerHTML += `<option value="${c.id}">${c.name}</option>`; });
  }
  updatePreview();
}
$('fRegionTop').addEventListener('change', fillCities);

function maskTel(t){ const d=t.replace(/\D/g,''); return d?d.slice(0,3)+' ·· ··':'—'; }
function firstLine(s){ const l=(s.split('\n')[0]||'').trim(); return l.length>40?l.slice(0,40)+'…':l; }

function updatePreview(){
  const mode = document.body.dataset.mode;
  const cat = $('fCat').selectedOptions[0]?.textContent || '';
  const content = $('fContent').value.trim();
  const name = $('fName').value.trim();
  const region = $('fRegion').selectedOptions[0]?.textContent.split(' ')[0] || '';
  const tel = $('fTel').value.trim();
  const wx = $('fWx').value.trim();
  const titleHTML = content
    ? `<div class="ptitle">${firstLine(content).replace(/</g,'&lt;')}</div>`
    : `<div class="ptitle ph">（填写信息内容，这里实时预览）</div>`;
  $('preview').innerHTML = `
    <div class="ctop"><span class="tag">${mode==='seek'?'求职 · ':''}${cat}</span><span class="new">今日新</span><span class="ago">刚刚</span></div>
    ${titleHTML}
    <div class="meta">${ICON_PIN}${region}${name?' · '+name.replace(/</g,'&lt;'):''}</div>
    <div class="cfoot">
      <div class="pay"><small>新发布</small></div>
      <div class="cact">
        <button type="button" class="call">${ICON_TEL}拨打 <span class="num">${maskTel(tel)}</span></button>
        ${wx?`<button type="button" class="wx">${ICON_WX}</button>`:''}
      </div>
    </div>`;
}

// 招人/找活切换
document.querySelectorAll('.seg button[data-mode]').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const mode=btn.dataset.mode;
    document.body.dataset.mode=mode;
    document.querySelectorAll('.seg button[data-mode]').forEach(b=>b.classList.toggle('on',b.dataset.mode===mode));
    const hire = mode==='hire';
    $('fType').value = hire?'1':'2';
    $('labZona1').innerHTML = (hire?'所在大区':'现居大区')+' <span class="req">*</span>';
    $('fContent').placeholder = hire
      ? '如：急招川菜炒锅师傅，有经验优先。做六休一提供食宿，待遇面谈。\n\n⚠ 电话和微信请填下面的联系方式栏，不要写在这里'
      : '如：找帮厨/洗碗工作，能吃苦可全职，有两年餐馆经验。\n\n⚠ 电话和微信请填下面的联系方式栏，不要写在这里';
    $('submitBtn').textContent = hire?'发布招聘':'发布求职';
    updatePreview();
  });
});

['fCat','fContent','fName','fTel','fWx','fRegion'].forEach(id=>{
  $(id).addEventListener('input',updatePreview);
  $(id).addEventListener('change',updatePreview);
});

// 提交
$('pubForm').addEventListener('submit', async e=>{
  e.preventDefault();
  const btn = $('submitBtn'); const errEl = $('formErr');
  errEl.textContent = '';
  btn.disabled = true; btn.textContent = '发布中…';
  try{
    const r = await fetch('/publish', {method:'POST', body:new FormData($('pubForm'))});
    const d = await r.json().catch(()=>({}));
    if(r.ok && d.ok){
      toast('发布成功，正在跳转…');
      location.href = d.url;
      return;
    }
    errEl.textContent = d.msg || '发布失败，请检查填写内容';
    if(d.error==='duplicate' && d.url){ errEl.innerHTML += ` <a href="${d.url}">查看已发布的信息 →</a>`; }
  }catch(err){
    errEl.textContent = '网络错误，请稍后再试';
  }
  btn.disabled = false;
  btn.textContent = document.body.dataset.mode==='seek'?'发布求职':'发布招聘';
});

fillCities();
updatePreview();
