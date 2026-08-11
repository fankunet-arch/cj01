/* 用户后台：编辑页的大区→城市联动（回显当前值） */
const topSel = document.getElementById('fRegionTop');
const citySel = document.getElementById('fRegion');

function fillCities(){
  if(!topSel || !citySel) return;
  const topId = topSel.value;
  const cities = (window.ZP_CITIES || {})[topId] || [];
  citySel.innerHTML = '';
  if(cities.length === 0){
    citySel.innerHTML = `<option value="${topId}">${topSel.selectedOptions[0].textContent}</option>`;
  }else{
    cities.forEach(c=>{ citySel.innerHTML += `<option value="${c.id}">${c.name}</option>`; });
  }
  const cur = citySel.dataset.current;
  if(cur && citySel.querySelector(`option[value="${cur}"]`)) citySel.value = cur;
}
topSel?.addEventListener('change', ()=>{ citySel.dataset.current=''; fillCities(); });
fillCities();
