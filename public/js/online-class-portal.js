(() => {
'use strict';
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let report=null;
async function api(url,options={}){const r=await fetch(url,{credentials:'same-origin',cache:'no-store',...options,headers:{Accept:'application/json',...(options.body?{'Content-Type':'application/json'}:{}),...(options.headers||{})}});const d=await r.json().catch(()=>({}));if(!r.ok||!d.success)throw new Error(d.message||'Unable to complete request.');return d;}
function showMessage(text,error=false){$('portalMessage').textContent=text;$('portalMessage').style.color=error?'#a33d2c':'#285b3a';}
function grade(score){return score>=80?['A','Awesome']:score>=70?['B','Phenomenal']:score>=50?['C','Promising']:score>=40?['D','Impressive']:['E','Poor'];}
async function loadResults(){
 const session=$('portalSession').value.trim(),term=$('portalTerm').value;
 if(!/^\d{4}\/\d{4}$/.test(session)){showMessage('Enter a valid academic session.',true);return;}
 try{
  const d=await api('./api/online-class/student-results.php?academic_session='+encodeURIComponent(session)+'&term='+encodeURIComponent(term));report=d;
  if(!d.released){$('portalReport').style.display='none';showMessage('Your '+term+' results have not been released yet. Please check again later.');return;}
  const rows=d.results||[],complete=rows.filter(r=>r.total!==null),average=complete.length?complete.reduce((sum,r)=>sum+Number(r.total),0)/complete.length:0,g=grade(average);
  $('portalStudentName').textContent='Welcome, '+[d.student.first_name,d.student.middle_name,d.student.last_name].filter(Boolean).join(' ');
  $('portalStudentDetails').textContent='Admission No: '+d.student.admission_number+' · '+d.student.level_name;
  $('portalReportTitle').textContent=term.toUpperCase()+' RESULT SHEET · '+session;
  $('portalReportDetails').innerHTML='<b>Name:</b> '+esc([d.student.first_name,d.student.middle_name,d.student.last_name].filter(Boolean).join(' '))+' &nbsp; <b>Admission:</b> '+esc(d.student.admission_number)+' &nbsp; <b>Level:</b> '+esc(d.student.level_name)+' &nbsp; <b>Tutor:</b> '+esc(d.student.tutor_name||'—')+' &nbsp; <b>Next Term Begins:</b> '+esc(({ 'First Term':d.student.first_term_resumption,'Second Term':d.student.second_term_resumption,'Third Term':d.student.third_term_resumption})[term]||'To be announced')+'<br><b>Portion of Memorisation:</b> '+esc(d.student.memorization_portion||'—');
  $('portalResultsRows').innerHTML=rows.map(r=>'<tr><td>'+esc(r.subject_name)+'</td><td>'+esc(r.attendance_score??'—')+'</td><td>'+esc(r.test_score??'—')+'</td><td>'+esc(r.exam_score??'—')+'</td><td>'+esc(r.total??'—')+'</td><td>'+esc(r.grade??'Pending')+'</td><td>'+esc(r.grade_remark??'—')+'</td></tr>').join('')+'<tr><td colspan="4"><b>Overall Average</b></td><td><b>'+average.toFixed(1)+'%</b></td><td><b>'+g[0]+'</b></td><td><b>'+g[1]+'</b></td></tr>';
  const sig=d.signatures||{},sigImg=v=>v?'<img src="./'+esc(String(v).replace(/^\/+/,''))+'" alt="Signature" style="max-width:135px;max-height:38px;object-fit:contain">':'_____________________';
  const tutor=rows.map(r=>r.teacher_remark).find(Boolean)||'Your effort and progress are appreciated. Continue learning with confidence and regular revision.';
  const director=rows.map(r=>r.director_remark).find(Boolean)||'May Allah grant you beneficial knowledge and continued progress.';
  $('portalRemarks').innerHTML='<section><b>Tutor’s Remark</b><p>'+esc(tutor)+'</p><p>Signature: '+sigImg(sig.teacher)+'</p><p>Date: '+esc(sig.date||'')+'</p></section><section><b>Coordinator’s / Director’s Remark</b><p>'+esc(director)+'</p><p>Signature: '+sigImg(sig.director)+'</p><p>Date: '+esc(sig.date||'')+'</p></section>';
  $('portalReport').style.display='block';showMessage('Your released results are ready. You can print or save the report as PDF.');
 }catch(e){showMessage(e.message,true);if(e.message.toLowerCase().includes('sign in'))location.replace('online-class-login.html');}
}
$('portalLoad').addEventListener('click',loadResults);
$('portalPrint').addEventListener('click',()=>{if(report?.released)window.print();});
$('portalLogout').addEventListener('click',async()=>{try{await api('./api/online-class/logout.php',{method:'POST',body:'{}'});}catch(_){}location.replace('online-class-login.html');});
loadResults();
})();