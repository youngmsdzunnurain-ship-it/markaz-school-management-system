"use strict";

const $ = id => document.getElementById(id);
let classes = [];
let students = [];

function listFrom(payload, key) {
    if (Array.isArray(payload)) return payload;
    if (!payload || typeof payload !== "object") return [];
    if (Array.isArray(payload[key])) return payload[key];
    if (Array.isArray(payload.data)) return payload.data;
    if (payload.data && Array.isArray(payload.data[key])) {
        return payload.data[key];
    }
    if (payload.success === false) {
        throw new Error(payload.message || "Unable to load records.");
    }
    return [];
}

async function getList(url, key) {
    const response = await fetch(url, {
        credentials: "same-origin",
        cache: "no-store"
    });
    const payload = await response.json();
    if (!response.ok || payload.success === false) {
        throw new Error(payload.message || "Request failed.");
    }
    return listFrom(payload, key);
}

function classId(item) {
    return String(item.id ?? item.class_id ?? "");
}

function className(item) {
    return item.class_name || item.name || item.title ||
           item.className || "Unnamed class";
}

function displayClassName(item) {
    const name = className(item);
    return /^ss\s*1$/i.test(String(name).trim()) ? "SS1 Science" : name;
}

function isConventionalClass(name) {
    const normalized = String(name || "")
        .trim()
        .replace(/[._-]+/g, " ")
        .replace(/\s+/g, " ");

    const compact = normalized.replace(/\s+/g, "").toLowerCase();

    if (/^(?:ss|sss)[1-3]$/.test(compact)) return true;
    if (/^seniorsecondary(?:school)?(?:1|2|3|one|two|three)$/i.test(compact)) return true;

    return /^(?:Pre\s*Nursery\s*[12]|KG(?:\s*[1-3])?|Kindergarten(?:\s*[1-3])?|Nursery\s*[1-3]?|Basic\s*[1-6]|Primary\s*[1-6]|JSS\s*[1-3]|Junior\s*Secondary\s*[1-3]|SSS?\s*[1-3]|Senior\s*Secondary(?:\s*School)?\s*(?:[1-3]|One|Two|Three))$/i.test(normalized);
}

function studentId(item) {
    return String(item.id ?? item.student_id ?? "");
}

function studentName(item) {
    return [item.first_name, item.middle_name, item.last_name]
        .filter(Boolean).join(" ") ||
        item.full_name || item.name || "Unnamed student";
}

function showClasses() {
    $("classesPanel").classList.remove("hidden");
    $("studentsPanel").classList.add("hidden");
    $("backToClasses").textContent = "← Go Back";
}

function renderClasses() {
    const container = $("classesList");
    container.replaceChildren();

    const conventionalClasses = classes.filter(item =>
        isConventionalClass(className(item))
    );

    if (!conventionalClasses.length) {
        $("classMessage").textContent = "No classes were found.";
        return;
    }

    $("classMessage").textContent = "Choose the class to print.";
    conventionalClasses.forEach(item => {
        const id = classId(item);
        if (!id) return;

        const button = document.createElement("button");
        button.type = "button";
        button.className = "class-button";

        const title = document.createElement("strong");
        title.textContent = displayClassName(item);
        const hint = document.createElement("small");
        hint.textContent = "View students and print results";

        button.append(title, hint);
        button.addEventListener("click", () => showStudents(item));
        container.append(button);
    });
}

function printStudent(sid, cid) {
    const params = new URLSearchParams({
        student_id: String(sid),
        class_id: String(cid),
        class_type: "conventional",
        print: "1",
        display_version: "20261106-targeted-student-display-fix"
    });
    window.location.href = "./student-result-print.html?" + params.toString();
}


async function getClassCompletion(classIdValue, classType = "conventional") {
  const response = await fetch(
    "./api/results/class-completion.php?class_type=" + encodeURIComponent(classType) +
      "&class_id=" + encodeURIComponent(classIdValue),
    { credentials: "same-origin", cache: "no-store" }
  );
  const payload = await response.json();

  if (!response.ok || payload.success === false) {
    throw new Error(payload.message || "Unable to verify result completion.");
  }

  return {
    threshold: Number(payload.threshold ?? 95),
    byStudent: new Map(
      (Array.isArray(payload.students) ? payload.students : [])
        .map(item => [String(item.student_id), item])
    )
  };
}

async function printAllEligibleStudents(classIdValue, studentIds) {
  const popup = window.open("", "_blank");
  if (!popup) {
    alert("Please allow pop-ups to print the class results.");
    return;
  }

  const button = $("printAllEligibleButton");
  button.disabled = true;
  button.textContent = "Preparing results…";
  const frames = [];
  const printDocument = popup.document;
  printDocument.open();
  printDocument.write(`<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="${location.origin}/"><title>Class Results | MARKAZ</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><link href="https://fonts.googleapis.com/css2?family=Amiri:wght@700&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="/css/app-watermark.css"><link rel="stylesheet" href="/css/website-image-polish.css"><link rel="stylesheet" href="/css/standard-page-heading.css"><link rel="stylesheet" href="/css/student-result.css" media="screen"><link rel="stylesheet" href="/css/student-result-print.css?v=20261031-watermark-center" media="print"><style>@media print{html,body{height:auto!important;overflow:visible!important}.batch-result-page{break-after:page;page-break-after:always}.batch-result-page:last-child{break-after:auto;page-break-after:auto}}.batch-result-page{display:block;min-height:100vh;page-break-after:always}.batch-result-page:last-child{page-break-after:auto}</style></head><body><main id="batchResults"></main></body></html>`);
  printDocument.close();

  const waitForResult = frame => new Promise((resolve, reject) => {
    let observer;
    const timeout = setTimeout(() => {
      observer?.disconnect();
      reject(new Error("A student's result did not finish loading."));
    }, 30000);
    const check = () => {
      try {
        if (frame.contentDocument?.body?.dataset.resultReady === "true") {
          clearTimeout(timeout);
          observer?.disconnect();
          resolve();
        }
      } catch (error) {
        clearTimeout(timeout);
        observer?.disconnect();
        reject(error);
      }
    };
    frame.addEventListener("load", check, { once: true });
    const attach = () => {
      if (!frame.contentDocument?.body) return;
      observer = new MutationObserver(check);
      observer.observe(frame.contentDocument.body, { attributes: true, attributeFilter: ["data-result-ready"] });
      check();
    };
    attach();
  });

  try {
    for (const sid of studentIds) {
      const frame = document.createElement("iframe");
      frame.hidden = true;
      frame.setAttribute("aria-hidden", "true");
      frame.src = "./student-result-print.html?" + new URLSearchParams({
        student_id: String(sid),
        class_id: String(classIdValue),
        class_type: "conventional",
        print: "1"
      }).toString();
      document.body.appendChild(frame);
      frames.push(frame);
    }

    await Promise.all(frames.map(waitForResult));

    const blockedFrame = frames.find(frame =>
      frame.contentDocument?.body?.classList.contains("result-print-blocked")
    );
    if (blockedFrame) {
      throw new Error("At least one selected result is not eligible to print. Only students meeting the configured completion threshold can be printed.");
    }

    const target = printDocument.getElementById("batchResults");
    for (const frame of frames) {
      const source = frame.contentDocument.querySelector("main.student-result-page");
      if (!source) throw new Error("A rendered student result could not be found.");
      const page = source.cloneNode(true);
      page.classList.add("batch-result-page");
      page.querySelectorAll("img").forEach(img => { img.loading = "eager"; });
      target.appendChild(page);
    }

    await Promise.all(Array.from(target.images).map(img =>
      img.complete ? Promise.resolve() : new Promise(resolve => {
        img.addEventListener("load", resolve, { once: true });
        img.addEventListener("error", resolve, { once: true });
      })
    ));
    await printDocument.fonts.ready;
    popup.focus();
    popup.print();
  } catch (error) {
    popup.close();
    alert(error.message || "Unable to prepare all eligible results.");
    console.error("Print all results failed:", error);
  } finally {
    frames.forEach(frame => frame.remove());
    button.disabled = false;
    button.textContent = "Print All Eligible";
  }
}

async function showStudents(selectedClass) {
  $("classesPanel").classList.add("hidden");
  $("studentsPanel").classList.remove("hidden");
  $("backToClasses").textContent = "← Go Back";
  $("selectedClassTitle").textContent = displayClassName(selectedClass);

  const id = classId(selectedClass);
  const matching = students.filter(item =>
    String(item.class_id ?? item.main_class_id ?? "") === id
  );
  const container = $("studentsList");
  container.replaceChildren();

  $("studentMessage").textContent = matching.length
    ? "Checking each student's results and print eligibility…"
    : "No students were found for this class.";

  const rows = new Map();
  const printAllButton = $("printAllEligibleButton");
  printAllButton.hidden = true;
  printAllButton.disabled = true;

  matching.forEach(item => {
    const sid = studentId(item);
    if (!sid) return;

    const row = document.createElement("div");
    row.className = "student";

    const details = document.createElement("div");
    details.style.cssText = "display:flex;flex-direction:column;gap:5px;min-width:0;flex:1;";

    const name = document.createElement("span");
    name.className = "student-name";
    name.textContent = studentName(item);

    const status = document.createElement("span");
    status.style.cssText = "font-size:0.85rem;line-height:1.45;overflow-wrap:anywhere;";
    status.textContent = "Checking eligibility…";

    const missing = document.createElement("span");
    missing.style.cssText = "font-size:0.8rem;line-height:1.4;overflow-wrap:anywhere;";
    missing.textContent = "";

    const button = document.createElement("button");
    button.type = "button";
    button.textContent = "Checking…";
    button.disabled = true;
    button.addEventListener("click", () => {
      if (!button.disabled) printStudent(sid, id);
    });

    details.append(name, status, missing);
    row.append(details, button);
    container.append(row);
    rows.set(sid, { status, missing, button });
  });

  if (!matching.length) return;

  try {
    const completion = await getClassCompletion(id, "conventional");

    // Ignore a late response if the user has navigated to another class.
    if ($("selectedClassTitle").textContent !== displayClassName(selectedClass)) return;

    const eligibleIds = [];
    rows.forEach(({ status, missing, button }, sid) => {
      const item = completion.byStudent.get(sid);

      if (!item) {
        status.textContent = "Eligibility could not be verified — printing disabled.";
        status.style.color = "#b42318";
        button.textContent = "Unavailable";
        return;
      }

      const percentage = Number(item.percentage);
      const threshold = Number(item.threshold ?? completion.threshold);
      const eligible = item.eligible === true || item.eligible === 1 || item.eligible === "1";

      status.textContent = eligible
        ? `Eligible to print · Completion ${percentage}% (required ${threshold}%)`
        : `Not eligible to print · Completion ${percentage}% (required ${threshold}%)`;
      status.style.color = eligible ? "#18794e" : "#b42318";

      const missingFields = Array.isArray(item.missing_fields)
        ? item.missing_fields
        : [];

      missing.textContent = missingFields.length
        ? "Missing: " + missingFields.join(", ")
        : (eligible ? "All required fields are complete." : "Required fields remain incomplete.");

      button.disabled = !eligible;
      button.textContent = eligible ? "Print Results" : "Incomplete";
      if (eligible) eligibleIds.push(sid);
    });

    printAllButton.hidden = eligibleIds.length === 0;
    printAllButton.disabled = eligibleIds.length === 0;
    printAllButton.textContent = "Print All Eligible (" + eligibleIds.length + ")";
    printAllButton.onclick = () => printAllEligibleStudents(id, eligibleIds);
    $("studentMessage").textContent =
      "Only students meeting the configured result-entry completion threshold can be printed. Each eligible student will print on a separate page.";
  } catch (error) {
    rows.forEach(({ status, button }) => {
      status.textContent = "Eligibility could not be verified — printing disabled.";
      status.style.color = "#b42318";
      button.disabled = true;
      button.textContent = "Unavailable";
    });
    $("studentMessage").textContent =
      "Could not verify completion. Students are still listed, but printing stays disabled until verification works.";
    console.error("Class completion check failed:", error);
  }
}

$("backToClasses").addEventListener("click", () => {
    if (!$("studentsPanel").classList.contains("hidden")) {
        showClasses();
    } else {
        window.location.href = "./dashboard.html";
    }
});

async function initialize() {
    try {
        const results = await Promise.all([
            getList("./api/classes/list.php", "classes"),
            getList("./api/students/list.php", "students")
        ]);
        classes = results[0];
        students = results[1];
        renderClasses();
    } catch (error) {
        $("classMessage").textContent =
            error.message || "Unable to load classes and students.";
    }
}

const directParams = new URLSearchParams(window.location.search);
if (directParams.get("student_id") && directParams.get("class_id")) {
    $("classesPanel").classList.add("hidden");
    $("studentsPanel").classList.add("hidden");
    printStudent(directParams.get("student_id"), directParams.get("class_id"));
} else {
    initialize();
}
