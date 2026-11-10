(() => {
'use strict';
const $=id=>document.getElementById(id);
let portalOptions={levels:[],sections:[]};
async function loadOptions(){
 const response=await fetch('api/online-class/portal-options.php',{credentials:'same-origin',cache:'no-store'});
 const data=await response.json().catch(()=>({}));
 if(!response.ok||!data.success)throw new Error(data.message||'Unable to load Online Class levels.');
 portalOptions=data;
 $('onlineLevel').innerHTML='<option value="">Choose your level</option>'+data.levels.map(level=>'<option value="'+Number(level.id)+'">'+escapeHtml(level.name)+'</option>').join('');
 updateSections();
}
function escapeHtml(value){return String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));}
function updateSections(){
 const level=Number($('onlineLevel').value);
 const sections=[...new Set(portalOptions.sections.filter(item=>Number(item.level_id)===level).map(item=>String(item.section)))];
 $('onlineSection').innerHTML='<option value="">Choose your section</option>'+sections.map(section=>'<option value="'+escapeHtml(section)+'">'+escapeHtml(section)+'</option>').join('');
 $('onlineSection').disabled=!level||sections.length===0;
 if(level&&sections.length===0){$('onlineSection').disabled=false;$('onlineSection').innerHTML='<option value="">No sections registered yet</option>';}
}
$('onlineLevel').addEventListener('change',updateSections);
loadOptions().catch(error=>{$('onlineLoginMessage').textContent=error.message;});
$('onlineLoginForm').addEventListener('submit',async event=>{
 event.preventDefault();
 const button=$('onlineLoginButton'),message=$('onlineLoginMessage');
 button.disabled=true;message.textContent='Checking your details…';
 try{
  const response=await fetch('api/online-class/student-login.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({admission_number:$('onlineAdmission').value.trim(),level_id:$('onlineLevel').value,section:$('onlineSection').value,academic_session:$('onlineSession').value.trim(),term:$('onlineTerm').value})});
  const data=await response.json().catch(()=>({}));
  if(!response.ok||!data.success)throw new Error(data.message||'Unable to sign in.');
  window.location.replace('online-class-portal.html');
 }catch(error){message.textContent=error.message;message.dataset.kind='error';button.disabled=false;}
});
})();