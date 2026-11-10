(() => {
'use strict';
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const grade=n=>n>=80?['A','Awesome']:n>=70?['B','Phenomenal']:n>=50?['C','Promising']:n>=40?['D','Impressive']:['E','Poor'];
async function request(url,options={}){const r=await fetch(url,{credentials:'same-origin',cache:'no-store',...options,headers:{Accept:'application/json',...(options.body?{'Content-Type':'application/json'}:{}),...(options.headers||{})}});const d=await r.json().catch(()=>({}));if(!r.ok||!d.success)throw new Error(d.message||'Unable to complete request.');return d;}
function signature(settings,imageKey,textKey,alt){const path=settings[imageKey];return path?'<img src="./'+esc(String(path).replace(/^\\/+/,''))+'" alt="'+esc(alt)+'" style="max-width:150px;max-height:48px;object-fit:contain">':esc(settings[textKey]||'—');}
async function load(){
 const session=$('portalSession').value.trim(),term=$('portalTerm').value;
 if(!/^\d{4}\/\d{4}$/.test(session)){$('portalMessage').textContent='Enter a valid academic session, e.g. 2026/2027.';return;}
 try{
  const [d,footer]=await Promise.all([request('api/online-class/student-results.php?academic_session='+encodeURIComponent(session)+'&term='+encodeURIComponent(term)),request('api/online-class/student-footer.php')]);
  $('portalStudentName').textContent=[d.student.first_name,d.student.middle_name,d.student.last_name].filter(Boolean).join(' ');
  $('portalStudentDetails').textContent=d.student.admission_number+' · '+d.student.level_name;
  $('portalMessage').textContent=d.released?'Your released results are shown below.':'Results for this term have not yet been released.';
  $('portalReport').style.display=d.released?'block':'none';
  if(!d.released)return;
  $('portalReportTitle').textContent=term.toUpperCase()+' RESULT SHEET · SESSION '+session;
  $('portalReportDetails').innerHTML='<b>Student:</b> '+esc([d.student.first_name,d.student.middle_name,d.student.last_name].filter(Boolean).join(' '))+' &nbsp; <b>Admission No:</b> '+esc(d.student.admission_number)+' &nbsp; <b>Level:</b> '+esc(d.student.level_name)+' &nbsp; <b>Tutor:</b> '+esc(d.student.tutor_name||'—')+' &nbsp; <b>Portion of Memorisation:</b> '+esc(d.student.memorization_portion||'—')+' &nbsp; <b>Next Term Begins:</b> '+esc(d.student[({'First Term':'first_term_resumption','Second Term':'second_term_resumption','Third Term':'third_term_resumption'})[term]]||'To be announced');
  const rows=d.results||[];
  $('portalResultsRows').innerHTML=rows.map(r=>'<tr><td>'+esc(r.subject_name)+'</td><td>'+esc(r.attendance_score??'—')+'</td><td>'+esc(r.test_score??'—')+'</td><td>'+esc(r.exam_score??'—')+'</td><td><b>'+esc(r.total??'—')+'</b></td><td>'+esc(r.grade||'Pending')+'</td><td>'+esc(r.grade_remark||'')+'</td></tr>' ).join('');
  const complete=rows.filter(r=>r.total!==null&&r.total!==undefined);
  const average=complete.length?complete.reduce((sum,r)=>sum+Number(r.total),0)/complete.length:0;
  const overall=grade(average);
  const tutor=rows.find(r=>r.teacher_remark)?.teacher_remark||'Your effort and progress are appreciated. Keep learning steadily.';
  const director=rows.find(r=>r.director_remark)?.director_remark||'May Allah grant you beneficial knowledge and continued progress.';
  $('portalRemarks').innerHTML='<section><b>Tutor’s Remark</b><p>'+esc(tutor)+'</p><p>Signature: '+signature(footer.settings,'class_teacher_signature_image','class_teacher_signature','Tutor signature')+'</p><p>Date: '+esc(footer.settings.result_signature_date||'—')+'</p></section><section><b>Coordinator’s / Director’s Remark</b><p>'+esc(director)+'</p><p>Signature: '+signature(footer.settings,'director_signature_image','director_signature','Director signature')+'</p><p>Date: '+esc(footer.settings.result_signature_date||'—')+'</p></section>';
  $('portalReport').style.display='block';
 }catch(e){$('portalMessage').textContent=e.message;}
}
$('portalLoad').addEventListener('click',load);
$('portalPrint').addEventListener('click',()=>window.print());
$('portalLogout').addEventListener('click',async()=>{try{await request('api/online-class/logout.php',{method:'POST',body:'{}'});}catch(_){}window.location.replace('online-class-login.html');});
load();
})();