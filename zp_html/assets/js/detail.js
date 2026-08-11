/* 详情页：点击取号 + 微信查看/复制 + 举报 + 失效反馈（全部走真实 API） */
const $=id=>document.getElementById(id);
function toast(msg){
  const t=$('toast'); $('toastMsg').textContent=msg;
  t.classList.add('show'); setTimeout(()=>t.classList.remove('show'),2400);
}

async function api(url, payload){
  const r = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
  const d = await r.json().catch(()=>({}));
  if(!r.ok || !d.ok) throw new Error(d.msg || '操作失败，请稍后再试');
  return d;
}

// 电话：第一次点取号显示，再点拨打
document.querySelectorAll('.js-call').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    if(btn.dataset.tel){ location.href='tel:'+btn.dataset.tel.replace(/\s/g,''); return; }
    try{
      const d = await api('/get_contact', {code:btn.dataset.code, ctype:'phone'});
      document.querySelectorAll('.js-call').forEach(b=>{
        b.dataset.tel = d.value;
        b.querySelector('.num').textContent = d.value;
      });
      toast('再点一次直接拨打');
    }catch(err){ toast(err.message); }
  });
});

// 微信：取号显示并复制
document.querySelectorAll('.js-wx').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    try{
      const d = await api('/get_contact', {code:btn.dataset.code, ctype:'wechat'});
      document.querySelectorAll('.js-wx-value').forEach(el=>el.textContent=d.value);
      const copyBtn = document.querySelector('.copybtn.js-wx');
      if(copyBtn) copyBtn.textContent='复制';
      navigator.clipboard?.writeText(d.value).catch(()=>{});
      toast('微信号：'+d.value+'（已复制）');
    }catch(err){ toast(err.message); }
  });
});

// 失效反馈
$('invalidBtn')?.addEventListener('click', async function(){
  try{
    await api('/mark_invalid', {code:this.dataset.code});
    toast('已记录，谢谢反馈');
  }catch(err){ toast(err.message); }
});

// 举报弹层
$('reportBtn').addEventListener('click',()=>$('overlay').classList.add('show'));
$('mCancel').addEventListener('click',()=>$('overlay').classList.remove('show'));
$('overlay').addEventListener('click',e=>{if(e.target===$('overlay'))$('overlay').classList.remove('show');});
$('reasons').addEventListener('change',()=>{
  document.querySelectorAll('#reasons .reason').forEach(l=>l.classList.toggle('on',l.querySelector('input').checked));
});
$('mOk').addEventListener('click', async function(){
  const checked = document.querySelector('#reasons input:checked');
  try{
    const d = await api('/report', {code:this.dataset.code, reason:checked?checked.value:''});
    $('overlay').classList.remove('show');
    toast(d.msg || '已收到你的举报，谢谢');
  }catch(err){ toast(err.message); }
});
