(() => {
"use strict";
const key="markaz-dashboard-theme";
const root=document.documentElement;
let stored=null;try{stored=localStorage.getItem(key);}catch(_){}
const theme=stored==="dark"?"dark":"light";
root.dataset.theme=theme;
root.style.colorScheme=theme;
function init(){
 if(!document.body.classList.contains("dashboard-page")&&!document.querySelector(".app .sidebar"))return;
 const button=document.createElement("button");button.type="button";button.className="markaz-theme-toggle";button.setAttribute("aria-pressed",String(theme==="dark"));button.addEventListener("click",()=>{
  const next=root.dataset.theme==="dark"?"light":"dark";root.dataset.theme=next;root.style.colorScheme=next;try{localStorage.setItem(key,next);}catch(_){}button.setAttribute("aria-pressed",String(next==="dark"));button.textContent=next==="dark"?"☀ Light mode":"☾ Dark mode";
 });
 button.textContent=theme==="dark"?"☀ Light mode":"☾ Dark mode";
 const target=document.querySelector(".topbar .topbar-actions,.topbar .topbar-right,.topbar,.app .topbar");
 if(target)target.append(button);else document.body.append(button);
}
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init,{once:true});else init();
window.addEventListener("storage",e=>{if(e.key===key&&["dark","light"].includes(e.newValue)){root.dataset.theme=e.newValue;root.style.colorScheme=e.newValue;}});
})();