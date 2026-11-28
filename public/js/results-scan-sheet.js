(() => {
"use strict";
const params=new URLSearchParams(location.search);
const classId=Number(params.get("class_id")||0);
const classType=params.get("class_type")==="arabic"?"arabic":"conventional";
const status=document.getElementById("sheetStatus");
const options=document.getElementById("subjectOptions");
const printButton=document.getElementById("printSheets");
const pages=document.getElementById("scanPages");
let data=null;
const esc=value=>String(value??"").replace(/[&<>"']/g,char=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[char]));
const fullName=s=>[s.first_name,s.middle_name,s.last_name].filter(Boolean).join(" ");
async function init(){
 if(!classId){status.textContent="A class ID is required.";return;}
 try{
  const response=await fetch(`./api/results/class.php?class_id=${encodeURIComponent(classId)}&class_type=${encodeURIComponent(classType)}`,{credentials:"same-origin",cache:"no-store"});
  data=await response.json();
  if(!response.ok||!data.success)throw new Error(data.message||"Could not load this class.");
  if(!data.students?.length||!data.subjects?.length)throw new Error("This class needs active students and assigned subjects before a scan sheet can be printed.");
  status.textContent=`${data.class.name} · ${data.students.length} active students · ${data.subjects.length} assigned subjects. Choose up to 12 subjects.`;
  options.innerHTML=data.subjects.map(subject=>`<label><input type="checkbox" value="${subject.id}" checked> <span>${esc(subject.name)}</span></label>`).join("");
  printButton.disabled=false;
 }catch(error){status.textContent=error.message;}
}
function makePage(studentPage,subjects,pageNumber,totalPages){
 const page=document.createElement("section");page.className="scan-page";
 const qrPayload=["MZSCAN1",classId,classType,subjects.map(s=>s.id).join(","),studentPage.map(s=>s.id).join(",")].join("|");
 const head=document.createElement("header");head.className="scan-page-head";
 head.innerHTML=`<div><h1>MARKAZ · ${esc(data.class.name)} · RESULT ENTRY</h1><p>${esc(data.academic_period.session_name)} · ${esc(data.academic_period.term_name)} · Page ${pageNumber}/${totalPages}</p></div><div style="text-align:right;font-size:7pt"><b>Write clearly inside each box</b><br>CA1 · CA2 · EXAM</div>`;
 const qr=document.createElement("div");qr.className="scan-qr";page.append(qr,head);
 ["tl","tr","bl","br"].forEach(corner=>{const marker=document.createElement("span");marker.className="scan-fiducial "+corner;marker.setAttribute("aria-hidden","true");page.append(marker);});
 const table=document.createElement("table");table.className="scan-grid";
 const scoreCols=subjects.length*3;const scoreWidth=(289-56)/scoreCols;
 let colgroup="<colgroup><col style='width:18mm'><col style='width:38mm'>";
 for(let i=0;i<scoreCols;i++)colgroup+=`<col style="width:${scoreWidth.toFixed(4)}mm">`;
 colgroup+="</colgroup>";
 const top=`<tr><th rowspan="2">ADM. NO.</th><th rowspan="2">STUDENT NAME</th>${subjects.map(s=>`<th colspan="3">${esc(s.name)}</th>`).join("")}</tr>`;
 const sub=`<tr>${subjects.map(()=>"<th>CA1</th><th>CA2</th><th>EXAM</th>").join("")}</tr>`;
 const body=studentPage.map(student=>`<tr><td class="admission">${esc(student.admission_number)}</td><td class="student-name">${esc(fullName(student))}</td>${Array.from({length:scoreCols},()=>'<td class="score-cell"></td>').join("")}</tr>`).join("");
 table.innerHTML=colgroup+`<thead>${top}${sub}</thead><tbody>${body}</tbody>`;
 page.append(table);
 const foot=document.createElement("div");foot.className="scan-footer";foot.textContent="Scan code identifies the class, subject order and student order. Do not fold or cover the QR code or table borders.";page.append(foot);
 const code=new QRCode(qr,{text:qrPayload,width:192,height:192,correctLevel:QRCode.CorrectLevel.M});
 return page;
}
function prepare(){
 const ids=[...options.querySelectorAll("input:checked")].map(input=>Number(input.value));
 const subjects=data.subjects.filter(s=>ids.includes(Number(s.id)));
 if(!subjects.length||subjects.length>12){status.textContent="Select between 1 and 12 subjects.";return;}
 const roster=data.students;
 const chunks=[];for(let i=0;i<roster.length;i+=20)chunks.push(roster.slice(i,i+20));
 pages.replaceChildren();
 chunks.forEach((chunk,index)=>pages.append(makePage(chunk,subjects,index+1,chunks.length)));
 status.textContent=`Prepared ${chunks.length} A4 page(s), ${roster.length} students and ${subjects.length} subjects. Use Print and choose A4 landscape, 100% scale, no browser headers or footers.`;
 window.setTimeout(()=>window.print(),800);
}
printButton.addEventListener("click",prepare);
init();
})();