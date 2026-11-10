(() => {
'use strict';
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function grade(n){if(n>=80)return['A','Awesome'];if(n>=70)return['B','Phenomenal'];if(n>=50)return['C','Promising'];if(n>=40)return['D','Impressive'];return['E','Poor'];}
async function loadResults(){
 const session=$('portalSession').value.trim(),term=$('portalTerm').value;
 if(!/^\d{4}\/\d{4}$/.test(session)){$('portalMessage').textContent='Enter a valid academic session.';return;}
 try{
  const response=await fetch('api/online-class/student-results.php?academic_session='+encodeURIComponent(session)+'&term='+encodeURIComponent(term),{credentials:'same-origin',cache:'no-store'});
  const d=await response.json();if(!response.ok||!d.success)throw new Error(d.message||'Unable to load results.');
  const s=d.student;$('portalStudentName').textContent=[s.first_name,s.middle_name,s.last_name].filter(Boolean).join(' ');
  $('portalStudentDetails').textContent=s.admission_number+' · '+s.level_name;
  $('portalMessage').textContent=d.released?'Your released results are shown below.':'Results for this term have not yet been released.';
  if(!d.released){$('portalReport').style.display='none';return;}
  const rows=d.results||[],complete=rows.filter(r=>[r.attendance_score,r.test_score,r.exam_score].every(v=>v!==null));
  const average=complete.length?complete.reduce((sum,r)=>sum+Number(r.total),0)/complete.length:0,g=grade(average);
  const dateKey=({'First Term':'first_term_resumption','Second Term':'second_term_resumption','Third Term':'third_term_resumption'})[term];
  $('portalReportTitle').textContent=term.toUpperCase()+' RESULT SHEET';
  $('portalReportDetails').innerHTML='<b>Academic Session:</b> '+esc(session)+' &nbsp; <b>Next Term Begins:</b> '+esc(s[dateKey]||'To be announced')+'<br><b>Student:</b> '+esc([s.first_name,s.middle_name,s.last_name].filter(Boolean).join(' '))+' &nbsp; <b>Admission No:</b> '+esc(s.admission_number)+' &nbsp; <b>Level:</b> '+esc(s.level_name)+'<br><b>Tutor:</b> '+esc(s.tutor_name||'—')+' &nbsp; <b>Portion of Memorisation:</b> '+esc(s.memorization_portion||'—');
  $('portalResultsRows').innerHTML=rows.map(r=>'<tr><td>'+esc(r.subject_name)+'</td><td>'+esc(r.attendance_score??'—')+'</td><td>'+esc(r.test_score??'—')+'</td><td>'+esc(r.exam_score??'—')+'</td><td><b>'+esc(r.total??'—')+'</b></td><td>'+esc(r.grade||'Pending')+'</td><td>'+esc(r.grade_remark||'')+'</td></tr>').join('')+'<tr><td colspan="4"><b>Overall Average</b></td><td><b>'+average.toFixed(1)+'%</b></td><td><b>'+g[0]+'</b></td><td><b>'+g[1]+'</b></td></tr>';
  const sig=d.signatures||{};
  const tutor=average>=80?'Mashallah! Your dedication and excellent effort are truly commendable. May Allah increase you in beneficial knowledge.':average>=70?'Well done. Your steady effort is showing beautiful progress. Keep revising and moving forward.':average>=50?'You are making promising progress. Continue with regular revision and confidence; every step matters.':average>=40?'You have made a beginning. With patient practice and consistent revision, you can make stronger progress.':'Please do not be discouraged. Learning is a journey; take it one step at a time and keep trying.';
  const director=average>=80?'Excellent achievement. May Allah preserve your knowledge and grant you continued success.':average>=70?'A very good effort. We encourage you to maintain this consistency and continue striving.':average>=50?'Your progress is encouraging. More regular revision will help you reach the next level.':average>=40?'We appreciate your effort. With support, patience and consistency, further improvement is within reach.':'Every learner deserves encouragement. Let us continue supporting your learning journey with patience and care.';
  const sigImage=(path,alt)=>path?'<img class="online-report-signature" src="./'+esc(path)+'" alt="'+alt+'">':'________________________';
  $('portalRemarks').innerHTML='<section><b>Tutor’s Remark</b><p>'+esc(tutor)+'</p><p>Signature: '+sigImage(sig.teacher,'Tutor signature')+'</p><p>Date: '+esc(sig.date||'—')+'</p></section><section><b>Coordinator’s / Director’s Remark</b><p>'+esc(director)+'</p><p>Signature: '+sigImage(sig.director,'Director signature')+'</p><p>Date: '+esc(sig.date||'—')+'</p></section>';
  $('portalReport').style.display='block';
 }catch(e){$('portalMessage').textContent=e.message;$('portalReport').style.display='none';}
}
$('portalLoad').addEventListener('click',loadResults);
$('portalPrint').addEventListener('click',()=>window.print());
$('portalLogout').addEventListener('click',async()=>{await fetch('api/online-class/logout.php',{method:'POST',credentials:'same-origin'});location.replace('online-class-login.html');});
loadResults();
})();