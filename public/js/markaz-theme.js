(() => {
"use strict";
const key="markaz-dashboard-theme";
const root=document.documentElement;
const body=document.body;
const isStudent=()=>body?.classList.contains("student-login-page")||Boolean(document.querySelector(".student-login-card"));
const isAdminLogin=()=>body?.classList.contains("admin-login-page");
let theme="light";
try{theme=localStorage.getItem(key)==="dark"?"dark":"light";}catch(_){}
function apply(next){
 theme=next==="dark"?"dark":"light";
 root.dataset.theme=theme;
 root.style.colorScheme=theme;
 try{localStorage.setItem(key,theme);}catch(_){}
 document.querySelectorAll("[data-markaz-theme-toggle]").forEach(button=>{
  button.setAttribute("aria-pressed",String(theme==="dark"));
  button.setAttribute("title",theme==="dark"?"Switch to light mode":"Switch to dark mode");
  const state=button.querySelector("[data-markaz-theme-state]");
  if(state)state.textContent=theme==="dark"?"Dark":"Light";
  const icon=button.querySelector("[data-markaz-theme-icon]");
  if(icon)icon.textContent=theme==="dark"?"☀":"☾";
 });
}
function init(){
 if(isStudent()||isAdminLogin())return;
 apply(theme);
 const host=document.querySelector("[data-markaz-preferences],.markaz-shell-profile-menu");
 if(!host||host.querySelector("[data-markaz-theme-toggle]"))return;
 const button=document.createElement("button");
 button.type="button";
 button.className="markaz-preference-action markaz-theme-action";
 button.setAttribute("data-markaz-theme-toggle","");
 button.setAttribute("aria-label","Switch colour theme");
 button.innerHTML='<span class="markaz-preference-icon" data-markaz-theme-icon aria-hidden="true">☾</span><span class="markaz-preference-copy"><strong>Appearance</strong><small data-markaz-theme-state></small></span><span class="markaz-preference-switch" aria-hidden="true"><i></i></span>';
 button.addEventListener("click",()=>apply(theme==="dark"?"light":"dark"));
 host.appendChild(button);
 apply(theme);
}
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init,{once:true});else init();
window.addEventListener("storage",event=>{if(event.key===key&&["dark","light"].includes(event.newValue)){theme=event.newValue;root.dataset.theme=theme;root.style.colorScheme=theme;document.querySelectorAll("[data-markaz-theme-toggle]").forEach(button=>{button.setAttribute("aria-pressed",String(theme==="dark"));const icon=button.querySelector("[data-markaz-theme-icon]");if(icon)icon.textContent=theme==="dark"?"☀":"☾";const state=button.querySelector("[data-markaz-theme-state]");if(state)state.textContent=theme==="dark"?"Dark":"Light";});}});
window.markazTheme={get theme(){return theme;},setTheme:apply};
})();