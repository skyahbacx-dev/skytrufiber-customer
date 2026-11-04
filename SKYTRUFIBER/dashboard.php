<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../db_connect.php';

$clientId = $_SESSION['client_id'] ?? $_SESSION['user'] ?? null;
if (!$clientId) {
    header('Location: /fiber');
    exit;
}

try {
    $userStmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE id = :id LIMIT 1");
    $userStmt->execute([':id' => $clientId]);
    $customer = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer) {
        $_SESSION = [];
        session_destroy();
        header('Location: /fiber');
        exit;
    }

    $ticketStmt = $conn->prepare("
        SELECT id, status, created_at
        FROM tickets
        WHERE client_id = :id
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $ticketStmt->execute([':id' => $clientId]);
    $latestTicket = $ticketStmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (PDOException $e) {
    error_log('Customer dashboard load failed: ' . $e->getMessage());
    http_response_code(503);
    exit('We could not load your dashboard right now. Please try again shortly.');
}

$name = htmlspecialchars($customer['full_name'] ?? 'Customer', ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($customer['email'] ?? '', ENT_QUOTES, 'UTF-8');
$ticketId = $latestTicket ? (int)$latestTicket['id'] : 0;
$status = $latestTicket ? ucfirst(strtolower((string)$latestTicket['status'])) : 'No support request yet';
$statusClass = $latestTicket && strtolower((string)$latestTicket['status']) === 'resolved' ? 'resolved' : 'pending';
$chatUrl = $ticketId ? '/fiber/chat?ticket=' . $ticketId : '/fiber/chat';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#082d58">
<link rel="icon" type="image/png" href="/SKYTRUFIBER.png">
<link rel="apple-touch-icon" href="/SKYTRUFIBER.png">
<title>My Dashboard | SkyTruFiber</title>
<style>
:root{--navy:#082d58;--blue:#0879bd;--ink:#17324d;--muted:#64788d;--line:#e1eaf2;--soft:#f3f8fc}
*{box-sizing:border-box}
body{margin:0;background:#f3f7fb;color:var(--ink);font-family:Inter,system-ui,-apple-system,"Segoe UI",Arial,sans-serif}
a{color:inherit}
.topbar{background:linear-gradient(110deg,#082d58,#075b91);color:#fff}
.top-inner{width:min(1120px,calc(100% - 36px));margin:auto;min-height:76px;display:flex;align-items:center;justify-content:space-between;gap:18px}
.brand{display:flex;align-items:center;gap:12px;font-weight:800;letter-spacing:.01em}
.brand img{width:44px;height:44px;object-fit:contain;border-radius:50%;background:#fff}
.brand small{display:block;font-size:11px;font-weight:500;opacity:.78;letter-spacing:.08em;text-transform:uppercase}
.logout{color:#fff;text-decoration:none;border:1px solid #ffffff70;border-radius:999px;padding:9px 15px;font-size:13px;font-weight:700}
main{width:min(1120px,calc(100% - 36px));margin:38px auto 56px}
.welcome{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;margin-bottom:26px}
.eyebrow{font-size:12px;color:var(--blue);font-weight:800;letter-spacing:.12em;text-transform:uppercase}
h1{font-size:clamp(27px,4vw,38px);line-height:1.15;margin:7px 0 8px;color:var(--navy)}
.sub{color:var(--muted);margin:0;line-height:1.6}
.account-chip{background:#fff;border:1px solid var(--line);border-radius:14px;padding:13px 16px;min-width:190px}
.account-chip span{display:block;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.08em}
.account-chip strong{display:block;margin-top:4px;font-size:14px;overflow-wrap:anywhere}
.section-title{font-size:18px;color:var(--navy);margin:0 0 13px}
.actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}
.action{display:flex;gap:16px;align-items:flex-start;text-decoration:none;background:#fff;border:1px solid var(--line);border-radius:18px;padding:22px;min-height:140px;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
.action:hover{transform:translateY(-2px);border-color:#9bcce9;box-shadow:0 12px 28px #153b5b12}
.icon{width:48px;height:48px;flex:0 0 48px;border-radius:14px;background:#eaf5fc;color:#0879bd;display:grid;place-items:center}
.icon svg{width:24px;height:24px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.action h3{margin:2px 0 6px;color:var(--navy);font-size:17px}
.action p{margin:0;color:var(--muted);font-size:13px;line-height:1.55}
.arrow{margin-left:auto;color:#8ba3b7;font-size:22px}
.lower{display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:20px}
.panel{background:#fff;border:1px solid var(--line);border-radius:18px;padding:22px}
.panel h2{margin:0 0 14px;font-size:17px;color:var(--navy)}
.ticket-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 0;border-top:1px solid var(--line);font-size:13px}
.ticket-row:first-of-type{border-top:0}
.ticket-row span{color:var(--muted)}
.pill{display:inline-block;border-radius:999px;background:#fff4dc;color:#8a5b00;padding:5px 10px;font-size:11px;font-weight:800}
.pill.resolved{background:#e7f7ee;color:#187447}
.guides details{border-top:1px solid var(--line);padding:12px 0}
.guides details:first-of-type{border-top:0}
.guides summary{cursor:pointer;font-weight:700;font-size:13px;color:var(--ink)}
.guides p{font-size:13px;line-height:1.6;color:var(--muted);margin:9px 0 0}
.support-note{margin-top:20px;border-radius:16px;background:#e9f5fc;padding:17px 20px;display:flex;justify-content:space-between;align-items:center;gap:15px}
.support-note strong{display:block;color:var(--navy);margin-bottom:4px}
.support-note p{margin:0;color:#506b82;font-size:13px;line-height:1.5}
.primary{display:inline-block;text-decoration:none;background:var(--blue);color:#fff;padding:11px 16px;border-radius:10px;font-weight:750;font-size:13px;white-space:nowrap}
footer{text-align:center;color:#8a9bad;font-size:12px;padding:0 18px 26px}
@media(max-width:700px){.top-inner{min-height:68px}.brand img{width:38px;height:38px}main{margin:26px auto 38px}.welcome{display:block}.account-chip{margin-top:17px}.actions{grid-template-columns:1fr;gap:11px}.action{min-height:0;padding:18px}.lower{grid-template-columns:1fr}.support-note{align-items:flex-start;flex-direction:column}.primary{width:100%;text-align:center}}
@media(prefers-reduced-motion:reduce){.action{transition:none}}

/* Modern customer dashboard refresh */
:root{--navy:#071b35;--blue:#1677e8;--mint:#48d6a0;--line:#e4ebf4;--muted:#718198}
body{background:radial-gradient(ellipse at 8% 0%,rgba(22,119,232,.11),transparent 32%),radial-gradient(ellipse at 92% 18%,rgba(72,214,160,.10),transparent 28%),#f5f8fc;color:#152b45}
.topbar{background:linear-gradient(112deg,#061a34 0%,#0a3261 62%,#07568b 100%);position:relative;overflow:hidden}
.topbar:after{content:"";position:absolute;width:420px;height:220px;right:-100px;top:-130px;border:1px solid #ffffff20;border-radius:50%;box-shadow:0 0 0 28px #ffffff08,0 0 0 58px #ffffff06;pointer-events:none}
.top-inner{position:relative;z-index:1}
main{margin-top:42px}
.welcome{align-items:stretch;margin-bottom:30px}
.welcome>div:first-child{align-self:center}
h1{letter-spacing:-1.4px;font-weight:850}
.eyebrow{color:#1677e8}
.welcome-side{display:flex;gap:12px;align-items:stretch}
.clock-card{min-width:210px;padding:15px 18px;border-radius:18px;color:#fff;background:linear-gradient(145deg,#0b2b50,#104d7a);box-shadow:0 14px 32px #0c31521c;display:flex;flex-direction:column;justify-content:center}
.clock-label{font-size:9px;letter-spacing:.13em;font-weight:800;color:#9dd8ff}
.clock-card strong{font-size:27px;letter-spacing:-1px;line-height:1.25;margin:5px 0;font-variant-numeric:tabular-nums}
.clock-card span:last-child{font-size:11px;color:#d1e6f6}
.account-chip{min-width:175px;border-radius:18px;box-shadow:0 12px 28px #12385c0a;display:flex;flex-direction:column;justify-content:center}
.section-title{font-size:20px;letter-spacing:-.45px}
.actions{gap:16px}
.action{position:relative;min-height:150px;padding:23px;border-radius:20px;box-shadow:0 8px 24px #17395c08;transition:transform .28s cubic-bezier(.2,.8,.2,1),box-shadow .28s,border-color .28s}
.action:before{content:"";position:absolute;inset:0 auto 0 0;width:4px;border-radius:20px 0 0 20px;background:linear-gradient(#1677e8,#48d6a0);opacity:0;transition:opacity .2s}
.action:hover{transform:translateY(-5px);border-color:#b8d8f5;box-shadow:0 20px 42px #12385c16}
.action:hover:before{opacity:1}
.icon{border-radius:16px;background:linear-gradient(145deg,#e9f4ff,#e7faf3);color:#126fc8}
.action h3{letter-spacing:-.3px}
.panel{border-radius:20px;box-shadow:0 8px 24px #17395c08;padding:24px}
.panel h2{letter-spacing:-.35px}
.support-note{border:1px solid #d8eaf8;background:linear-gradient(110deg,#eaf6ff,#ecfbf5);border-radius:20px;padding:22px}
.primary{border-radius:12px;background:linear-gradient(135deg,#1677e8,#0756c9);box-shadow:0 7px 16px #1677e82a;transition:transform .2s,box-shadow .2s}
.primary:hover{transform:translateY(-2px);box-shadow:0 11px 22px #1677e83b}
.logout{backdrop-filter:blur(8px);transition:background .2s}
.logout:hover{background:#ffffff20}
@media(max-width:760px){.welcome-side{margin-top:18px;display:grid;grid-template-columns:1fr 1fr}.clock-card{min-width:0}.account-chip{min-width:0}.clock-card strong{font-size:23px}}
@media(max-width:430px){.welcome-side{grid-template-columns:1fr}.clock-card{min-height:100px}.account-chip{min-height:85px}}
@media(prefers-reduced-motion:reduce){.action,.primary{transition:none}}

/* Account and latest-concern dialogs */
.welcome-side .account-chip{display:none}
.dashboard-secondary{margin-top:20px}
.action{cursor:pointer}
.portal-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(3,15,31,.62);backdrop-filter:blur(9px);opacity:0}
.portal-modal.is-open{display:flex;opacity:1}
.portal-modal-card{position:relative;width:min(480px,100%);padding:30px;border:1px solid #ffffffaa;border-radius:24px;background:#fff;box-shadow:0 30px 90px #00152e55;transform:translateY(14px) scale(.98);transition:transform .22s ease}
.portal-modal.is-open .portal-modal-card{transform:translateY(0) scale(1)}
.modal-close{position:absolute;right:16px;top:14px;width:36px;height:36px;border:0;border-radius:50%;background:#eef4fa;color:#34516c;font-size:24px;line-height:1;cursor:pointer}
.modal-kicker{font-size:10px;font-weight:850;letter-spacing:.16em;color:#1680cf}
.portal-modal-card h2{margin:7px 40px 5px 0;color:#092c52;font-size:25px;letter-spacing:-.7px}
.modal-description{margin:0 0 18px;color:#718198;font-size:13px;line-height:1.55}
.portal-modal-card .ticket-row{padding:15px 0}
.modal-footnote{margin-top:16px;padding:13px 14px;border-radius:12px;background:#edf7ff;color:#456987;font-size:12px;line-height:1.55}
.modal-primary{display:block;text-align:center;margin-top:18px}
.modal-empty{padding:14px 0}
.modal-quick-links{display:flex;gap:9px;flex-wrap:wrap;margin-top:12px}
.modal-quick-links button{border:1px solid #d5e5f3;border-radius:11px;background:#f2f8fd;color:#1268b7;padding:10px 13px;font-weight:750;cursor:pointer}
body.modal-open{overflow:hidden}
@media(max-width:520px){.portal-modal{padding:12px;align-items:flex-end}.portal-modal-card{padding:25px 21px calc(24px + env(safe-area-inset-bottom));border-radius:24px 24px 18px 18px}.modal-quick-links{display:grid;grid-template-columns:1fr 1fr}.modal-quick-links button{font-size:12px}}
@media(prefers-reduced-motion:reduce){.portal-modal-card{transition:none}}

/* SkyTruFiber customer portal — visual refresh (presentation only) */
:root{
  --navy:#071b35;
  --blue:#1677e8;
  --mint:#48d6a0;
  --line:#e4ebf4;
  --muted:#718198;
}
body{
  background:
    radial-gradient(ellipse at 8% 0%,rgba(22,119,232,.13),transparent 32%),
    radial-gradient(ellipse at 92% 18%,rgba(72,214,160,.12),transparent 28%),
    #f4f7fc;
}
.topbar{
  background:linear-gradient(112deg,#06172f 0%,#082d58 55%,#0876a7 100%);
  box-shadow:0 8px 28px rgba(5,28,55,.16);
}
.brand{letter-spacing:-.2px}
.brand img{box-shadow:0 4px 14px #001a3433}
.logout{
  padding:10px 17px;
  border-radius:12px;
  background:#ffffff0d;
  transition:background .2s ease,transform .2s ease;
}
.logout:hover{background:#ffffff22;transform:translateY(-1px)}
main{margin-top:34px}
.welcome{
  position:relative;
  isolation:isolate;
  overflow:hidden;
  align-items:center;
  min-height:220px;
  margin-bottom:32px;
  padding:32px 34px;
  border:1px solid #ffffff18;
  border-radius:26px;
  color:#fff;
  background:
    radial-gradient(ellipse at 82% 22%,rgba(72,214,160,.24),transparent 28%),
    radial-gradient(ellipse at 96% 100%,rgba(22,119,232,.34),transparent 38%),
    linear-gradient(115deg,#071b35 0%,#0b3765 58%,#075b83 100%);
  box-shadow:0 20px 48px rgba(9,43,79,.17);
}
.welcome:after{
  content:"";
  position:absolute;
  z-index:-1;
  width:330px;height:330px;
  right:7%;top:50%;
  transform:translateY(-50%);
  border:1px solid #ffffff20;
  border-radius:50%;
  box-shadow:0 0 0 24px #ffffff08,0 0 0 50px #ffffff06,0 0 0 78px #ffffff04;
  pointer-events:none;
}
.welcome>div:first-child{align-self:center;max-width:590px}
.eyebrow{color:#78e8c2;font-size:11px;letter-spacing:.16em}
h1{
  color:#fff;
  font-size:clamp(30px,4vw,43px);
  letter-spacing:-1.5px;
  line-height:1.08;
  margin:10px 0 11px;
}
.welcome .sub{color:#d1e4f5;max-width:480px;font-size:14px}
.welcome-side{position:relative;z-index:1}
.clock-card{
  min-width:205px;
  min-height:118px;
  padding:19px 21px;
  border:1px solid #ffffff24;
  border-radius:19px;
  background:linear-gradient(145deg,#ffffff1c,#ffffff0b);
  box-shadow:0 12px 32px #00172c24;
  backdrop-filter:blur(12px);
}
.clock-label{color:#9fe9d1;font-size:9px;letter-spacing:.15em}
.clock-card strong{font-size:29px;color:#fff}
.clock-card span:last-child{color:#d4e7f6}
.section-title{
  display:flex;
  align-items:center;
  gap:11px;
  margin-bottom:16px;
  font-size:20px;
  letter-spacing:-.6px;
}
.section-title:before{
  content:"";
  width:5px;height:22px;
  border-radius:8px;
  background:linear-gradient(#1677e8,#48d6a0);
}
.actions{gap:17px}
.action{
  min-height:158px;
  padding:24px;
  border:1px solid #e3ebf4;
  border-radius:21px;
  background:linear-gradient(145deg,#fff,#fbfdff);
  box-shadow:0 9px 25px rgba(16,48,82,.055);
  transition:transform .25s cubic-bezier(.2,.8,.2,1),box-shadow .25s,border-color .25s;
}
.action:before{opacity:.0}
.action:hover{
  transform:translateY(-6px);
  border-color:#b7d8f4;
  box-shadow:0 20px 42px rgba(16,48,82,.12);
}
.action:before{opacity:0}
.action:hover:before{opacity:1}
.icon{
  width:54px;height:54px;flex-basis:54px;
  border-radius:17px;
  background:linear-gradient(145deg,#e8f3ff,#e8fbf4);
  color:#126fc8;
  box-shadow:inset 0 1px #fff,0 6px 14px #0c477b0b;
}
.action:nth-child(2) .icon{background:linear-gradient(145deg,#e6f4ff,#e7f8ff);color:#0879bd}
.action:nth-child(3) .icon{background:linear-gradient(145deg,#eaf9f2,#e4fbef);color:#16885d}
.action:nth-child(4) .icon{background:linear-gradient(145deg,#f0edff,#e9f1ff);color:#6653bd}
.action h3{font-size:18px;letter-spacing:-.45px}
.action p{font-size:13px;line-height:1.65}
.arrow{font-size:26px;transition:transform .2s,color .2s}
.action:hover .arrow{transform:translateX(4px);color:#1677e8}
.lower{gap:17px;margin-top:22px}
.panel{
  padding:25px;
  border:1px solid #e4ebf4;
  border-radius:21px;
  background:linear-gradient(145deg,#fff,#fbfdff);
  box-shadow:0 9px 25px rgba(16,48,82,.045);
}
.panel h2{font-size:18px;letter-spacing:-.45px}
.support-note{
  position:relative;
  overflow:hidden;
  padding:23px 25px;
  border:1px solid #d7eaf6;
  border-radius:21px;
  background:
    radial-gradient(ellipse at 90% 0%,#d9f9ec 0%,transparent 38%),
    linear-gradient(110deg,#eaf5ff,#f0fcf7);
}
.support-note:after{
  content:"";
  position:absolute;
  right:180px;top:-70px;
  width:180px;height:180px;
  border:1px solid #1686c21a;border-radius:50%;
  box-shadow:0 0 0 18px #1686c20a,0 0 0 38px #1686c207;
  pointer-events:none;
}
.primary{
  position:relative;
  z-index:1;
  padding:12px 18px;
  border-radius:12px;
  background:linear-gradient(135deg,#1677e8,#0756c9);
  box-shadow:0 8px 18px #1677e82c;
  transition:transform .2s ease,box-shadow .2s ease;
}
.primary:hover{transform:translateY(-2px);box-shadow:0 12px 24px #1677e83b}
.guides details{padding:14px 0}
.guides summary{font-size:14px}
.guides summary:hover{color:#1677e8}
footer{padding-top:4px}
@media(max-width:760px){
  main{margin-top:25px}
  .welcome{padding:25px 23px;min-height:0;border-radius:22px}
  .welcome:after{right:-100px;opacity:.7}
  .welcome-side{grid-template-columns:1fr;margin-top:20px}
  .clock-card{min-height:105px}
  .actions{gap:12px}
  .action{min-height:0;padding:19px;border-radius:18px}
  .panel{padding:21px}
}
@media(max-width:430px){
  .welcome{padding:23px 19px}
  h1{font-size:31px}
  .action{gap:13px}
  .icon{width:47px;height:47px;flex-basis:47px}
}
@media(prefers-reduced-motion:reduce){
  .action,.logout,.primary,.arrow{transition:none}
  .action:hover,.primary:hover,.logout:hover{transform:none}
}


/* Simplified dashboard actions and in-page Help Center navigation */
.quick-access-wrap{position:relative;margin-top:13px}
.quick-access-btn{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;border:1px solid #ffffff45;border-radius:11px;padding:9px 12px;background:#ffffff18;color:#fff;font-family:inherit;font-size:12px;font-weight:700;cursor:pointer;transition:background .18s ease}
.quick-access-btn:hover,.quick-access-btn[aria-expanded="true"]{background:#ffffff2b}
.quick-access-btn span{font-size:17px;line-height:10px}
.quick-access-menu{position:absolute;z-index:30;top:calc(100% + 7px);left:0;right:0;padding:6px;border:1px solid #ffffff30;border-radius:12px;background:#fff;box-shadow:0 14px 32px #00172c35}
.quick-access-menu[hidden]{display:none}
.quick-access-menu button{display:block;width:100%;padding:10px 11px;border:0;border-radius:8px;background:transparent;color:#17324d;text-align:left;font-family:inherit;font-size:12px;font-weight:650;cursor:pointer}
.quick-access-menu button:hover{background:#edf6ff;color:#126fc8}
.help-view{animation:help-view-in .22s ease both}
.help-view[hidden]{display:none}
.breadcrumbs{display:flex;align-items:center;gap:10px;margin:2px 0 28px;color:#718198;font-size:13px}
.breadcrumbs a{color:#1677e8;text-decoration:none;font-weight:750}
.breadcrumbs a:hover{text-decoration:underline}
.breadcrumbs span[aria-hidden="true"]{color:#9aabba;font-size:20px}
.breadcrumbs [aria-current="page"]{color:#17324d;font-weight:750}
.help-heading{margin-bottom:22px}
.help-heading h1{color:#071b35;margin:7px 0 8px}
.help-view .panel{margin-top:0}
.help-contact{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-top:18px;padding:20px 22px;border:1px solid #d7eaf6;border-radius:18px;background:linear-gradient(110deg,#eaf5ff,#f0fcf7)}
.help-contact strong{display:block;color:#082d58;margin-bottom:4px}
.help-contact p{margin:0;color:#506b82;font-size:13px}
@keyframes help-view-in{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:520px){.quick-access-wrap{margin-top:11px}.help-contact{align-items:stretch;flex-direction:column}.help-contact .primary{text-align:center}}
@media(prefers-reduced-motion:reduce){.help-view{animation:none}}

</style>
</head>
<body>
<header class="topbar"><div class="top-inner">
  <div class="brand"><img src="/SKYTRUFIBER.png" alt=""><div>SkyTruFiber<small>Customer Portal</small></div></div>
  <a class="logout" href="/fiber/logout">Log out</a>
</div></header>
<main>
  <div id="dashboard-home">
  <section class="welcome">
    <div><div class="eyebrow">Your account at a glance</div><h1>Good day, <?= $name ?>!</h1><p class="sub">Choose what you need below. We’ve kept everything in one simple place.</p></div>
    <div class="welcome-side"><div class="clock-card"><span class="clock-label">PHILIPPINE STANDARD TIME</span><strong id="live-clock">--:--:--</strong><span id="live-date">Loading date…</span><div class="quick-access-wrap"><button type="button" class="quick-access-btn" id="quick-access-toggle" aria-expanded="false">Quick Access <span>⌄</span></button><div class="quick-access-menu" id="quick-access-menu" hidden><button type="button" data-open-modal="account-modal">My Account</button><button type="button" data-open-modal="concerns-modal">My Latest Concerns</button><button type="button" data-open-help>Help Center</button></div></div></div></div>
  </section>

  <h2 class="section-title">What would you like to do?</h2>
  <section class="actions" aria-label="Main actions">
    <a class="action" href="#account-modal" data-open-modal="account-modal" aria-haspopup="dialog"><span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span><span><h3>My Account</h3><p>View your customer ID and registered account information.</p></span><span class="arrow">›</span></a>
    <a class="action" href="<?= htmlspecialchars($chatUrl, ENT_QUOTES, 'UTF-8') ?>"><span class="icon"><svg viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5 9 9 0 0 1-4-.9L3 21l1.9-5.5a9 9 0 0 1-.9-4A8.5 8.5 0 0 1 12.5 3H13a8.5 8.5 0 0 1 8 8z"/></svg></span><span><h3>Get Support</h3><p>Open your support conversation and chat with our team.</p></span><span class="arrow">›</span></a>
    <a class="action" href="#concerns-modal" data-open-modal="concerns-modal" aria-haspopup="dialog"><span class="icon"><svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg></span><span><h3>My Concerns</h3><p>See the latest status of your support request.</p></span><span class="arrow">›</span></a>
    <a class="action" href="#help-center" data-open-help><span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.2 1.8c-1.1 1-1.8 1.3-1.8 3M12 17h.01"/></svg></span><span><h3>Help Center</h3><p>Read quick answers and simple troubleshooting steps.</p></span><span class="arrow">›</span></a>
  </section>



  <div class="portal-modal" id="account-modal" role="dialog" aria-modal="true" aria-labelledby="account-modal-title" aria-hidden="true">
    <div class="portal-modal-card"><button class="modal-close" type="button" aria-label="Close">×</button>
      <div class="modal-kicker">YOUR PROFILE</div><h2 id="account-modal-title">My Account</h2><p class="modal-description">Your registered SkyTruFiber account information.</p>
      <div class="ticket-row"><span>Account name</span><strong><?= $name ?></strong></div>
      <div class="ticket-row"><span>Customer ID</span><strong>#<?= (int)$customer['id'] ?></strong></div>
      <div class="ticket-row"><span>Email address</span><strong><?= $email ?: 'Not provided' ?></strong></div>
      <div class="modal-footnote">For changes to your registered details, please contact our support team.</div>
    </div>
  </div>

  <div class="portal-modal" id="concerns-modal" role="dialog" aria-modal="true" aria-labelledby="concerns-modal-title" aria-hidden="true">
    <div class="portal-modal-card"><button class="modal-close" type="button" aria-label="Close">×</button>
      <div class="modal-kicker">SUPPORT OVERVIEW</div><h2 id="concerns-modal-title">My Latest Concerns</h2><p class="modal-description">Check your most recent support request and continue the conversation.</p>
      <?php if ($latestTicket): ?>
      <div class="ticket-row"><span>Request number</span><strong>#<?= $ticketId ?></strong></div>
      <div class="ticket-row"><span>Current status</span><strong><span class="pill <?= $statusClass ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span></strong></div>
      <div class="ticket-row"><span>Started</span><strong><?= htmlspecialchars(date('M j, Y · g:i A', strtotime($latestTicket['created_at'])), ENT_QUOTES, 'UTF-8') ?></strong></div>
      <a class="primary modal-primary" href="<?= htmlspecialchars($chatUrl, ENT_QUOTES, 'UTF-8') ?>">Open conversation</a>
      <?php else: ?><p class="sub modal-empty">You don’t have a support request yet. Start a conversation whenever you need help.</p><a class="primary modal-primary" href="<?= htmlspecialchars($chatUrl, ENT_QUOTES, 'UTF-8') ?>">Get support</a><?php endif; ?>
    </div>
  </div>

  </div>
  <section class="help-view" id="help-center" hidden aria-labelledby="help-center-title">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="#dashboard" data-show-dashboard>Dashboard</a><span aria-hidden="true">›</span><span aria-current="page">Help Center</span></nav>
    <div class="help-heading"><div class="eyebrow">CUSTOMER SUPPORT</div><h1 id="help-center-title">Help Center</h1><p class="sub">Find quick answers and simple steps to resolve common connection concerns.</p></div>
    <section class="panel guides">
      <details><summary>My internet is slow. What should I do?</summary><p>Restart your router by unplugging its power for about 30 seconds, then plug it back in and allow it to reconnect. If the issue continues, choose Get Support and tell our team when the slowdown happens.</p></details>
      <details><summary>I have no internet connection.</summary><p>Check that your router is powered on and that its cables are firmly connected. If the LOS light is red or blinking, avoid resetting the router and contact support so our team can assist.</p></details>
      <details><summary>How do I follow up on my concern?</summary><p>Choose My Concerns on your dashboard to view the latest ticket status, then select Open conversation to continue your existing support chat.</p></details>
      <details><summary>How do I contact customer service?</summary><p>Select Get Support on your dashboard. Your existing support conversation will open so you can continue without losing your previous messages.</p></details>
    </section>
    <div class="help-contact"><div><strong>Still have a question?</strong><p>Our team can help with your account or connection concern.</p></div><a class="primary" href="<?= htmlspecialchars($chatUrl, ENT_QUOTES, 'UTF-8') ?>">Chat with Support</a></div>
  </section>
</main>
<footer>SkyTruFiber by AHBA Development · Customer Portal</footer>


<script>
(function(){
 const dialogs=[...document.querySelectorAll('.portal-modal')];
 let lastTrigger=null;
 function close(dialog){
  dialog.classList.remove('is-open');dialog.style.display='';dialog.setAttribute('aria-hidden','true');
  document.body.classList.remove('modal-open');
  if(lastTrigger&&lastTrigger.focus)lastTrigger.focus();
 }
 function open(dialog,trigger){
  if(!dialog)return;lastTrigger=trigger||document.activeElement;
  dialog.style.display='flex';dialog.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');
  requestAnimationFrame(()=>{dialog.classList.add('is-open');const closeBtn=dialog.querySelector('.modal-close');if(closeBtn)closeBtn.focus();});
 }
 document.addEventListener('click',e=>{
  const trigger=e.target.closest('[data-open-modal]');
  if(trigger){e.preventDefault();open(document.getElementById(trigger.dataset.openModal),trigger);return;}
  const closeBtn=e.target.closest('.modal-close');
  if(closeBtn){const dialog=closeBtn.closest('.portal-modal');if(dialog)close(dialog);return;}
  const backdrop=e.target.classList.contains('portal-modal')?e.target:null;
  if(backdrop)close(backdrop);
 });
 document.addEventListener('keydown',e=>{
  if(e.key==='Escape'){const dialog=dialogs.find(d=>d.classList.contains('is-open'));if(dialog)close(dialog);}
 });
 const home=document.getElementById('dashboard-home');
 const help=document.getElementById('help-center');
 function showHelp(){if(home)home.hidden=true;if(help)help.hidden=false;window.location.hash='help-center';window.scrollTo({top:0,behavior:'smooth'});}
 function showDashboard(){if(help)help.hidden=true;if(home)home.hidden=false;window.location.hash='dashboard';window.scrollTo({top:0,behavior:'smooth'});}
 document.addEventListener('click',e=>{
  const helpTrigger=e.target.closest('[data-open-help]');
  if(helpTrigger){e.preventDefault();showHelp();return;}
  if(e.target.closest('[data-show-dashboard]')){e.preventDefault();showDashboard();return;}
  const quickToggle=e.target.closest('#quick-access-toggle');
  const quickMenu=document.getElementById('quick-access-menu');
  if(quickToggle&&quickMenu){const expanded=quickToggle.getAttribute('aria-expanded')==='true';quickToggle.setAttribute('aria-expanded',String(!expanded));quickMenu.hidden=expanded;return;}
  if(quickMenu&&!e.target.closest('.quick-access-wrap')){quickMenu.hidden=true;const b=document.getElementById('quick-access-toggle');if(b)b.setAttribute('aria-expanded','false');}
 });
 if(window.location.hash==='#help-center')showHelp();
})();
</script>

<script>
(function(){
 const clock=document.getElementById('live-clock'),date=document.getElementById('live-date');
 if(!clock||!date)return;
 const tf=new Intl.DateTimeFormat('en-PH',{timeZone:'Asia/Manila',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
 const df=new Intl.DateTimeFormat('en-PH',{timeZone:'Asia/Manila',weekday:'long',year:'numeric',month:'long',day:'numeric'});
 function tick(){const n=new Date();clock.textContent=tf.format(n);date.textContent=df.format(n)}
 tick();setInterval(tick,1000);
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
<script>
(function(){
 if(!window.gsap||window.matchMedia('(prefers-reduced-motion: reduce)').matches)return;
 gsap.from('.welcome > div',{y:18,opacity:0,duration:.65,stagger:.12,ease:'power3.out'});
 gsap.from('.action',{y:22,opacity:0,duration:.55,stagger:.09,delay:.12,ease:'power3.out'});
 gsap.from('.lower .panel,.guides,.support-note',{y:18,opacity:0,duration:.5,stagger:.1,delay:.28,ease:'power2.out'});
})();
</script>
</body>
</html>
