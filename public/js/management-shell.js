(() => {
"use strict";
const path=location.pathname;
if(!/\/(students\.html|classes\.html|class\.html|class-subjects\/(index\.html)?|subjects\/(index\.html)?|attendance\.html|school-fees\.html|settings\.html|student-result\.html|print-results\.html)$/.test(path)) return;
if(document.querySelector(".markaz-management-shell")) return;
const header=document.createElement("header");
header.className="markaz-management-shell";
header.innerHTML='<button class="markaz-shell-menu" type="button" aria-label="Open management menu" aria-expanded="false"><span aria-hidden="true">☰</span></button><a class="markaz-shell-brand" href="/dashboard.html"><img src="/assets/images/markaz-logo.jpg" alt=""><span>MARKAZ<small>Management</small></span></a><span class="markaz-shell-spacer"></span><button class="markaz-shell-profile" type="button" aria-expanded="false"><span class="admin-profile-avatar"><span id="markazShellInitials">AD</span></span><span class="markaz-shell-profile-copy"><strong id="markazShellName">Administrator</strong><small id="markazShellRole">Management</small></span><span aria-hidden="true">▾</span></button><div class="markaz-shell-profile-menu" hidden><div class="markaz-shell-profile-details"><strong id="markazShellMenuName">Administrator</strong><small id="markazShellEmail"></small></div><a href="/settings.html">Settings</a><div class="markaz-profile-preferences" data-markaz-preferences aria-label="Display preferences"></div><button type="button" id="markazShellLogout">Logout</button></div>';
document.body.prepend(header);
function mountPreferences(){window.markazI18n?.mountControls?.();window.markazTheme?.mountControls?.();}
mountPreferences();
window.markazTheme?.mountControls?.();
const overlay=document.createElement("div");overlay.className="markaz-shell-overlay";overlay.hidden=true;
const sidebar=document.createElement("aside");sidebar.className="markaz-shell-sidebar";
sidebar.innerHTML='<div class="markaz-shell-sidebar-head"><img src="/assets/images/markaz-logo.jpg" alt=""><strong>MARKAZ</strong><button type="button" aria-label="Close menu">×</button></div><nav><a href="/dashboard.html"><span data-no-translate aria-hidden="true">⌂</span><span>Dashboard</span></a><a href="/students.html"><span data-no-translate aria-hidden="true">♙</span><span>Students</span></a><a href="/classes.html"><span data-no-translate aria-hidden="true">▤</span><span>Classes</span></a><a href="/subjects/"><span data-no-translate aria-hidden="true">▣</span><span>Subjects</span></a><a href="/classes.html"><span data-no-translate aria-hidden="true">▥</span><span>Results</span></a><a href="/attendance.html"><span data-no-translate aria-hidden="true">▦</span><span>Attendance</span></a><a href="/school-fees.html"><span data-no-translate aria-hidden="true">▤</span><span>School Fees</span></a><a href="/print-results.html"><span data-no-translate aria-hidden="true">▧</span><span>Print Results</span></a><a href="/settings.html"><span data-no-translate aria-hidden="true">⚙</span><span>Settings</span></a></nav>';
document.body.prepend(overlay,sidebar);
const menu=header.querySelector(".markaz-shell-menu"), profile=header.querySelector(".markaz-shell-profile"), profileMenu=header.querySelector(".markaz-shell-profile-menu");
const close=()=>{sidebar.classList.remove("open");overlay.hidden=true;menu.setAttribute("aria-expanded","false");};
menu.addEventListener("click",()=>{const open=!sidebar.classList.contains("open");sidebar.classList.toggle("open",open);overlay.hidden=!open;menu.setAttribute("aria-expanded",String(open));});
sidebar.querySelector("button").addEventListener("click",close);overlay.addEventListener("click",close);sidebar.querySelectorAll("a").forEach(a=>a.addEventListener("click",close));
profile.addEventListener("click",()=>{profileMenu.hidden=!profileMenu.hidden;profile.setAttribute("aria-expanded",String(!profileMenu.hidden));});
document.addEventListener("click",e=>{if(!header.contains(e.target)){profileMenu.hidden=true;profile.setAttribute("aria-expanded","false");}});
fetch("/api/auth/me.php",{credentials:"same-origin",cache:"no-store"}).then(r=>r.json()).then(data=>{
if(!data.success||!data.admin)return;const a=data.admin,name=a.name||"Administrator";const initials=name.trim().split(/\s+/).map(x=>x[0]).slice(0,2).join("").toUpperCase();
header.querySelector("#markazShellName").textContent=name;header.querySelector("#markazShellMenuName").textContent=name;header.querySelector("#markazShellInitials").textContent=initials||"AD";header.querySelector("#markazShellRole").textContent=String(a.role||"Management").replaceAll("_"," ");header.querySelector("#markazShellEmail").textContent=a.email||"";
if(a.profile_image){const avatar=header.querySelector(".admin-profile-avatar");avatar.textContent="";const img=document.createElement("img");img.src="/"+String(a.profile_image).replace(/^\/+/,"");img.alt=name;avatar.appendChild(img);}
}).catch(e=>console.error("Management profile load failed:",e));
header.querySelector("#markazShellLogout").addEventListener("click",async()=>{const b=header.querySelector("#markazShellLogout");b.disabled=true;try{const r=await fetch("/api/auth/logout.php",{method:"POST",credentials:"same-origin"});const d=await r.json();if(!r.ok||!d.success)throw new Error(d.message||"Logout failed.");location.replace("/login.html");}catch(e){b.disabled=false;alert(e.message||"Unable to log out.");}});
document.body.classList.add("markaz-management-shell-active");
})();