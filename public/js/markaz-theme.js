(() => {
"use strict";
const key="markaz-dashboard-theme";
const root=document.documentElement;
let theme="light";
try{theme=localStorage.getItem(key)==="dark"?"dark":"light";}catch(_){}
function isStudentSurface(){
 return document.body.classList.contains("student-login-page") ||
        Boolean(document.querySelector(".student-login-card")) ||
        Boolean(document.body.classList.contains("student-portal-page"));
}
function paint(next,persist=true){
 theme=next==="dark"?"dark":"light";
 root.dataset.theme=theme;
 root.style.colorScheme=theme;
 if(persist){try{localStorage.setItem(key,theme);}catch(_){}}
 document.querySelectorAll("[data-markaz-theme-toggle]").forEach(button=>{
  button.setAttribute("aria-pressed",String(theme==="dark"));
  button.setAttribute("title",theme==="dark"?"Switch to light mode":"Switch to dark mode");
  const icon=button.querySelector("[data-markaz-theme-icon]");
  if(icon)icon.innerHTML=theme==="dark"?'<i class="fa-solid fa-sun" aria-hidden="true"></i>':'<i class="fa-solid fa-moon" aria-hidden="true"></i>';
  const label=button.querySelector("[data-markaz-theme-state]");
  if(label)label.textContent=theme==="dark"?"Dark":"Light";
 });
}
function mount(){
 if(isStudentSurface())return;
 paint(theme,false);
 const host=document.querySelector("[data-markaz-preferences],.markaz-shell-profile-menu,.dashboard-profile-menu");
 if(!host||host.querySelector("[data-markaz-theme-toggle]"))return;
 const button=document.createElement("button");
 button.type="button";
 button.className="markaz-preference-action markaz-theme-action";
 button.dataset.markazThemeToggle="";
 button.setAttribute("aria-label","Switch colour theme");
 button.innerHTML='<span class="markaz-preference-icon" data-markaz-theme-icon aria-hidden="true"></span><span class="markaz-preference-copy"><strong>Appearance</strong><small data-markaz-theme-state></small></span><span class="markaz-preference-switch" aria-hidden="true"><i></i></span>';
 button.addEventListener("click",()=>paint(theme==="dark"?"light":"dark"));
 host.appendChild(button);
 paint(theme,false);
}
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",mount,{once:true});else mount();
window.addEventListener("storage",event=>{if(event.key===key&&["dark","light"].includes(event.newValue))paint(event.newValue,false);});
window.markazTheme={get theme(){return theme;},setTheme:paint};
})();