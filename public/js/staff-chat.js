(() => {
"use strict";
const api = "api/messages/index.php";
const $ = id => document.getElementById(id);
const layout = document.querySelector(".chat-layout");
const state = { staff: [], conversations: [], activeId: 0, messages: [], currentUserId: 0, currentName: "", poll: null, busy: false };
const escDate = value => { const d = new Date(String(value || "").replace(" ", "T")); return Number.isNaN(d.getTime()) ? "" : d.toLocaleTimeString([], {hour:"2-digit",minute:"2-digit"}); };
const initials = value => String(value || "M").trim().split(/\s+/).slice(0,2).map(x => x[0] || "").join("").toUpperCase();
function avatar(person, className="person-avatar") {
 const el=document.createElement("span");el.className=className;
 if(person && person.avatar_url){
  const img=document.createElement("img");img.src=person.avatar_url;img.alt="";img.loading="lazy";
  img.addEventListener("error",()=>{el.textContent=initials(person.name);},{once:true});el.append(img);
 }else el.textContent=initials(person && person.name);
 if(person && person.is_online){const dot=document.createElement("i");dot.className="online-dot";dot.setAttribute("aria-label","Online");el.append(dot);}
 return el;
}
function otherParticipant(conversation){return (conversation.participants||[]).find(p=>Number(p.id)!==state.currentUserId);}
async function heartbeat(){try{await request(api,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({action:"heartbeat"})});}catch(_){} }
async function request(url, options = {}) {
 const response = await fetch(url, {credentials:"same-origin",cache:"no-store",...options,headers:{"Accept":"application/json",...(options.headers || {})}});
 const data = await response.json().catch(() => ({}));
 if (!response.ok || !data.success) throw new Error(data.message || "The request could not be completed.");
 return data;
}
function toast(message) { const el=$("toast"); el.textContent=message; el.classList.add("show"); setTimeout(()=>el.classList.remove("show"),2600); }
function conversationName(item) {
 if (item.conversation_type === "group") return item.title || "Staff group";
 const other=(item.participants || []).find(p=>Number(p.id)!==state.currentUserId);
 return other ? other.name : "Private conversation";
}
function renderConversations() {
 const q=$("conversationSearch").value.trim().toLowerCase();
 const list=$("conversationList"); list.replaceChildren();
 const rows=state.conversations.filter(c => (conversationName(c)+" "+(c.last_message||"")).toLowerCase().includes(q));
 if (!rows.length) { const p=document.createElement("p"); p.className="empty-note"; p.textContent=q?"No matching conversations.":"No conversations yet. Let's chat with a staff member."; list.append(p); return; }
 rows.forEach(c=>{
  const button=document.createElement("button"); button.type="button"; button.className="conversation-item"+(Number(c.id)===state.activeId?" active":"");
  const person=c.conversation_type==="direct"?otherParticipant(c):null; const avatarEl=avatar(person||{name:conversationName(c)});
  const copy=document.createElement("span"); copy.className="conversation-copy";
  const name=document.createElement("strong"); name.textContent=conversationName(c);
  const preview=document.createElement("p"); preview.textContent=c.last_message || (c.conversation_type==="group"?"Group conversation":"Start your conversation");
  copy.append(name,preview);
  const meta=document.createElement("span"); meta.className="conversation-meta";
  const time=document.createElement("time"); time.textContent=escDate(c.last_message_at || c.updated_at);
  meta.append(time);
  if(Number(c.unread_count)>0){const unread=document.createElement("span");unread.className="unread";unread.textContent=String(c.unread_count);meta.append(unread);}
  button.append(avatarEl,copy,meta); button.addEventListener("click",()=>openConversation(Number(c.id))); list.append(button);
 });
}
async function loadConversations() { const data=await request(api+"?action=conversations"); state.conversations=data.conversations||[]; renderConversations(); }
function renderMessages() {
 const list=$("messageList"); list.replaceChildren();
 if(!state.messages.length){const p=document.createElement("p");p.className="empty-note";p.textContent="This is the beginning of your conversation.";list.append(p);return;}
 state.messages.forEach(m=>{
  const bubble=document.createElement("article");bubble.className="message"+(Number(m.sender_id)===state.currentUserId?" mine":"");
  if(Number(m.sender_id)!==state.currentUserId){const author=document.createElement("div");author.className="message-author";author.textContent=m.sender_name; bubble.append(author);}
  const body=document.createElement("div");body.className="message-body";body.textContent=m.body;
  const footer=document.createElement("div");footer.className="message-footer";
  const time=document.createElement("time");time.textContent=escDate(m.created_at);footer.append(time);
  if(Number(m.sender_id)===state.currentUserId){
   const ticks=document.createElement("span");ticks.className="message-ticks";
   const recipients=Number(m.recipient_count),delivered=Number(m.delivered_count),read=Number(m.read_count);
   const status=recipients>0&&read===recipients?"read":recipients>0&&delivered===recipients?"delivered":"sent";
   ticks.classList.add(status);ticks.textContent=status==="sent"?"✓": "✓✓";
   ticks.title=status==="read"?"Read by all recipients":status==="delivered"?"Delivered to all recipients":"Sent";
   ticks.setAttribute("aria-label",ticks.title);footer.append(ticks);
  }
  bubble.append(body,footer);list.append(bubble);
 });
 list.scrollTop=list.scrollHeight;
}
async function loadMessages(silent=false) {
 if(!state.activeId)return;
 const data=await request(api+"?action=messages&conversation_id="+encodeURIComponent(state.activeId));
 const oldLast=state.messages.length?Number(state.messages[state.messages.length-1].id):0;
 state.messages=data.messages||[];renderMessages();
 if(!silent || (state.messages.length && Number(state.messages[state.messages.length-1].id)!==oldLast)) await loadConversations();
}
async function openConversation(id) {
 state.activeId=id; const c=state.conversations.find(x=>Number(x.id)===id); if(!c)return;
 $("threadTitle").textContent=conversationName(c);
 $("threadSubtitle").textContent=c.conversation_type==="group"?(c.participants||[]).map(p=>p.name).join(" · "):"Private staff conversation";
 const directPerson=c.conversation_type==="direct"?otherParticipant(c):null;
 $("threadAvatar").replaceWith(avatar(directPerson||{name:conversationName(c)},"person-avatar"));$("threadAvatar").id="threadAvatar";
 const presence=directPerson?(directPerson.is_online?"Online":"Offline"):"Group conversation";
 $("threadSubtitle").textContent=c.conversation_type==="group"?(c.participants||[]).map(p=>p.name).join(" · "):presence;
 $("threadWelcome").hidden=true;$("activeThread").hidden=false;layout.classList.add("thread-open");
 renderConversations();
 try{await loadMessages();}catch(e){toast(e.message);}
}
function openDialog() {
 $("dialogError").textContent="";$("staffSearch").value="";$("groupTitle").value="";
 document.querySelectorAll("#staffDirectory input").forEach(x=>x.checked=false);
 $("newChatDialog").showModal();
}
function renderDirectory() {
 const q=$("staffSearch").value.trim().toLowerCase(), list=$("staffDirectory");list.replaceChildren();
 const rows=state.staff.filter(s=>(s.name+" "+s.role).toLowerCase().includes(q));
 if(!rows.length){const p=document.createElement("p");p.className="empty-note";p.textContent="No staff members found.";list.append(p);return;}
 rows.forEach(s=>{
  const label=document.createElement("label");label.className="staff-option";
  const input=document.createElement("input");input.type="checkbox";input.value=String(s.id);input.name="recipients";
  const text=document.createElement("span");text.textContent=s.name;
  const personAvatar=avatar(s,"directory-avatar");label.append(personAvatar);
  const role=document.createElement("small");role.textContent=String(s.role).replaceAll("_"," ");
  text.append(role);label.append(input,text);list.append(label);
 });
}
async function startConversation(event) {
 event.preventDefault();const ids=Array.from(document.querySelectorAll("#staffDirectory input:checked")).map(x=>Number(x.value));
 const title=$("groupTitle").value.trim();
 if(!ids.length){$("dialogError").textContent="Select at least one staff member.";return;}
 if(ids.length>1&&!title){$("dialogError").textContent="Enter a name for this group chat.";return;}
 const button=$("createChatButton");button.disabled=true;
 try{
  const data=await request(api,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({action:"create_conversation",recipient_ids:ids,title})});
  $("newChatDialog").close();await loadConversations();await openConversation(Number(data.conversation_id));toast(data.existing?"Opened existing conversation.":"Conversation created.");
 }catch(e){$("dialogError").textContent=e.message;}finally{button.disabled=false;}
}
async function sendMessage(event) {
 event.preventDefault();if(state.busy||!state.activeId)return;
 const input=$("messageInput"),body=input.value.trim();if(!body)return;
 state.busy=true;$("sendButton").disabled=true;
 try{
  await request(api,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({action:"send_message",conversation_id:state.activeId,body})});
  input.value="";await loadMessages();
 }catch(e){toast(e.message);}finally{state.busy=false;$("sendButton").disabled=false;input.focus();}
}
async function init() {
 try {
  const me=await request("api/auth/me.php");state.currentUserId=Number(me.admin.id);state.currentName=me.admin.name||"Staff";
  $("currentStaff").textContent=state.currentName+" · "+String(me.admin.role).replaceAll("_"," ");
  await heartbeat();const directory=await request(api+"?action=directory");state.staff=directory.staff||[];renderDirectory();
  await loadConversations();
  $("newChatButton").addEventListener("click",openDialog);$("welcomeNewChat").addEventListener("click",openDialog);
  $("closeDialog").addEventListener("click",()=>$("newChatDialog").close());$("cancelDialog").addEventListener("click",()=>$("newChatDialog").close());
  $("newChatForm").addEventListener("submit",startConversation);$("staffSearch").addEventListener("input",renderDirectory);
  $("conversationSearch").addEventListener("input",renderConversations);$("messageForm").addEventListener("submit",sendMessage);
  $("messageInput").addEventListener("keydown",e=>{if(e.key==="Enter"&&!e.shiftKey){e.preventDefault();$("messageForm").requestSubmit();}});
  const back=document.createElement("button");back.type="button";back.className="mobile-back";back.textContent="Back";back.addEventListener("click",()=>{layout.classList.remove("thread-open");state.activeId=0;});document.querySelector(".thread-heading").prepend(back);
  state.poll=setInterval(async()=>{await heartbeat();try{await loadConversations();if(state.activeId)await loadMessages(true);}catch(_){}},10000);
 }catch(e){$("conversationList").textContent=e.message;toast(e.message);}
}
document.addEventListener("DOMContentLoaded",init);
})();