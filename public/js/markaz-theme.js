(() => {
 "use strict";
 const KEY="markaz.theme", root=document.documentElement;
 const system=()=>matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";
 const stored=()=>{try{const v=localStorage.getItem(KEY);return v==="dark"||v==="light"?v:null}catch(_){return null}};
 let current=stored()||root.dataset.theme||system();
 const meta=document.querySelector('meta[name="theme-color"]')||(()=>{const m=document.createElement("meta");m.name="theme-color";document.head.appendChild(m);return m;})();
 const icon=mode=>mode==="dark"?"☀":"☾";
 function apply(mode,save=false){
  current=mode==="dark"?"dark":"light";root.dataset.theme=current;root.style.colorScheme=current;
  if(meta)meta.content=getComputedStyle(root).getPropertyValue("--canvas").trim();
  if(save)try{localStorage.setItem(KEY,current)}catch(_){}
  document.querySelectorAll("[data-markaz-theme-toggle]").forEach(b=>{b.innerHTML=icon(current);b.setAttribute("aria-label",current==="dark"?"Switch to light mode":"Switch to dark mode");b.setAttribute("aria-pressed",String(current==="dark"));});
 }
 function addToggle(host,where="append"){
  if(!host||host.querySelector(":scope > [data-markaz-theme-toggle]"))return;
  const b=document.createElement("button");b.type="button";b.className="markaz-theme-toggle";b.dataset.markazThemeToggle="";b.title="Change colour theme";
  if(where==="floating")b.classList.add("markaz-theme-toggle-floating");
  b.innerHTML=icon(current);b.setAttribute("aria-label",current==="dark"?"Switch to light mode":"Switch to dark mode");b.setAttribute("aria-pressed",String(current==="dark"));
  host.appendChild(b);
 }
 function mount(){
  const profile=document.querySelector(".markaz-shell-profile-menu");
  if(profile){addToggle(profile);return}
  const dash=document.querySelector("#dashboardProfileMenu,.dashboard-profile-menu");
  if(dash){addToggle(dash);return}
  const prefs=document.querySelector("[data-markaz-preferences]");
  if(prefs){addToggle(prefs);return}
  const head=document.querySelector(".topbar,.students-topbar,.classes-topbar,.class-topbar,.attendance-header,.settings-header,.fees-placeholder-top,.role-login-page");
  if(head){addToggle(head);return}
  addToggle(document.body,"floating");
 }
 document.addEventListener("click",e=>{if(e.target.closest("[data-markaz-theme-toggle]"))apply(current==="dark"?"light":"dark",true)});
 if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",mount,{once:true});else mount();
 const observer=new MutationObserver(mount);observer.observe(document.body,{childList:true,subtree:true});
 matchMedia("(prefers-color-scheme: dark)").addEventListener?.("change",e=>{if(!stored())apply(e.matches?"dark":"light")});
 window.addEventListener("storage",e=>{if(e.key===KEY)apply(stored()||system())});
 apply(current);
 window.markazTheme={mountControls:mount,setTheme:m=>apply(m,true),get theme(){return current}};
})();