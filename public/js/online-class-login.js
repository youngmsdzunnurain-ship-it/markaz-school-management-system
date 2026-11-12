(() => {
'use strict';
const $ = id => document.getElementById(id);
function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
}
function populateAcademicSessions() {
  const select = $('onlineSession');
  const sessions = Array.from({ length: 10 }, (_, index) => { const year = 2026 + index; return year + '/' + (year + 1); });
  select.innerHTML = '<option value="">Select academic session</option>' + sessions.map(session => '<option value="' + session + '">' + session + '</option>').join('');
  select.value = sessions[0];
}
async function loadOptions() {
  const response = await fetch('api/online-class/portal-options.php',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
  const data = await response.json().catch(()=>({}));
  if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load Online Class levels.');
  if (!Array.isArray(data.levels)) throw new Error('Online Class setup is incomplete. Please contact the school administrator.');
  const select = $('onlineLevel');
  select.innerHTML = '<option value="">Choose your level</option>' + data.levels.map(level=>'<option value="'+Number(level.id)+'">'+escapeHtml(level.name)+'</option>').join('');
  select.disabled = data.levels.length === 0;
  if (!data.levels.length) select.innerHTML = '<option value="">No active levels available</option>';

}
function showMessage(message,kind){const node=$('onlineLoginMessage');node.textContent=message;node.dataset.kind=kind||'error';node.classList.add('visible');}
let lockoutTimer = null;
function showLockoutCountdown(lockout, button) {
 if (lockoutTimer) clearInterval(lockoutTimer);
 let remaining = Math.max(0, Math.floor(Number(lockout.remaining_seconds) || 0)); button.disabled = true;
 const render = () => {
  const days = Math.floor(remaining / 86400), hours = Math.floor((remaining % 86400) / 3600);
  const minutes = Math.floor((remaining % 3600) / 60), seconds = remaining % 60;
  showMessage('Online Class access is locked. Time remaining: ' + days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's.', 'error');
  if (remaining <= 0) { clearInterval(lockoutTimer); lockoutTimer = null; button.disabled = false; button.removeAttribute('aria-busy'); showMessage('The lockout period has ended. The server will verify your next sign-in.', 'success'); return; }
  remaining -= 1;
 };
 render(); if (remaining > 0) lockoutTimer = setInterval(render, 1000);
}
populateAcademicSessions();
loadOptions().catch(error=>showMessage(error.message,'error'));
$('onlineLoginForm').addEventListener('submit',async event=>{
 event.preventDefault();const button=$('onlineLoginButton');button.disabled=true;button.setAttribute('aria-busy','true');$('onlineLoginMessage').classList.remove('visible');
 try{
  const response=await fetch('api/online-class/student-login.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({admission_number:$('onlineAdmission').value.trim(),level_id:$('onlineLevel').value,academic_session:$('onlineSession').value,term:$('onlineTerm').value})});
  const data=await response.json().catch(()=>({}));if(!response.ok||!data.success){const error=new Error(data.message||'Unable to check your results. Confirm your details and try again.');error.loginData=data;throw error;}
  window.location.replace('online-class-portal.html');
 }catch(error){if(error.loginData?.locked&&Number(error.loginData.remaining_seconds)>0){showLockoutCountdown(error.loginData,button);}else{showMessage(error.message,'error');button.disabled=false;button.removeAttribute('aria-busy');}}
});
})();