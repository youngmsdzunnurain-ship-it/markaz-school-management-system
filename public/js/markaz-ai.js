(() => {
"use strict";
if (window.markazAiMounted || new URLSearchParams(location.search).get("print") === "1") return;
window.markazAiMounted = true;

const api = async (url, options = {}) => {
    const response = await fetch(url, {
        credentials: "same-origin",
        cache: "no-store",
        ...options,
        headers: { Accept: "application/json", ...(options.headers || {}) }
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.success) throw new Error(data.message || "MARKAZ AI could not complete that request.");
    return data;
};

const accountResult = awaitAccount();
accountResult.then(({ok, data}) => {
    if (!ok || !data?.success || !data.admin || !["admin","exam_officer","teacher","management"].includes(data.admin.role)) return;
    mount(data.admin);
});

function awaitAccount() {
    return fetch("/api/auth/me.php", { credentials: "same-origin", cache: "no-store", headers: {Accept:"application/json"} })
        .then(async response => ({ok:response.ok, data:await response.json()}))
        .catch(() => ({ok:false, data:{}}));
}

function mount(account) {\n    const studentMode = false;
    const endpoint = name => "/api/ai/" + name + ".php";
    if (document.getElementById("markazAiRoot")) return;
    if (!document.querySelector('link[data-markaz-ai-css]')) {
        const css = document.createElement("link"); css.rel = "stylesheet"; css.href = "/css/markaz-ai.css?v=20261116-ai1"; css.dataset.markazAiCss = "1"; document.head.appendChild(css);
    }
    const root = document.createElement("div");
    root.id = "markazAiRoot";
    root.innerHTML = `
      <button class="markaz-ai-launcher" type="button" aria-label="Open MARKAZ AI assistant" aria-expanded="false">
        <span class="markaz-ai-launcher-mark" aria-hidden="true">✦</span><span>MARKAZ AI</span>
      </button>
      <section class="markaz-ai-panel markaz-ai-chat" role="dialog" aria-modal="false" aria-label="MARKAZ AI assistant" hidden>
        <header class="markaz-ai-header">
          <div class="markaz-ai-brand"><span class="markaz-ai-logo">✦</span><div><strong>MARKAZ AI</strong><small>${studentMode ? "Published results · private session · no-cost mode" : "Verified school data · no-cost mode"}</small></div></div>
          <div class="markaz-ai-header-actions"><button type="button" data-ai-new title="New conversation" aria-label="New conversation">＋</button><button type="button" data-ai-close title="Close assistant" aria-label="Close assistant">×</button></div>
        </header>
        <div class="markaz-ai-tabs" role="tablist">
          <button type="button" class="active" data-ai-tab="chat" role="tab" aria-selected="true">Assistant</button>
          <button type="button" data-ai-tab="history" role="tab" aria-selected="false">History</button>
          <button type="button" data-ai-tab="scan" role="tab" aria-selected="false" ${studentMode ? "hidden" : ""}>Scan result</button>
          <button type="button" data-ai-tab="activity" role="tab" aria-selected="false" hidden>School activity</button>
        </div>
        <div class="markaz-ai-view" data-ai-view="chat">
          <div class="markaz-ai-welcome"><span class="markaz-ai-eyebrow">${studentMode ? "YOUR PUBLISHED RESULT" : "YOUR MARKAZ WORKSPACE"}</span><h2>${studentMode ? "Let’s understand your results." : "Your no-cost school assistant"}</h2><p>${studentMode ? "Ask me to explain your published subject results, totals and areas to practise. I can only see the result released to your Student Portal session." : "Ask about the current academic period, your assigned classes, or school information permitted for your account. I use verified MARKAZ records and will tell you when I cannot answer."}</p><div class="markaz-ai-suggestions"><button type="button">${studentMode ? "Explain my result and total" : "What is the current academic period?"}</button><button type="button">${studentMode ? "Which subjects should I practise more?" : "What can you do?"}</button></div></div>
          <div class="markaz-ai-messages" aria-live="polite"></div>
          <form class="markaz-ai-compose"><textarea name="message" rows="2" maxlength="4000" placeholder="Message MARKAZ AI…" aria-label="Message MARKAZ AI" required></textarea><button type="button" class="markaz-ai-voice" data-ai-voice aria-label="Voice input unavailable in no-cost mode" title="Voice input unavailable in no-cost mode" hidden>🎙</button><button type="submit" aria-label="Send message">↑</button><small>AI can make mistakes. Verify important school records.</small></form>
        </div>
        <div class="markaz-ai-view" data-ai-view="history" hidden><div class="markaz-ai-view-heading"><strong>Your conversations</strong><span>${studentMode ? "Private to this Student Portal session" : "Only visible to your account"}</span></div><div class="markaz-ai-history-list"></div></div>
        <div class="markaz-ai-view" data-ai-view="scan" hidden>
          <div class="markaz-ai-view-heading"><strong>Result-sheet scanner</strong><span>Draft extraction · review required</span></div>
          <p class="markaz-ai-scan-copy">Upload a clear photo of a result sheet. MARKAZ AI will transcribe visible values and flag unclear cells. Nothing is saved to student results.</p>
          <label class="markaz-ai-scan-scope" data-ai-scan-scope hidden><span>Assigned class and subject</span><select id="markazAiScanAssignment" aria-label="Choose an assigned class and subject"><option value="">Loading your assignments…</option></select></label>
          <form class="markaz-ai-scan-form"><label class="markaz-ai-upload"><span>＋</span><strong>Choose result-sheet image</strong><small>JPEG, PNG or WebP · up to 5 MB</small><input type="file" name="result_sheet" accept="image/jpeg,image/png,image/webp" required></label><button type="submit" class="markaz-ai-primary">Scan image</button></form>
          <div class="markaz-ai-scan-result" aria-live="polite"></div>
        </div>
        <div class="markaz-ai-view" data-ai-view="activity" hidden><div class="markaz-ai-view-heading"><strong>School activity</strong><span>Admin oversight · recorded activity only</span></div><div class="markaz-ai-activity-filters"><label>Date<input type="date" data-ai-activity-date></label><label>Staff member<select data-ai-activity-account><option value="0">All staff</option></select></label><button type="button" class="markaz-ai-primary" data-ai-refresh-activity>View activity</button></div><div class="markaz-ai-activity-list"></div></div>
        <div class="markaz-ai-status" role="status" aria-live="polite"></div>
      </section>`;
    document.body.appendChild(root);
    if (account.role === "teacher") {
        const scopeBox = root.querySelector("[data-ai-scan-scope]");
        const scopeSelect = root.querySelector("#markazAiScanAssignment");
        scopeBox.hidden = false;
        api("/api/ai/context.php").then(data => {
            scopeSelect.replaceChildren();
            data.scan_assignments.forEach(item => {
                const option = document.createElement("option");
                option.value = [item.class_type, item.class_id, item.subject_id].join("|");
                option.textContent = item.label;
                scopeSelect.appendChild(option);
            });
            if (!data.scan_assignments.length) {
                const option = document.createElement("option");
                option.value = "";
                option.textContent = "No active class/subject assignment";
                scopeSelect.appendChild(option);
            }
        }).catch(error => {
            scopeSelect.replaceChildren();
            const option = document.createElement("option");
            option.value = "";
            option.textContent = error.message;
            scopeSelect.appendChild(option);
        });
    }
    root.querySelector("[data-ai-tab=scan]").hidden = true;
    root.querySelector("[data-ai-voice]").hidden = true;
    const launcher = root.querySelector(".markaz-ai-launcher");
    const panel = root.querySelector(".markaz-ai-panel");
    const status = root.querySelector(".markaz-ai-status");
    const messages = root.querySelector(".markaz-ai-messages");
    let conversationId = 0;
    let busy = false;

    const sayStatus = text => { status.textContent = text || ""; };
    const setBusy = value => {
        busy = value;
        root.querySelectorAll("button, textarea, input").forEach(control => {
            if (control.dataset.aiClose !== undefined || control === launcher) return;
            control.disabled = value;
        });
        root.querySelector(".markaz-ai-compose button").textContent = value ? "…" : "↑";
    };
    const addMessage = (role, content) => {
        const item = document.createElement("article");
        item.className = "markaz-ai-message " + role;
        const label = document.createElement("span");
        label.className = "markaz-ai-message-label";
        label.textContent = role === "user" ? "You" : "MARKAZ AI";
        const body = document.createElement("div");
        body.className = "markaz-ai-message-content";
        body.textContent = content;
        item.append(label, body);
        messages.appendChild(item);
        messages.scrollTop = messages.scrollHeight;
    };
    const switchTab = tab => {
        root.querySelectorAll("[data-ai-tab]").forEach(button => {
            const active = button.dataset.aiTab === tab;
            button.classList.toggle("active", active);
            button.setAttribute("aria-selected", String(active));
        });
        root.querySelectorAll("[data-ai-view]").forEach(view => view.hidden = view.dataset.aiView !== tab);
        if (tab === "history") loadHistory();
        if (tab === "activity") loadActivity();
    };
    const open = () => {
        panel.hidden = false;
        launcher.setAttribute("aria-expanded", "true");
        launcher.classList.add("is-open");
        root.querySelector(".markaz-ai-compose textarea").focus();
    };
    const close = () => {
        if (voiceRecorder && voiceRecorder.state === "recording") {
            voiceRecorder.onstop = null;
            voiceRecorder.stop();
        }
        voiceStream?.getTracks().forEach(track => track.stop());
        voiceStream = null;
        voiceChunks = [];
        panel.hidden = true;
        launcher.setAttribute("aria-expanded", "false");
        launcher.classList.remove("is-open");
    };
    launcher.addEventListener("click", () => panel.hidden ? open() : close());
    root.querySelector("[data-ai-close]").addEventListener("click", close);
    root.querySelectorAll("[data-ai-tab]").forEach(button => button.addEventListener("click", () => switchTab(button.dataset.aiTab)));
    root.querySelectorAll(".markaz-ai-suggestions button").forEach(button => button.addEventListener("click", () => {
        const textarea = root.querySelector(".markaz-ai-compose textarea");
        textarea.value = button.textContent;
        textarea.focus();
    }));

    async function startConversation() {
        const data = await api(endpoint("conversations") + (studentMode ? "?" + "" : ""), {
            method: "POST", headers: {"Content-Type":"application/json"},
            body: JSON.stringify({title:"New conversation"})
        });
        conversationId = Number(data.conversation.id);
        messages.replaceChildren();
        switchTab("chat");
        return conversationId;
    }
    async function loadHistory() {
        const list = root.querySelector(".markaz-ai-history-list");
        list.replaceChildren();
        try {
            const data = await api(endpoint("conversations") + (studentMode ? "?" + studentQuery : ""));
            if (!data.conversations.length) {
                const empty = document.createElement("p"); empty.className = "markaz-ai-empty"; empty.textContent = "Your conversations will appear here."; list.appendChild(empty); return;
            }
            data.conversations.forEach(item => {
                const row = document.createElement("div"); row.className = "markaz-ai-history-row";
                const openButton = document.createElement("button"); openButton.type = "button"; openButton.className = "markaz-ai-history-open"; openButton.textContent = item.title;
                openButton.addEventListener("click", async () => {
                    try {
                        const history = await api(endpoint("history") + "?conversation_id=" + encodeURIComponent(item.id) + (studentMode ? "&" + studentQuery : ""));
                        conversationId = Number(item.id); messages.replaceChildren();
                        history.messages.forEach(message => addMessage(message.role, message.content));
                        switchTab("chat");
                    } catch (error) { sayStatus(error.message); }
                });
                const remove = document.createElement("button"); remove.type = "button"; remove.className = "markaz-ai-history-delete"; remove.textContent = "Delete"; remove.setAttribute("aria-label", "Delete conversation");
                remove.addEventListener("click", async () => {
                    if (!confirm("Delete this conversation and its messages?")) return;
                    try {
                        await api(endpoint("delete-conversation") + (studentMode ? "?" + studentQuery : ""), {method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({conversation_id:item.id})});
                        if (conversationId === Number(item.id)) { conversationId = 0; messages.replaceChildren(); }
                        loadHistory();
                    } catch (error) { sayStatus(error.message); }
                });
                row.append(openButton, remove); list.appendChild(row);
            });
        } catch (error) { sayStatus(error.message); }
    }
    root.querySelector("[data-ai-new]").addEventListener("click", async () => {
        try { await startConversation(); sayStatus(""); } catch (error) { sayStatus(error.message); }
    });
    let voiceRecorder = null;
    let voiceStream = null;
    let voiceChunks = [];
    root.querySelector("[data-ai-voice]").addEventListener("click", async () => {
        const voiceButton = root.querySelector("[data-ai-voice]");
        if (busy) return;
        if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
            sayStatus("Voice input is not supported by this browser. You can type your message instead.");
            return;
        }
        if (voiceRecorder && voiceRecorder.state === "recording") {
            setBusy(true);
            voiceRecorder.stop();
            voiceButton.textContent = "…";
            sayStatus("Preparing your voice input…");
            return;
        }
        try {
            voiceStream = await navigator.mediaDevices.getUserMedia({audio:true});
            voiceChunks = [];
            const preferred = MediaRecorder.isTypeSupported("audio/webm;codecs=opus") ? "audio/webm;codecs=opus" : "";
            voiceRecorder = new MediaRecorder(voiceStream, preferred ? {mimeType:preferred} : {});
            voiceRecorder.ondataavailable = event => { if (event.data && event.data.size) voiceChunks.push(event.data); };
            voiceRecorder.onstop = async () => {
                voiceStream?.getTracks().forEach(track => track.stop());
                voiceStream = null;
                const mime = voiceRecorder?.mimeType || "audio/webm";
                const blob = new Blob(voiceChunks, {type:mime});
                voiceChunks = [];
                if (!blob.size) { voiceButton.textContent = "🎙"; sayStatus("No audio was captured. Please try again."); return; }
                const extension = mime.split("/")[1]?.split(";")[0] || "webm";
                const form = new FormData();
                form.append("voice_note", blob, "markaz-voice-note." + extension);
                setBusy(true); sayStatus("Transcribing your voice…");
                try {
                    const data = await api(endpoint("transcribe") + (studentMode ? "?" + studentQuery : ""), {method:"POST",body:form});
                    const textarea = root.querySelector(".markaz-ai-compose textarea");
                    textarea.value = [textarea.value.trim(), data.transcript].filter(Boolean).join(" ");
                    textarea.focus();
                    sayStatus("Voice input added. Review it, then send when ready.");
                } catch (error) { sayStatus(error.message); }
                finally { setBusy(false); voiceButton.textContent = "🎙"; }
            };
            voiceRecorder.start();
            voiceButton.textContent = "■";
            sayStatus("Recording… tap the microphone again to stop.");
        } catch (error) {
            voiceStream?.getTracks().forEach(track => track.stop());
            voiceStream = null;
            sayStatus(error.name === "NotAllowedError" ? "Microphone permission was denied. Allow microphone access and try again." : "Could not start microphone recording on this device.");
        }
    });
    root.querySelector(".markaz-ai-compose").addEventListener("submit", async event => {
        event.preventDefault();
        if (busy) return;
        const textarea = event.currentTarget.elements.message;
        const message = textarea.value.trim();
        if (!message) return;
        textarea.value = "";
        addMessage("user", message);
        setBusy(true); sayStatus("MARKAZ AI is thinking…");
        try {
            const data = await api(endpoint("chat") + (studentMode ? "?" + studentQuery : ""), {method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({message,conversation_id:conversationId || undefined,page:location.pathname.split("/").pop()})});
            conversationId = Number(data.conversation_id);
            addMessage("assistant", data.answer); sayStatus("");
        } catch (error) { addMessage("assistant", error.message); sayStatus(""); }
        finally { setBusy(false); textarea.focus(); }
    });
    root.querySelector(".markaz-ai-scan-form").addEventListener("submit", async event => {
        event.preventDefault();
        if (busy) return;
        const file = event.currentTarget.elements.result_sheet.files[0];
        if (!file) return;
        const result = root.querySelector(".markaz-ai-scan-result");
        result.textContent = "";
        const form = new FormData(); form.append("result_sheet", file);
        if (account.role === "teacher") {
            const selectedScope = String(root.querySelector("#markazAiScanAssignment").value || "").split("|");
            if (selectedScope.length !== 3 || !selectedScope[0] || !selectedScope[1] || !selectedScope[2]) {
                result.textContent = "Choose one of your assigned classes and subjects before scanning.";
                return;
            }
            form.append("class_type", selectedScope[0]);
            form.append("class_id", selectedScope[1]);
            form.append("subject_id", selectedScope[2]);
        }
        setBusy(true); sayStatus("Scanning image…");
        try {
            const data = await api("/api/ai/scan-result.php", {method:"POST",body:form});
            result.textContent = data.message + "\n\n" + data.draft;
            sayStatus("");
        } catch (error) { result.textContent = error.message; sayStatus(""); }
        finally { setBusy(false); }
    });
    async function loadActivity() {
        const list = root.querySelector(".markaz-ai-activity-list");
        list.replaceChildren();
        try {
            const date = root.querySelector("[data-ai-activity-date]");
            const account = root.querySelector("[data-ai-activity-account]");
            const params = new URLSearchParams({limit:"100", date:date.value || new Date().toLocaleDateString("en-CA"), account_id:account.value || "0"});
            const data = await api("/api/ai/admin-activity.php?" + params.toString());
            if (!account.dataset.loaded) {
                data.accounts.forEach(person => {
                    const option = document.createElement("option");
                    option.value = String(person.id);
                    option.textContent = person.name + " · " + person.role.replaceAll("_", " ");
                    account.appendChild(option);
                });
                account.dataset.loaded = "1";
            }
            const items = [
                ...data.activity.map(item => ({...item, source:"School"})),
                ...data.ai_activity.map(item => ({...item, source:"AI"})),
                ...(data.student_ai_activity || []).map(item => ({...item, source:"Student AI"}))
            ].sort((a,b) => String(b.created_at).localeCompare(String(a.created_at)));
            items.forEach(item => {
                const row = document.createElement("article"); row.className = "markaz-ai-activity-item";
                const title = document.createElement("strong"); title.textContent = (item.account_name || "Account") + " · " + (item.account_role || "");
                const eventLabels = {result_created:"Entered a result",result_updated:"Edited a result",attendance_register_saved:"Saved an attendance register",result_scan_started:"Started result-sheet scan",result_scan_completed:"Completed result-sheet scan",message_completed:"Used MARKAZ AI",conversation_created:"Started an AI conversation",message_started:"Started using MARKAZ AI",voice_transcription_started:"Started voice transcription",voice_transcription_completed:"Transcribed voice input"};
                const detail = document.createElement("span"); detail.textContent = item.source + " / " + (eventLabels[item.event_type] || item.event_type.replaceAll("_", " "));
                let metadata = {};
                try { metadata = typeof item.metadata === "string" ? JSON.parse(item.metadata || "{}") : (item.metadata || {}); } catch (_) {}
                const refs = [];
                if (metadata.class_type) refs.push(metadata.class_type + " class #" + (metadata.class_id || ""));
                if (metadata.subject_id) refs.push("Subject #" + metadata.subject_id);
                if (metadata.attendance_date) refs.push("Register date " + metadata.attendance_date);
                if (metadata.saved_count !== undefined) refs.push(metadata.saved_count + " marks saved");
                if (metadata.cleared_count) refs.push(metadata.cleared_count + " marks cleared");
                if (metadata.conversation_id) refs.push("Conversation #" + metadata.conversation_id);
                if (item.entity_type && item.entity_id) refs.push(item.entity_type + " #" + item.entity_id);
                if (item.student_id) refs.push("Student #" + item.student_id);
                if (refs.length) { const context = document.createElement("small"); context.textContent = refs.join(" · "); row.append(title, detail, context); }
                else row.append(title, detail);
                const time = document.createElement("time"); time.textContent = item.created_at || "";
                row.appendChild(time); list.appendChild(row);
            });
            if (!items.length) { const empty = document.createElement("p"); empty.className = "markaz-ai-empty"; empty.textContent = "No recorded activity for this date and filter."; list.appendChild(empty); }
            const note = document.createElement("p"); note.className = "markaz-ai-disclaimer"; note.textContent = data.note; list.appendChild(note);
        } catch (error) { sayStatus(error.message); }
    }
    root.querySelector("[data-ai-refresh-activity]").addEventListener("click", loadActivity);
    root.querySelector("[data-ai-activity-date]").value = new Date().toLocaleDateString("en-CA");
    root.querySelector("[data-ai-activity-account]").addEventListener("change", loadActivity);
    if (account.role === "admin") root.querySelector('[data-ai-tab="activity"]').hidden = false;
}
})();