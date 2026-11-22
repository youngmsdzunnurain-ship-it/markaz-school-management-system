(() => {
"use strict";
const $ = (id) => document.getElementById(id);
const dateInput = $("teacherAttendanceDate");
const rowsHost = $("teacherAttendanceRows");
const message = $("teacherAttendanceMessage");
const saveButton = $("saveTeacherAttendance");
let staff = [];
let busy = false;
const today = () => { const d=new Date(); d.setMinutes(d.getMinutes()-d.getTimezoneOffset()); return d.toISOString().slice(0,10); };
const esc = value => String(value ?? "").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[c]));
const show = (text,error=false) => { message.textContent=text; message.classList.toggle("error",error); };
async function request(url,options={}) {
 const response=await fetch(url,{credentials:"same-origin",cache:"no-store",...options,headers:{Accept:"application/json",...(options.headers||{})}});
 const data=await response.json().catch(()=>({success:false,message:"The server returned an invalid response."}));
 if(response.status===401){location.replace("./login.html");throw new Error("Please sign in again.");}
 if(!response.ok||!data.success)throw new Error(data.message||"Teacher attendance request failed.");
 return data;
}
function render() {
 if(!staff.length){rowsHost.innerHTML='<tr><td colspan="7" class="register-empty">No active Finance staff have a salary and arrival-time profile for this date. Configure staff and salary details in Finance first.</td></tr>';saveButton.disabled=true;return;}
 rowsHost.innerHTML=staff.map((s,i)=>{
 const status=s.status||"";
 const options=[["","Choose status"],["present","Present"],["absent","Absent"],["approved_leave","Approved leave"],["excused","Excused"]];
 const selected=options.map(([v,l])=>'<option value="'+v+'" '+(v===status?'selected':'')+'>'+l+'</option>').join("");
 const late=Number(s.late_minutes||0);
 return '<tr data-staff-row="'+esc(s.finance_staff_id)+'"><td>'+(i+1)+'</td><td><span class="staff-name">'+esc(s.full_name)+'</span><span class="staff-meta">'+esc(s.staff_number)+' · '+esc(s.job_title)+'</span></td><td>'+esc(String(s.expected_arrival||"").slice(0,5))+'</td><td><input type="time" data-arrival value="'+esc(String(s.actual_arrival||"").slice(0,5))+'" aria-label="Actual arrival for '+esc(s.full_name)+'"></td><td><select data-status aria-label="Attendance status for '+esc(s.full_name)+'">'+selected+'</select></td><td><input type="text" maxlength="500" data-notes value="'+esc(s.notes||"")+'" aria-label="Notes for '+esc(s.full_name)+'"></td><td data-late>'+late+' min</td></tr>';
 }).join("");
 saveButton.disabled=false;
}
async function load() {
 if(busy)return;busy=true;saveButton.disabled=true;show("Loading teacher attendance…");
 try {
  const data=await request("./api/attendance/teachers.php?date="+encodeURIComponent(dateInput.value));
  staff=Array.isArray(data.staff)?data.staff:[];
  $("teacherAttendanceSubtitle").textContent=new Intl.DateTimeFormat(undefined,{weekday:"long",day:"numeric",month:"long",year:"numeric"}).format(new Date(dateInput.value+"T12:00:00"))+" · "+staff.length+" Finance staff with active salary profiles";
  render();show(staff.length?"Mark each staff member's attendance and save the register.":"No eligible staff found for this date.");
 } catch(error){staff=[];render();show(error.message,true);}
 finally{busy=false;}
}
async function save() {
 if(busy||!staff.length)return;
 const records=[...rowsHost.querySelectorAll("[data-staff-row]")].map(row=>({
  finance_staff_id:Number(row.dataset.staffRow),
  actual_arrival:row.querySelector("[data-arrival]").value,
  status:row.querySelector("[data-status]").value,
  notes:row.querySelector("[data-notes]").value
 }));
 if(records.some(r=>!r.status)){show("Choose an attendance status for every staff member before saving.",true);return;}
 if(records.some(r=>r.status==="present"&&!r.actual_arrival)){show("Enter the actual arrival time for every staff member marked Present.",true);return;}
 if(!confirm("Save and approve this teacher attendance register for payroll?"))return;
 busy=true;saveButton.disabled=true;show("Saving attendance register…");
 try {
  const result=await request("./api/attendance/teachers.php",{method:"POST",headers:{"Content-Type":"application/json","X-MARKAZ-Request":"attendance"},body:JSON.stringify({action:"save_register",attendance_date:dateInput.value,records})});
  show(result.message+" ("+result.saved+" records).");await load();
 } catch(error){show(error.message,true);}
 finally{busy=false;saveButton.disabled=!staff.length;}
}
dateInput.max=today();dateInput.value=today();
$("teacherTodayLabel").textContent=new Intl.DateTimeFormat(undefined,{weekday:"short",day:"numeric",month:"short",year:"numeric"}).format(new Date(today()+"T12:00:00"));
$("loadTeacherAttendance").addEventListener("click",load);
saveButton.addEventListener("click",save);
dateInput.addEventListener("change",load);
request("./api/auth/me.php").then(data=>{
 if(data.admin?.can_access_teacher_attendance!==true){show("This page is available only to Admin and Management accounts with full Attendance access.",true);rowsHost.innerHTML='<tr><td colspan="7" class="register-empty">Access denied.</td></tr>';return;}
 load();
}).catch(error=>show(error.message,true));
})();