/* ============================================================
SkyTruFiber Client Chat System — FINAL REVISED BUILD (2025)
============================================================ */

/* ---------------- GLOBAL STATE ---------------- */
let editing = false;
let activePopup = null;
const ticketId = new URLSearchParams(window.location.search).get("ticket");
let suggestionShown = false;

/* ============================================================
INIT
============================================================ */
$(document).ready(() => {

    if (!ticketId) {
        $("#chat-messages").html(`
            <p style="text-align:center;padding:20px;color:#777;">Invalid ticket.</p>
        `);
        return;
    }

    // Move popup inside modal for correct positioning
    const popup = $("#msg-action-popup");
    if (popup.length) $(".chat-modal").append(popup.detach());

    loadMessages(true);
    checkTicketStatus();

    // Polling every 3.5 seconds
    setInterval(() => {
        if (!editing && !activePopup) {
            fetchNewMessages();
            checkTicketStatus();
        }
    }, 3500);

    // Send message
    $("#send-btn").click(sendMessage);
    $("#message-input").keypress(e => {
        if (e.which === 13) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Restore theme if saved
    const savedTheme = localStorage.getItem("theme");
    if (savedTheme) document.documentElement.setAttribute("data-theme", savedTheme);

    // Toggle theme
    $("#theme-toggle").click(toggleTheme);

    // Scroll visibility
    $("#chat-messages").on("scroll", updateScrollButton);
});

/* ============================================================
THEME TOGGLE (WITH LOCALSTORAGE)
============================================================ */
function toggleTheme() {
    const root = document.documentElement;
    const dark = root.getAttribute("data-theme") === "dark";
    const newTheme = dark ? "light" : "dark";
    root.setAttribute("data-theme", newTheme);
    localStorage.setItem("theme", newTheme);
}

/* ============================================================
INSERT SUGGESTION BUBBLE
============================================================ */
function insertSuggestionBubble() {
    if (suggestionShown) return;
    suggestionShown = true;

    $("#chat-messages").append(`
        <div class="message received system-suggest" data-msg-id="suggest-1">
            <div class="message-avatar"><img src="/SKYTRUFIBER.png"></div>
            <div class="message-content">
                <div class="message-bubble">
                    <strong>SkyTru Smart Assistant</strong><br>
                    Please select an option below:
                    <div class="suggest-buttons">
                        <button class="suggest-btn">I am experiencing no internet.</button>
                        <button class="suggest-btn">My connection is slow.</button>
                        <button class="suggest-btn">My router is blinking red.</button>
                        <button class="suggest-btn">I already restarted my router.</button>
                        <button class="suggest-btn">Please assist me. Thank you.</button>
                    </div>
                </div>
                <div class="message-time">Now</div>
            </div>
        </div>
    `);

    scrollToBottom();
}

/* ============================================================
SUGGESTION BUTTON HANDLER
============================================================ */
$(document).on("click", ".suggest-btn", function () {

    const text = $(this).text();
    $(".system-suggest").remove();

    const tempId = appendClientBubble(text);

    $.post("/SKYTRUFIBER/chat/send_message_client.php", {
        ticket: ticketId,
        message: text
    }).done(() => {
        $(`.message[data-msg-id='${tempId}']`).remove();
        fetchNewMessages();
    });
});

/* ============================================================
CHECK TICKET STATUS (FULLY FIXED + CACHE-PROOF)
============================================================ */
function checkTicketStatus() {

    $.post("/SKYTRUFIBER/chat/get_ticket_status.php", {
        ticket: ticketId,
        nocache: Date.now() // avoid caching
    })
    .done(raw => {
        if (!raw) return;
        const status = raw.trim().toLowerCase(); // normalize always
        applyTicketStatus(status);
    });
}

/* ============================================================
APPLY TICKET STATUS (RELIABLE STATUS CONTROL)
============================================================ */
function applyTicketStatus(status) {

    // Update header status text
    $("#ticket-status-label").text("Status: " + status.toUpperCase());

    const input = $("#message-input");
    const sendBtn = $("#send-btn");

    if (status === "resolved") {

        input.prop("disabled", true);
        sendBtn.prop("disabled", true);
        $(".system-suggest").remove();

        $("#chat-messages").html(`
            <div class="system-message">
                Your ticket has been marked as 
                <strong style="color:green;">RESOLVED</strong>.<br><br>
                Thank you for contacting SkyTruFiber Support!<br><br>
                <span style="font-size:13px;color:#888;">
                    You will be logged out in 5 seconds...
                </span>
            </div>
        `);

        setTimeout(() => {
            window.location.href = "/SKYTRUFIBER/chat/logout.php";
        }, 5000);

        return;
    }

    input.prop("disabled", false);
    sendBtn.prop("disabled", false);
}




/* ============================================================
SEND MESSAGE
============================================================ */
function sendMessage() {

    const msg = $("#message-input").val().trim();
    if (!msg) return;

    $(".system-suggest").remove();

    const tempId = appendClientBubble(msg);
    $("#message-input").val("");

    $.post("/SKYTRUFIBER/chat/send_message_client.php", {
        ticket: ticketId,
        message: msg
    }).done(raw => {

        let res = {};
        try { res = JSON.parse(raw); } catch {}

        // remove temp immediately
        $(`.message[data-msg-id='${tempId}']`).remove();

        // now fetch actual DB message
        fetchNewMessages();

        if (res.first_message === true) {
            insertSuggestionBubble();
        }

    });
}



function appendClientBubble(msg) {
    const id = "temp-" + Date.now();

    $("#chat-messages").append(`
        <div class="message sent temp-msg" data-msg-id="${id}">
            <div class="message-content">
                <div class="message-bubble">${msg}</div>
                <div class="message-time">Sending...</div>
            </div>
        </div>
    `);

    scrollToBottom();
    return id;
}


/* ============================================================
LOAD MESSAGES (FULL)
============================================================ */
function loadMessages(scrollBottom = false) {
    $.post("/SKYTRUFIBER/chat/load_messages_client.php", { ticket: ticketId })
    .done(html => {

        if (html.trim() === "FORCE_LOGOUT") {
            window.location.href = "/SKYTRUFIBER/chat/logout.php";
            return;
        }

        $("#chat-messages").html(html);

        bindActionToolbar();

        if (scrollBottom) scrollToBottom();
    });
}

/* ============================================================
FETCH NEW MESSAGES
============================================================ */
function fetchNewMessages() {

    $.post("/SKYTRUFIBER/chat/load_messages_client.php", { ticket: ticketId })
    .done(html => {

        if (html.trim() === "FORCE_LOGOUT") {
            window.location.href = "/SKYTRUFIBER/chat/logout.php";
            return;
        }

        // 🔥 FIX #1 — always remove temp messages BEFORE updating the chat
        $("#chat-messages .temp-msg").remove();

        const temp = $("<div>").html(html);

        temp.find(".message").each(function () {
            const id = $(this).data("msg-id");

            // Only append message if it doesn't exist yet
            if (!$(`.message[data-msg-id='${id}']`).length) {
                $("#chat-messages").append($(this));
            }
        });

        bindActionToolbar();
        updateScrollButton();
    });
}


/* ============================================================
SCROLL
============================================================ */
function scrollToBottom() {
    const m = $("#chat-messages")[0];
    m.scrollTo({ top: m.scrollHeight, behavior: "smooth" });
}

function updateScrollButton() {
    const m = $("#chat-messages");
    const btn = $("#scroll-bottom-btn");

    const nearBottom = m.scrollTop() + m.innerHeight() >= m[0].scrollHeight - 60;
    if (nearBottom) btn.removeClass("show");
    else btn.addClass("show");
}

$(document).on("click", "#scroll-bottom-btn", scrollToBottom);

/* ============================================================
ACTION POPUP
============================================================ */
function bindActionToolbar() {
    $(".more-btn").off("click").on("click", function (e) {
        e.stopPropagation();
        openPopup($(this).data("id"), this);
    });
}

function openPopup(id, anchor) {
    const popup = $("#msg-action-popup");
    const modal = $(".chat-modal");

    popup.data("msgId", id).show();

    const a = $(anchor).offset();
    const m = modal.offset();

    popup.css({
        top: a.top - m.top + 30,
        left: Math.max(5, a.left - m.left - popup.outerWidth() + 22)
    });

    activePopup = popup;
}

function closePopup() {
    $("#msg-action-popup").hide();
    activePopup = null;
}

/* ============================================================
EDIT MESSAGE
============================================================ */
$(document).on("click", ".action-edit", function () {
    const id = $("#msg-action-popup").data("msgId");
    startEdit(id);
    closePopup();
});

function startEdit(id) {
    editing = true;

    const bubble = $(`.message[data-msg-id='${id}'] .message-bubble`);
    const old = bubble.text();

    bubble.html(`
        <textarea class="edit-textarea">${old}</textarea>
        <div class="edit-actions">
            <button class="edit-save" data-id="${id}">Save</button>
            <button class="edit-cancel">Cancel</button>
        </div>
    `);
}

$(document).on("click", ".edit-save", function () {
    const id = $(this).data("id");
    const text = $(this).closest(".message-bubble").find("textarea").val().trim();

    $.post("/SKYTRUFIBER/chat/edit_message_client.php", {
        id,
        ticket: ticketId,
        message: text
    }).done(() => {
        editing = false;
        loadMessages(true);
    });
});

$(document).on("click", ".edit-cancel", () => {
    editing = false;
    loadMessages(false);
});

/* ============================================================
LOGOUT BUTTON
============================================================ */
$(document).on("click", "#logout-btn", () => {
    if (confirm("Are you sure you want to log out?")) {
        window.location.href = "/SKYTRUFIBER/chat/logout.php";
    }
});
