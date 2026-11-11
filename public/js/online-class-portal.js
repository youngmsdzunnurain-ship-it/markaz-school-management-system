(() => {
'use strict';
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function formatOnlineReportDate(value){const match=String(value||'').match(/^(\d{4})-(\d{2})-(\d{2})$/);return match?match[3]+'/'+match[2]+'/'+match[1]:(value||'To be announced');}
let report=null;
async function api(url,options={}){const controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),20000);try{const r=await fetch(url,{credentials:'same-origin',cache:'no-store',...options,signal:options.signal||controller.signal,headers:{Accept:'application/json',...(options.body?{'Content-Type':'application/json'}:{}),...(options.headers||{})}});const d=await r.json().catch(()=>({}));if(!r.ok||!d.success)throw new Error(d.message||'Unable to complete request.');return d;}catch(error){if(error.name==='AbortError')throw new Error('The results request timed out. Please check your connection and tap Refresh results.');throw error;}finally{clearTimeout(timeout);}}
function showMessage(text,error=false){$('portalMessage').textContent=text;$('portalMessage').style.color=error?'#a33d2c':'#285b3a';}
function configureSupportLink(){const link=$('onlineSupportLink');if(!link)return;const message='Assalamualaikum. I am a student using the MARKAZ Online Class portal, and I need help with my results. Please assist me. Jazakumullahu khairan.';link.removeAttribute('href');fetch('./api/settings/support-contact.php',{credentials:'same-origin',cache:'no-store'}).then(response=>response.ok?response.json():null).then(data=>{if(!data?.success||!data?.whatsapp_number)return;let digits=String(data.whatsapp_number).replace(/\D/g,'');if(digits.startsWith('0'))digits='234'+digits.slice(1);else if(digits.startsWith('2340'))digits='234'+digits.slice(4);if(digits.length<10||digits.length>15)return;link.href='https://wa.me/'+digits+'?text='+encodeURIComponent(message);}).catch(()=>{});}
function grade(score){if(score>=80)return ['A','Awesome'];if(score>=70)return ['B','Phenomenal'];if(score>=50)return ['C','Impressive'];if(score>=30)return ['D','Promising'];return ['E','Poor'];}
function signatureImageSource(value){const raw=String(value||'').trim();if(!raw)return '';if(/^data:image\//i.test(raw)||/^https?:\/\//i.test(raw))return raw;const path=raw.replace(/^\.\//,'').replace(/^\/+/, '');return './'+path+(path.includes('?')?'&':'?')+'v='+Date.now();}
function renderSavedSignature(targetId,imagePath,legacyText,alt){const target=$(targetId);if(!target)return;target.replaceChildren();if(imagePath){const image=document.createElement('img');image.className='result-signature-image';image.alt=alt;image.src=signatureImageSource(imagePath);image.addEventListener('error',()=>{target.textContent=legacyText||'—';},{once:true});target.appendChild(image);}else target.textContent=legacyText||'—';}
async function waitForReportImages(root){const images=Array.from(root.querySelectorAll('img'));await Promise.all(images.map(img=>{if(typeof img.decode==='function')return img.decode().catch(()=>{});if(img.complete)return Promise.resolve();return new Promise(resolve=>{img.addEventListener('load',resolve,{once:true});img.addEventListener('error',resolve,{once:true});});}));}
async function loadResults(){
 $('portalSelectedPeriod').textContent='Checking your selected academic session and term…';
 showMessage('Loading your released results…');
 $('portalReport').style.display='none';
 try{
  const d=await api('./api/online-class/student-results.php');
  if(!d.student||!d.academic_session||!d.term||!Array.isArray(d.results))throw new Error('The results service returned an incomplete response. Please refresh or contact MARKAZ Support.');
  report=d;
  const session=d.academic_session,term=d.term;
  $('portalSelectedPeriod').textContent='Selected: '+term+' · '+session;
  if(!d.released){$('portalReport').style.display='none';showMessage('Results have not been released for '+d.student.level_name+' · '+term+' · '+session+'. Student login is enabled, but the administrator must separately release results for this exact level, session and term.');return;}
  const rows=d.results||[],complete=rows.filter(r=>r.total!==null),average=complete.length?complete.reduce((sum,r)=>sum+Number(r.total),0)/complete.length:0,g=grade(average);
  $('portalStudentName').textContent='Welcome, '+[d.student.first_name,d.student.middle_name,d.student.last_name].filter(Boolean).join(' ');
  $('portalStudentDetails').textContent='Admission No: '+d.student.admission_number+' · '+d.student.level_name+' · Section '+d.student.section;
  $('portalReportTitle').textContent=term.toUpperCase()+' ONLINE CLASS RESULT SHEET · '+session;
  $('portalReportDetails').innerHTML='<div><b>Student Name:</b> '+esc([d.student.first_name,d.student.middle_name,d.student.last_name].filter(Boolean).join(' '))+'</div><div><b>Admission Number:</b> '+esc(d.student.admission_number)+'</div><div><b>Class / Level:</b> '+esc(d.student.level_name)+'</div><div><b>Section:</b> '+esc(d.student.section)+'</div><div><b>Academic Session:</b> '+esc(session)+'</div><div><b>Term:</b> '+esc(term)+'</div><div><b>Class Teacher:</b> '+esc(d.student.tutor_name||'—')+'</div><div><b>Next Term Begins:</b> '+esc(formatOnlineReportDate(({ 'First Term':d.student.first_term_resumption,'Second Term':d.student.second_term_resumption,'Third Term':d.student.third_term_resumption})[term]))+'</div><div><b>Portion of Memorisation:</b> '+esc(d.student.memorization_portion||'—')+'</div><div><b>Task 1:</b> '+esc(d.student.task_one_name||'—')+'</div><div><b>Task 2:</b> '+esc(d.student.task_two_name||'—')+'</div>';
  $('portalResultsRows').innerHTML=rows.map((r,i)=>'<tr><td>'+String(i+1)+'</td><td>'+esc(r.subject_name)+'</td><td>'+esc(r.attendance_score??'—')+'</td><td>'+esc(r.test_score??'—')+'</td><td>'+esc(r.exam_score??'—')+'</td><td class="result-total">'+esc(r.total??'—')+'</td><td>'+esc(r.grade??'Pending')+'</td><td class="result-remark">'+esc(r.grade_remark??'—')+'</td></tr>').join('')+'<tr><td colspan="5"><b>Overall Average</b></td><td class="result-total"><b>'+average.toFixed(1)+'%</b></td><td><b>'+g[0]+'</b></td><td><b>'+g[1]+'</b></td></tr>';
  const sig=d.signatures||{};
  const tutor=rows.map(r=>r.teacher_remark).find(Boolean)||'Your effort and progress are appreciated. Continue learning with confidence and regular revision.';
  const director=rows.map(r=>r.director_remark).find(Boolean)||'Mashallah, Barakallahu fi';
  $('portalRemarks').innerHTML='<section class="remark-card"><span class="section-eyebrow">CLASS TEACHER’S REMARK</span><p>'+esc(tutor)+'</p></section><section class="remark-card director-remark"><span class="section-eyebrow">DIRECTOR’S REMARK</span><p>'+esc(director)+'</p></section>';
  renderSavedSignature('classTeacherSignatureDisplay',sig.teacher_image,sig.teacher_text,'Class Teacher signature');
  renderSavedSignature('directorSignatureDisplay',sig.director_image,sig.director_text,'Director signature');
  $('resultSignatureDateDisplay').textContent=sig.date||'—';
  $('portalReport').style.display='block';showMessage('Your released results are ready. You can print or save the report as PDF.');
 }catch(e){$('portalSelectedPeriod').textContent='Unable to load your selected academic session and term.';showMessage(e.message||'Unable to load your results. Please try again.',true);if(e.message.toLowerCase().includes('sign in'))location.replace('online-class-login.html');}
}

configureSupportLink();
$('portalLoad').addEventListener('click',loadResults);
$('portalPrint').addEventListener('click',async()=>{if(!report?.released)return;try{const student=report.student||{};$('portalPrintReport').innerHTML=window.MarkazOnlineClassReport.render({student,level:{name:student.level_name,tutor_name:student.tutor_name,memorization_portion:student.memorization_portion,first_term_resumption:student.first_term_resumption,second_term_resumption:student.second_term_resumption,third_term_resumption:student.third_term_resumption},rows:report.results||[],term:report.term,session:report.academic_session,signatures:{class_teacher_signature_image:report.signatures?.teacher_image||'',class_teacher_signature:report.signatures?.teacher_text||'',director_signature_image:report.signatures?.director_image||'',director_signature:report.signatures?.director_text||'',result_signature_date:report.signatures?.date||''}});$('portalPrintReport').classList.remove('online-hidden');await waitForReportImages($('portalPrintReport'));window.print();}catch(error){showMessage(error.message||'Unable to prepare your report for printing.',true);}});\nwindow.addEventListener('afterprint',()=>$('portalPrintReport').classList.add('online-hidden'));
$('portalLogout').addEventListener('click',async()=>{try{await api('./api/online-class/logout.php',{method:'POST',body:'{}'});}catch(_){}location.replace('online-class-login.html');});
loadResults();
})();