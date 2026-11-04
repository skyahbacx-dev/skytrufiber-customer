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



function escapeChatText(value) { return $("<div>").text(String(value)).html(); }

function appendClientBubble(msg) {
    const id = "temp-" + Date.now();

    $("#chat-messages").append(`
        <div class="message sent temp-msg" data-msg-id="${id}">
            <div class="message-content">
                <div class="message-bubble">${escapeChatText(msg).replace(/\n/g, "<br>")}</div>
                <div class="message-time">Sending...</div>
            </div>
        </div>
    `);

    scrollToBottom();
    return id;
}



/* Friendly first-contact state when a ticket has no messages yet. */
function emptyChatState() {
    return `
        <div class="empty-chat-state" role="status">
            <div class="empty-chat-icon"><i class="fa-regular fa-comments"></i></div>
            <h3>Welcome to SkyTruFiber Support</h3>
            <p>Send us a message about your connection or service concern. Our support team will assist you as soon as possible.</p>
        </div>
    `;
}

/* ============================================================
LOAD MESSAGES (FULL)
============================================================ */
function showChatLoadError(message) {
    $("#chat-messages").html(
        '<div class="system-message" role="alert">' +
        '<strong>Unable to load this conversation.</strong><br>' +
        message +
        '<br><small>Please refresh. If this continues, contact support.</small></div>'
    );
}

function loadMessages(scrollBottom = false) {
    $.ajax({
        url: "/SKYTRUFIBER/chat/load_messages_client.php",
        method: "POST",
        data: { ticket: ticketId },
        cache: false,
        dataType: "html"
    })
    .done(html => {
        const response = (html || "").trim();

        if (response === "FORCE_LOGOUT") {
            window.location.href = "/SKYTRUFIBER/chat/logout.php";
            return;
        }
        if (response === "CHAT_SESSION_MISSING") {
            showChatLoadError("Your support session has expired. Please log in again.");
            return;
        }
        if (response === "CHAT_TICKET_MISMATCH" || response === "CHAT_TICKET_NOT_FOUND" || response === "CHAT_INVALID_TICKET") {
            showChatLoadError("This ticket could not be matched to your account. Please log out and log in again.");
            return;
        }

        const visibleHtml = filterHiddenMessages(html);
        $("#chat-messages").html(visibleHtml ? visibleHtml : emptyChatState());
        bindActionToolbar();
        if (scrollBottom) scrollToBottom();
    })
    .fail(xhr => {
        const code = (xhr.responseText || "").trim();
        if (code === "CHAT_SESSION_MISSING") {
            showChatLoadError("Your support session has expired. Please log in again.");
        } else if (code === "CHAT_TICKET_MISMATCH" || code === "CHAT_TICKET_NOT_FOUND") {
            showChatLoadError("This ticket could not be matched to your account. Please log out and log in again.");
        } else {
            showChatLoadError("The message service returned an error (" + xhr.status + ").");
        }
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
        hideLocallyDeletedMessages(temp);

        if (!temp.find(".message").length && !$("#chat-messages .message").length) {
            $("#chat-messages").html(emptyChatState());
        }

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
MESSAGE ACTION MENU — edit, unsend, delete for me
============================================================ */
function hiddenMessagesKey() {
    return "skytrufiber_hidden_messages_" + String(ticketId || "current");
}

function getLocallyHiddenIds() {
    try {
        const value = JSON.parse(localStorage.getItem(hiddenMessagesKey()) || "[]");
        return Array.isArray(value) ? value.map(String) : [];
    } catch (_) { return []; }
}

function hideLocallyDeletedMessages(container) {
    const hidden = new Set(getLocallyHiddenIds());
    container.find(".message[data-msg-id]").each(function () {
        if (hidden.has(String($(this).attr("data-msg-id")))) $(this).remove();
    });
}

function filterHiddenMessages(html) {
    const wrapper = $("<div>").html(html || "");
    hideLocallyDeletedMessages(wrapper);
    return wrapper.html();
}

function bindActionToolbar() {
    $(".more-btn").off("click").on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        const id = $(this).attr("data-id");
        if (id) openPopup(id, this);
    });
}

function openPopup(id, anchor) {
    const popup = $("#msg-action-popup");
    const modal = $(".chat-modal");
    if (!popup.length || !modal.length) return;

    popup.data("msgId", String(id)).css({display:"block",visibility:"hidden"});
    const a = anchor.getBoundingClientRect();
    const m = modal[0].getBoundingClientRect();
    const width = popup.outerWidth();
    const height = popup.outerHeight();
    let left = a.right - m.left - width;
    let top = a.bottom - m.top + 7;

    left = Math.max(8, Math.min(left, m.width - width - 8));
    if (top + height > m.height - 12) top = Math.max(8, a.top - m.top - height - 7);

    popup.css({left:left + "px",top:top + "px",visibility:"visible"});
    activePopup = popup;
}

function closePopup() {
    $("#msg-action-popup").hide().css("visibility","visible").removeData("msgId");
    activePopup = null;
}

$(document).on("click", function (e) {
    if (activePopup && !$(e.target).closest("#msg-action-popup,.more-btn").length) closePopup();
});
$(document).on("keydown", function (e) {
    if (e.key === "Escape" && activePopup) closePopup();
});

$(document).on("click", ".action-cancel", closePopup);

/* EDIT */
$(document).on("click", ".action-edit", function () {
    const id = $("#msg-action-popup").data("msgId");
    closePopup();
    if (id) startEdit(id);
});

function startEdit(id) {
    const bubble = $(`.message[data-msg-id='${id}'] .message-bubble`);
    if (!bubble.length || bubble.hasClass("removed-text")) return;

    editing = true;
    const old = bubble.text();
    const editor = $("<textarea>", {
        class:"edit-textarea", maxlength:2000, "aria-label":"Edit your message"
    }).val(old);
    const actions = $("<div>", {class:"edit-actions"})
        .append($("<button>", {class:"edit-save", "data-id":id, type:"button", text:"Save"}))
        .append($("<button>", {class:"edit-cancel", type:"button", text:"Cancel"}));
    bubble.empty().append(editor, actions);
    editor.trigger("focus");
}

$(document).on("click", ".edit-save", function () {
    const button = $(this);
    const id = button.attr("data-id");
    const text = button.closest(".message-bubble").find("textarea").val().trim();
    if (!text) { alert("Message cannot be empty."); return; }
    if (text.length > 2000) { alert("Messages can contain up to 2,000 characters."); return; }

    button.prop("disabled", true).text("Saving…");
    $.ajax({
        url:"/SKYTRUFIBER/chat/edit_message_client.php",
        method:"POST", dataType:"json",
        data:{id:id,ticket:ticketId,message:text}
    }).done(res => {
        if (res && res.status === "success") {
            editing = false;
            loadMessages(false);
        } else {
            alert((res && res.msg) || "The message could not be edited.");
            button.prop("disabled",false).text("Save");
        }
    }).fail(xhr => {
        alert((xhr.responseJSON && xhr.responseJSON.msg) || "Unable to save this message. Please try again.");
        button.prop("disabled",false).text("Save");
    });
});

$(document).on("click", ".edit-cancel", () => {
    editing = false;
    loadMessages(false);
});

/* UNSEND — removes the message for everyone using the existing soft-delete field. */
$(document).on("click", ".action-unsend", function () {
    const id = $("#msg-action-popup").data("msgId");
    closePopup();
    if (!id) return;
    if (!confirm("Unsend this message? It will be marked as removed in this conversation for everyone.")) return;

    $.ajax({
        url:"/SKYTRUFIBER/chat/delete_message_client.php",
        method:"POST", dataType:"json",
        data:{id:id,ticket:ticketId}
    }).done(res => {
        if (res && (res.status === "ok" || res.status === "success")) {
            $(`.message[data-msg-id='${id}']`).remove();
            fetchNewMessages();
        } else {
            alert((res && res.msg) || "This message could not be unsent.");
        }
    }).fail(xhr => {
        alert((xhr.responseJSON && xhr.responseJSON.msg) || "Unable to unsend this message. Please try again.");
    });
});

/* DELETE FOR ME — hides only in this browser; the CSR and stored conversation are unchanged. */
$(document).on("click", ".action-delete", function () {
    const id = $("#msg-action-popup").data("msgId");
    closePopup();
    if (!id) return;
    if (!confirm("Delete this message from your view on this device? The support team and conversation record will remain unchanged.")) return;

    try {
        const hidden = getLocallyHiddenIds();
        if (!hidden.includes(String(id))) hidden.push(String(id));
        localStorage.setItem(hiddenMessagesKey(), JSON.stringify(hidden));
        $(`.message[data-msg-id='${id}']`).remove();
        if (!$("#chat-messages .message").length) $("#chat-messages").html(emptyChatState());
    } catch (_) {
        alert("Your browser could not save this view preference. Please check your browser storage settings.");
    }
});

/* ============================================================
LOGOUT BUTTON
============================================================ */
$(document).on("click", "#logout-btn", () => {
    if (confirm("Are you sure you want to log out?")) {
        window.location.href = "/SKYTRUFIBER/chat/logout.php";
    }
});
