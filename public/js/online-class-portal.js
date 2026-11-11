(() => {
'use strict';
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function formatOnlineReportDate(value){const match=String(value||'').match(/^(\d{4})-(\d{2})-(\d{2})$/);return match?match[3]+'/'+match[2]+'/'+match[1]:(value||'To be announced');}
let report=null;
async function api(url,options={}){const r=await fetch(url,{credentials:'same-origin',cache:'no-store',...options,headers:{Accept:'application/json',...(options.body?{'Content-Type':'application/json'}:{}),...(options.headers||{})}});const d=await r.json().catch(()=>({}));if(!r.ok||!d.success)throw new Error(d.message||'Unable to complete request.');return d;}
function showMessage(text,error=false){$('portalMessage').textContent=text;$('portalMessage').style.color=error?'#a33d2c':'#285b3a';}
function configureSupportLink(){const link=$('onlineSupportLink');if(!link)return;const message='Hello MARKAZ Support, I am having trouble accessing my Online Class results. Please help me.';const setNumber=value=>{let digits=String(value||'').replace(/\D/g,'');if(digits.startsWith('0'))digits='234'+digits.slice(1);else if(digits.startsWith('2340'))digits='234'+digits.slice(4);if(digits.length<10||digits.length>15)digits='2347048657955';link.href='https://wa.me/'+digits+'?text='+encodeURIComponent(message);};setNumber('2347048657955');fetch('./api/settings/support-contact.php',{credentials:'same-origin',cache:'no-store'}).then(response=>response.ok?response.json():null).then(data=>{if(data?.success&&data?.whatsapp_number)setNumber(data.whatsapp_number);}).catch(()=>{});}
function grade(score){if(score>=80)return ['A','Awesome'];if(score>=70)return ['B','Phenomenal'];if(score>=50)return ['C','Impressive'];if(score>=30)return ['D','Promising'];return ['E','Poor'];}
function sigMarkup(image,text,alt){const value=image||text||'';if(!value)return '_____________________';if(image||/^data:image\//i.test(value)||/\.(png|jpe?g|webp|svg)(\?.*)?$/i.test(value)){const raw=String(value).trim(),src=/^(?:data:image\/|https?:\/\/|\/)/i.test(raw)?raw:'./'+raw.replace(/^\.\//,'');return '<img class="online-report-signature" src="'+esc(src)+'" alt="'+esc(alt)+'">';}return esc(value);}
async function waitForReportImages(root){const images=Array.from(root.querySelectorAll('img'));await Promise.all(images.map(img=>{if(typeof img.decode==='function')return img.decode().catch(()=>{});if(img.complete)return Promise.resolve();return new Promise(resolve=>{img.addEventListener('load',resolve,{once:true});img.addEventListener('error',resolve,{once:true});});}));}
async function loadResults(){
 try{
  const d=await api('./api/online-class/student-results.php');report=d;
  const session=d.academic_session,term=d.term;
  $('portalSelectedPeriod').textContent='Selected: '+term+' · '+session;
  if(!d.released){$('portalReport').style.display='none';showMessage('Results have not been released for '+d.student.level_name+' · '+term+' · '+session+'. Student login is enabled, but the administrator must separately release results for this exact level, session and term.');return;}
  const rows=d.results||[],complete=rows.filter(r=>r.total!==null),average=complete.length?complete.reduce((sum,r)=>sum+Number(r.total),0)/complete.length:0,g=grade(average);
  $('portalStudentName').textContent='Welcome, '+[d.student.first_name,d.student.middle_name,d.student.last_name].filter(Boolean).join(' ');
  $('portalStudentDetails').textContent='Admission No: '+d.student.admission_number+' · '+d.student.level_name+' · Section '+d.student.section;
  $('portalReportTitle').textContent=term.toUpperCase()+' RESULT SHEET · '+session;
  $('portalReportDetails').innerHTML='<b>Name:</b> '+esc([d.student.first_name,d.student.middle_name,d.student.last_name].filter(Boolean).join(' '))+' &nbsp; <b>Admission:</b> '+esc(d.student.admission_number)+' &nbsp; <b>Level:</b> '+esc(d.student.level_name)+' &nbsp; <b>Section:</b> '+esc(d.student.section)+' &nbsp; <b>Tutor:</b> '+esc(d.student.tutor_name||'—')+' &nbsp; <b>Next Term Begins:</b> '+esc(formatOnlineReportDate(({ 'First Term':d.student.first_term_resumption,'Second Term':d.student.second_term_resumption,'Third Term':d.student.third_term_resumption})[term]))+'<br><b>Portion of Memorisation:</b> '+esc(d.student.memorization_portion||'—')+' &nbsp; <b>Task 1:</b> '+esc(d.student.task_one_name||'—')+' &nbsp; <b>Task 2:</b> '+esc(d.student.task_two_name||'—');
  $('portalResultsRows').innerHTML=rows.map(r=>'<tr><td>'+esc(r.subject_name)+'</td><td>'+esc(r.attendance_score??'—')+'</td><td>'+esc(r.test_score??'—')+'</td><td>'+esc(r.exam_score??'—')+'</td><td>'+esc(r.total??'—')+'</td><td>'+esc(r.grade??'Pending')+'</td><td>'+esc(r.grade_remark??'—')+'</td></tr>').join('')+'<tr><td colspan="4"><b>Overall Average</b></td><td><b>'+average.toFixed(1)+'%</b></td><td><b>'+g[0]+'</b></td><td><b>'+g[1]+'</b></td></tr>';
  const sig=d.signatures||{},sigImg=v=>v?'<img src="'+esc(String(v))+'" alt="Signature" style="max-width:135px;max-height:38px;object-fit:contain">':'_____________________';
  const tutor=rows.map(r=>r.teacher_remark).find(Boolean)||'Your effort and progress are appreciated. Continue learning with confidence and regular revision.';
  const director='Mashallah, Barakallahu fi';
  $('portalRemarks').innerHTML='<section><b>Tutor’s Remark</b><p>'+esc(tutor)+'</p><p>Signature: '+sigMarkup(sig.teacher_image,sig.teacher_text,'Tutor signature')+'</p><p>Date: '+esc(sig.date||'')+'</p></section><section><b>Coordinator’s / Director’s Remark</b><p>'+esc(director)+'</p><p>Signature: '+sigMarkup(sig.director_image,sig.director_text,'Director signature')+'</p><p>Date: '+esc(sig.date||'')+'</p></section>';
  $('portalReport').style.display='block';showMessage('Your released results are ready. You can print or save the report as PDF.');
 }catch(e){showMessage(e.message,true);if(e.message.toLowerCase().includes('sign in'))location.replace('online-class-login.html');}
}

configureSupportLink();
$('portalLoad').addEventListener('click',loadResults);
$('portalPrint').addEventListener('click',async()=>{if(report?.released){await waitForReportImages($('portalReport'));window.print();}});
$('portalLogout').addEventListener('click',async()=>{try{await api('./api/online-class/logout.php',{method:'POST',body:'{}'});}catch(_){}location.replace('online-class-login.html');});
loadResults();
})();