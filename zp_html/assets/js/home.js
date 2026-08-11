/* 首页（信息墙）：卡片跳转 + 点击取号（get_contact.php）+ 微信复制 */
function toast(msg){
  const t=document.getElementById('toast');
  document.getElementById('toastMsg').textContent=msg;
  t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'),2400);
}

async function getContact(code, ctype){
  const r = await fetch('/get_contact', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({code, ctype})
  });
  const d = await r.json().catch(()=>({}));
  if(!r.ok || !d.ok) throw new Error(d.msg || '获取失败，请稍后再试');
  return d.value;
}

document.addEventListener('click', async e=>{
  // 点击取号：第一次点显示完整号码，再点直接拨打
  const call = e.target.closest('.call');
  if(call){
    e.stopPropagation();
    if(call.dataset.tel){ location.href='tel:'+call.dataset.tel.replace(/\s/g,''); return; }
    try{
      const tel = await getContact(call.dataset.code, 'phone');
      call.dataset.tel = tel;
      call.querySelector('.num').textContent = tel;
      toast('再点一次直接拨打');
    }catch(err){ toast(err.message); }
    return;
  }
  // 微信：取号并复制
  const wx = e.target.closest('.wx');
  if(wx){
    e.stopPropagation();
    try{
      const id = await getContact(wx.dataset.code, 'wechat');
      navigator.clipboard?.writeText(id).catch(()=>{});
      toast('微信号：'+id+'（已复制）');
    }catch(err){ toast(err.message); }
    return;
  }
  // 卡片/按钮跳转
  const nav = e.target.closest('[data-href]');
  if(nav) location.href = nav.dataset.href;
});
