(() => {
"use strict";
const trigger=document.getElementById("dashboardProfileTrigger");
const menu=document.getElementById("dashboardProfileMenu");
if(!trigger||!menu)return;
const close=()=>{menu.hidden=true;trigger.setAttribute("aria-expanded","false");};
trigger.addEventListener("click",event=>{event.stopPropagation();menu.hidden=!menu.hidden;trigger.setAttribute("aria-expanded",String(!menu.hidden));});
document.addEventListener("click",event=>{if(!menu.contains(event.target)&&!trigger.contains(event.target))close();});
document.addEventListener("keydown",event=>{if(event.key==="Escape")close();});
fetch("/api/auth/me.php",{credentials:"same-origin",cache:"no-store"}).then(response=>response.json()).then(data=>{
 if(!data.success||!data.admin)return;
 const account=data.admin,name=account.name||"Account";
 const initials=name.trim().split(/\s+/).map(part=>part[0]).slice(0,2).join("").toUpperCase()||"AC";
 document.getElementById("dashboardProfileName").textContent=name;
 document.getElementById("dashboardProfileMenuName").textContent=name;
 document.getElementById("dashboardProfileInitials").textContent=initials;
 document.getElementById("dashboardProfileEmail").textContent=account.email||"";
 if(account.profile_image){const avatar=document.getElementById("dashboardProfileInitials");const image=document.createElement("img");image.src="/"+String(account.profile_image).replace(/^\/+/,"");image.alt="";avatar.replaceChildren(image);}
}).catch(()=>{});
document.getElementById("dashboardProfileLogout")?.addEventListener("click",()=>document.getElementById("logoutButton")?.click());
})();