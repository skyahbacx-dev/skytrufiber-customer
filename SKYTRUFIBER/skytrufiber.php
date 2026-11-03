<?php
session_start();
require_once __DIR__ . '/../db_connect.php';

$message = '';

/* ============================================================
   LOGIN HANDLER
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['full_name'], $_POST['password'])) {

    $input    = trim($_POST['full_name']);
    $password = $_POST['password'];

    /* Proper concern handling */
    $concern = "";
    if (!empty($_POST['concern_text'])) {
        $concern = trim($_POST['concern_text']);
    } elseif (!empty($_POST['concern_dropdown']) && $_POST['concern_dropdown'] !== "others") {
        $concern = trim($_POST['concern_dropdown']);
    }

    if ($input && $password) {

        try {
            /* Fetch user */
            $stmt = $conn->prepare("
                SELECT *
                FROM users
                WHERE email = :input OR full_name = :input
                LIMIT 1
            ");
            $stmt->execute([':input' => $input]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {

                session_regenerate_id(true);

                /* Fetch last ticket */
                $ticketStmt = $conn->prepare("
                    SELECT id, status
                    FROM tickets
                    WHERE client_id = :cid
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $ticketStmt->execute([':cid' => $user['id']]);
                $lastTicket = $ticketStmt->fetch(PDO::FETCH_ASSOC);

                /* ============================================================
                   CREATE NEW TICKET + INSERT CSR GREETING
                ============================================================ */
                if (!$lastTicket || $lastTicket['status'] === 'resolved') {

                    $newTicket = $conn->prepare("
                        INSERT INTO tickets (client_id, status, created_at)
                        VALUES (:cid, 'pending', NOW())
                    ");
                    $newTicket->execute([':cid' => $user['id']]);
                    $ticketId = $conn->lastInsertId();

                    /* Personalized CSR Greeting (FULL NAME) */
                 $csrGreeting = "Hello {$user['full_name']}! This is SkyTruFiber Support. How may I assist you today?";

                  $greet = $conn->prepare("
                   INSERT INTO chat (ticket_id, client_id, sender_type, message, delivered, seen, created_at)
                   VALUES (:tid, NULL, 'csr', :msg, TRUE, TRUE, NOW())
                  ");

                  $greet->execute([
                   ':tid' => $ticketId,
                   ':msg' => $csrGreeting
                  ]);


                    $_SESSION['show_suggestions'] = true;

                } else {
                    $ticketId = $lastTicket['id'];
                }

                /* Save session */
                $_SESSION['client_id'] = $user['id'];
                $_SESSION['ticket_id'] = $ticketId;

                /* Insert Client Inquiry */
                if (!empty($concern)) {
                    $insert = $conn->prepare("
                        INSERT INTO chat (ticket_id, client_id, sender_type, message, delivered, created_at)
                        VALUES (:tid, :cid, 'client', :msg, TRUE, NOW())
                    ");
                    $insert->execute([
                        ':tid' => $ticketId,
                        ':cid' => $user['id'],
                        ':msg' => $concern
                    ]);
                }

                header("Location: /fiber/chat?ticket=$ticketId");
                exit;

            } else {
                $message = "❌ Invalid login credentials.";
            }

        } catch (PDOException $e) {
            $message = "⚠ Database error: " . htmlspecialchars($e->getMessage());
        }

    } else {
        $message = "⚠ Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>
/* no scrollbar flash while the card rises into view */
document.documentElement.classList.add('intro');
setTimeout(function(){ document.documentElement.classList.remove('intro'); }, 3600);
</script>
<title>SkyTruFiber Customer Portal</title>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
body{
    margin:0;
    font-family:"Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Arial;
    background:radial-gradient(ellipse at 50% 45%, #14327a 0%, #08142f 58%, #050c1e 100%);
    background-attachment:fixed;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    min-height:100vh;
    box-sizing:border-box;
    padding:12px 0;
}
html.intro, html.intro body{ overflow:hidden; }

.container{
    background:white;
    padding:32px;
    border-radius:20px;
    box-shadow:0 10px 28px rgba(20,24,40,.14);
    box-sizing:border-box;
    width:min(444px, calc(100vw - 32px));
    text-align:center;
    position:relative;
    z-index:1;
    overflow:hidden;
    --pd:1.5s;                       /* delay before the card rises (coin goes first) */
    animation:cardPop 1s cubic-bezier(.2,.8,.2,1) var(--pd) both;
}

/* 3D coin sits here: the canvas draws the coin over this empty box */
.coin-anchor{
    display:none;
    flex:none;
    width:clamp(120px, 22vh, 200px);
    height:clamp(120px, 22vh, 200px);
    margin-bottom:6px;
    pointer-events:none;
}
.fx-on .coin-anchor{ display:block; }
.fx-on .logo-fb{ display:none; }

/* 3D fibre background (decorative) */
#fx{
    position:fixed;
    inset:0;
    width:100%;
    height:100%;
    z-index:0;
    pointer-events:none;
    opacity:0;
    transition:opacity 1.2s ease;
}
#fx.on{ opacity:1; }

/* form rises from the bottom, then the fields follow one by one */
@keyframes cardPop{
    0%  { opacity:0; transform:translateY(85vh) scale(.94); animation-timing-function:cubic-bezier(.16,.84,.3,1); }
    60% { opacity:1; transform:translateY(-16px) scale(1.01); animation-timing-function:ease-in-out; }
    80% { transform:translateY(6px) scale(1); animation-timing-function:ease-in-out; }
    100%{ opacity:1; transform:translateY(0) scale(1); }
}
@keyframes rise{
    from{ opacity:0; transform:translateY(26px); }
    to  { opacity:1; transform:translateY(0); }
}
.container > h2,
.container > .message,
.container > .small-links,
#loginForm > :not(textarea){
    animation:rise .6s cubic-bezier(.2,.8,.2,1) both;
    animation-delay:calc(var(--pd) + .55s);
}
#loginForm > :nth-child(1){ animation-delay:calc(var(--pd) + .65s); }
#loginForm > :nth-child(2){ animation-delay:calc(var(--pd) + .73s); }
#loginForm > :nth-child(3){ animation-delay:calc(var(--pd) + .81s); }
#loginForm > :nth-child(5){ animation-delay:calc(var(--pd) + .89s); }
.container > .small-links{ animation-delay:calc(var(--pd) + .97s); }

.container img{
    width:150px;
    margin-bottom:15px;
}

input, select, textarea {
    width:100%;
    padding:12px;
    margin:10px 0;
    border-radius:10px;
    border:1px solid #d7dbe4;
    font-size:15px;
    font-family:inherit;
    box-sizing:border-box;
}

input:focus, select:focus, textarea:focus {
    outline:none;
    border-color:#2f6fe0;
    box-shadow:0 0 0 3px #e8eefc;
}

textarea{
    height:80px;
    resize:none;
    display:none;
}

button{
    width:100%;
    padding:12px;
    background:#1a4fc4;
    color:white;
    border:none;
    border-radius:50px;
    cursor:pointer;
    font-size:16px;
    font-weight:700;
}

button:hover{ background:#123a91; }

.small-links{
    margin-top:12px;
    font-size:14px;
}

.small-links a{
    color:#123a91;
    text-decoration:none;
    font-weight:600;
}

.small-links a:hover{
    text-decoration:underline;
}

.message{
    color:#c0342b;
    font-size:0.9em;
    margin-bottom:8px;
}

/* Animation */
.form-box {
    transition: opacity .3s ease, transform .3s ease, height .3s ease;
}

.hidden {
    opacity: 0;
    transform: translateY(20px);
    height: 0;
    overflow: hidden;
    pointer-events: none;
}

.visible {
    opacity: 1;
    transform: translateY(0);
    height: auto;
    pointer-events: auto;
}
</style>
</head>

<body>

<canvas id="fx" aria-hidden="true"></canvas>
<div class="coin-anchor" id="coinAnchor"></div>

<div class="container">

    <img class="logo-fb" src="../SKYTRUFIBER.png" alt="SkyTruFiber Logo">

    <h2>Customer Service Portal</h2>

<?php if ($message): ?>
<p class="message"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<!-- LOGIN FORM -->
<form id="loginForm" class="form-box visible" method="POST">

    <input type="text" name="full_name" placeholder="Email or Full Name" required>
    <input type="password" name="password" placeholder="Password" required>

    <select name="concern_dropdown" id="concernSelect">
        <option value="">Select Concern / Inquiry</option>
        <option>Slow Internet</option>
        <option>No Connection</option>
        <option>Router LOS Light On</option>
        <option>Intermittent Internet</option>
        <option>Billing Concern</option>
        <option>Account Verification</option>
        <option value="others">Others…</option>
    </select>

    <textarea id="concernText" name="concern_text" placeholder="Type your concern here..."></textarea>

    <button type="submit">Submit</button>
</form>

<!-- FORGOT PASSWORD FORM -->
<form id="forgotForm" class="form-box hidden" onsubmit="return false;">

    <h2>Forgot Password</h2>
    <p style="font-size:14px;">Enter your email and we will send your account number.</p>

    <input type="email" id="forgotEmail" placeholder="Your Email">

    <button id="sendForgotBtn">Send Email</button>

    <div class="small-links" style="margin-top:15px;">
        <a href="#" id="backToLogin">← Back to Login</a>
    </div>
</form>

<div class="small-links">
    <a href="/fiber/consent">Register here</a> |
    <a href="#" id="forgotLink">Forgot Password?</a>
</div>

</div>

<script>
// Concern toggle
document.getElementById("concernSelect").addEventListener("change", function(){
    concernText.style.display = (this.value === "others") ? "block" : "none";
});

// Show forgot form
forgotLink.onclick = e => {
    e.preventDefault();
    loginForm.classList.replace("visible","hidden");
    forgotForm.classList.replace("hidden","visible");
};

// Back to login
backToLogin.onclick = e => {
    e.preventDefault();
    forgotForm.classList.replace("visible","hidden");
    loginForm.classList.replace("hidden","visible");
};

// AJAX: Send email
sendForgotBtn.onclick = async () => {

    let email = forgotEmail.value.trim();
    if (!email) {
        Swal.fire("Missing Email","Please enter your email.","warning");
        return;
    }

    Swal.fire({
        title:"Sending...",
        text:"Please wait...",
        allowOutsideClick:false,
        didOpen:()=>Swal.showLoading()
    });

    let response = await fetch("/fiber/forgot_password.php", {
        method:"POST",
        headers:{ "Content-Type":"application/x-www-form-urlencoded" },
        body:"email=" + encodeURIComponent(email)
    });

    let data = await response.json();

    if (data.success){
        Swal.fire("Success!", data.message, "success");
        forgotEmail.value = "";
    } else {
        Swal.fire("Error", data.message, "error");
    }
};
</script>

<!-- 3D background + coin: three.js from jsDelivr, cdnjs as backup -->
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/build/three.min.js"></script>
<script>window.THREE || document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"><\/script>');</script>
<script>
/* SkyTruFiber login: fibre-optic light scene behind the card.
   Purely decorative: if three.js or WebGL is unavailable the page keeps its gradient. */
(function () {
  var cv = document.getElementById('fx');
  if (!cv) return;
  if (!window.THREE) { console.warn('[fiber3d] three.js not loaded - using gradient background'); return; }
  var T = THREE, renderer;
  try {
    renderer = new T.WebGLRenderer({ canvas: cv, antialias: true, alpha: true, powerPreference: 'high-performance' });
  } catch (e) { console.warn('[fiber3d] WebGL unavailable - using gradient background', e); return; }

  renderer.autoClear = false;
  var root_el = document.documentElement;
  root_el.classList.add('fx-on');     // shows the coin anchor, hides the flat logo
  var card = document.querySelector('.container');
  var anchor = document.getElementById('coinAnchor');

  // Reduced-motion users get a much slower scene instead of a frozen one.
  var slow = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var SPEED = slow ? 0.3 : 1;
  var mobile = (window.matchMedia && matchMedia('(max-width:700px)').matches) || /Mobi|Android/i.test(navigator.userAgent);

  var scene = new T.Scene();
  var cam = new T.PerspectiveCamera(50, 1, 0.1, 100);
  cam.position.set(0, 0, 10);
  var root = new T.Group();
  scene.add(root);

  function rnd(a, b) { return a + Math.random() * (b - a); }

  // soft round sprite for the light pulses
  var sc = document.createElement('canvas'); sc.width = sc.height = 64;
  var g = sc.getContext('2d');
  var rg = g.createRadialGradient(32, 32, 0, 32, 32, 32);
  rg.addColorStop(0, 'rgba(255,255,255,1)');
  rg.addColorStop(0.25, 'rgba(255,255,255,.7)');
  rg.addColorStop(1, 'rgba(255,255,255,0)');
  g.fillStyle = rg; g.fillRect(0, 0, 64, 64);
  var sprite = new T.CanvasTexture(sc);

  var N = mobile ? 38 : 84;          // strands
  var PU = 3;                        // pulses per strand
  var SEG = 140;                     // samples per strand
  var palette = [0x2f6fe0, 0x4fb3ff, 0x35d07f, 0x9fd2ff].map(function (c) { return new T.Color(c); });
  var strands = [];
  var addBlend = T.AdditiveBlending;

  for (var i = 0; i < N; i++) {
    var by = rnd(-3.4, 3.4), bz = rnd(-5, 2.5), ph = rnd(0, 6.28), amp = rnd(0.4, 1.1);
    var pts = [];
    for (var k = 0; k < 7; k++) {
      var x = -17 + k * (34 / 6);
      var spread = 0.22 + Math.pow(Math.abs(x) / 17, 1.6) * 1.25;   // pinches near the middle, fans out at the sides
      pts.push(new T.Vector3(
        x,
        by * spread + Math.sin(x * 0.33 + ph) * amp,
        bz * spread + Math.cos(x * 0.29 + ph) * amp - 1
      ));
    }
    var curve = new T.CatmullRomCurve3(pts);
    var samples = curve.getPoints(SEG - 1);
    var col = palette[i % palette.length];

    var pos = new Float32Array(SEG * 3), colr = new Float32Array(SEG * 3);
    for (var s = 0; s < SEG; s++) {
      var p = samples[s], u = s / (SEG - 1), fade = 0.15 + 0.85 * Math.sin(Math.PI * u);
      pos[s * 3] = p.x; pos[s * 3 + 1] = p.y; pos[s * 3 + 2] = p.z;
      colr[s * 3] = col.r * fade; colr[s * 3 + 1] = col.g * fade; colr[s * 3 + 2] = col.b * fade;
    }
    var geo = new T.BufferGeometry();
    geo.setAttribute('position', new T.BufferAttribute(pos, 3));
    geo.setAttribute('color', new T.BufferAttribute(colr, 3));
    root.add(new T.Line(geo, new T.LineBasicMaterial({
      vertexColors: true, transparent: true, opacity: 0.6, blending: addBlend, depthWrite: false
    })));
    strands.push({ samples: samples, col: col });
  }

  // travelling light pulses
  var PN = N * PU;
  var ppos = new Float32Array(PN * 3), pcol = new Float32Array(PN * 3), pulses = [];
  for (var a = 0; a < N; a++) for (var b = 0; b < PU; b++) {
    var c = strands[a].col, idx = a * PU + b;
    pcol[idx * 3] = Math.min(1, c.r + 0.35); pcol[idx * 3 + 1] = Math.min(1, c.g + 0.35); pcol[idx * 3 + 2] = Math.min(1, c.b + 0.35);
    pulses.push({ strand: a, u0: Math.random(), v: rnd(0.035, 0.1) });
  }
  var pgeo = new T.BufferGeometry();
  var pAttr = new T.BufferAttribute(ppos, 3);
  pgeo.setAttribute('position', pAttr);
  pgeo.setAttribute('color', new T.BufferAttribute(pcol, 3));
  root.add(new T.Points(pgeo, new T.PointsMaterial({
    size: mobile ? 0.42 : 0.34, map: sprite, vertexColors: true, transparent: true,
    blending: addBlend, depthWrite: false, sizeAttenuation: true
  })));

  // faint dust for depth
  var DN = mobile ? 90 : 220, dpos = new Float32Array(DN * 3);
  for (var d = 0; d < DN; d++) { dpos[d * 3] = rnd(-16, 16); dpos[d * 3 + 1] = rnd(-8, 8); dpos[d * 3 + 2] = rnd(-9, 4); }
  var dgeo = new T.BufferGeometry();
  dgeo.setAttribute('position', new T.BufferAttribute(dpos, 3));
  root.add(new T.Points(dgeo, new T.PointsMaterial({
    size: 0.11, map: sprite, color: 0x8fb8ff, transparent: true, opacity: 0.45,
    blending: addBlend, depthWrite: false, sizeAttenuation: true
  })));

  /* ---------- the coin ---------- */
  var coinScene = new T.Scene();
  var coinCam = new T.PerspectiveCamera(50, 1, 0.1, 100);
  coinScene.add(new T.AmbientLight(0xffffff, 0.6));
  var key = new T.DirectionalLight(0xffffff, 0.5); key.position.set(-3, 4, 6); coinScene.add(key);
  var rimL = new T.PointLight(0x4fb3ff, 1.1, 30); rimL.position.set(5, 2, -3); coinScene.add(rimL);
  var fillL = new T.PointLight(0x35d07f, 0.7, 30); fillL.position.set(-5, -3, 3); coinScene.add(fillL);

  var coin = new T.Group();
  coinScene.add(coin);
  coin.visible = false;
  var TH = 0.16, RR = 1.07;             // thickness, outer radius incl. rim

  var edgeMat = new T.MeshPhongMaterial({ color: 0x1a3f9e, specular: 0xcfe3ff, shininess: 90 });
  var rimMat = new T.MeshPhongMaterial({ color: 0x2f6fe0, specular: 0xffffff, shininess: 120 });
  var body = new T.Mesh(new T.CylinderGeometry(1.03, 1.03, TH + 0.1, 80, 1, true), edgeMat);
  body.rotation.x = Math.PI / 2;
  coin.add(body);
  [1, -1].forEach(function (sd) {
    var rim = new T.Mesh(new T.TorusGeometry(1.0, 0.05, 14, 80), rimMat);
    rim.position.z = sd * (TH / 2 + 0.0);
    coin.add(rim);
  });

  var tex = new T.TextureLoader().load('/SKYTRUFIBER.png', function () { texReady = true; }, undefined, function () {
    console.warn('[fiber3d] could not load /SKYTRUFIBER.png - showing flat logo');
    root_el.classList.remove('fx-on');
    if (card) card.style.setProperty('--pd', '0.15s');
  });
  // crop the 500x500 PNG to the logo circle (measured: centre 249.5,256 / diameter 468)
  tex.repeat.set(0.936, 0.936);
  tex.offset.set(0.031, 0.020);
  tex.wrapS = tex.wrapT = T.ClampToEdgeWrapping;
  if (renderer.capabilities && renderer.capabilities.getMaxAnisotropy) tex.anisotropy = renderer.capabilities.getMaxAnisotropy();

  var faceMat = new T.MeshPhongMaterial({
    map: tex, alphaTest: 0.4, shininess: 55, specular: 0x444444,
    emissive: 0xffffff, emissiveMap: tex, emissiveIntensity: 0.18
  });
  var faceGeo = new T.CircleGeometry(0.97, 80);
  var front = new T.Mesh(faceGeo, faceMat); front.position.z = TH / 2 + 0.002; coin.add(front);
  var back = new T.Mesh(faceGeo, faceMat); back.position.z = -(TH / 2 + 0.002); back.rotation.y = Math.PI; coin.add(back);

  var glow = new T.Sprite(new T.SpriteMaterial({
    map: sprite, color: 0x2f7bff, transparent: true, opacity: 0.5, blending: addBlend, depthWrite: false
  }));
  coinScene.add(glow);

  var texReady = false, coinT0 = -1, tiltX = 0, tiltY = 0, boost = 0, spinExtra = 0;
  window.addEventListener('submit', function () { boost = 1; }, true);   // coin spins faster while the form submits

  function easeOutBack(x) { var c1 = 1.70158, c3 = c1 + 1; return 1 + c3 * Math.pow(x - 1, 3) + c1 * Math.pow(x - 1, 2); }
  function easeInOut(x) { return x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2; }
  function clamp01(x) { return Math.max(0, Math.min(1, x)); }
  var W = 1, H = 1, D = 10;

  function updateCoin(dt) {
    if (!texReady || !anchor) return false;
    if (coinT0 < 0) { coinT0 = t; coin.visible = true; }
    var ct = t - coinT0;
    var r = anchor.getBoundingClientRect();
    if (r.width < 10) return false;
    var wpp = 2 * D * Math.tan(25 * Math.PI / 180) / H;       // world units per CSS pixel on the coin's plane
    var bx = (r.left + r.width / 2 - W / 2) * wpp;
    var by = -(r.top + r.height / 2 - H / 2) * wpp;
    var bs = (r.width / 2) * wpp / RR;

    var p = clamp01(ct / 2.0), e = 1 - Math.pow(1 - p, 3);
    var sc = bs * Math.max(0.001, easeOutBack(clamp01(ct / 1.1)));
    var flip = 0;
    if (ct > 3.4) { var cyc = (ct - 3.4) % 7; if (cyc < 1.4) flip = easeInOut(cyc / 1.4) * Math.PI * 2; }
    if (boost) spinExtra += dt * 12;

    tiltY += (mx * 0.5 - tiltY) * 0.06;
    tiltX += (my * 0.35 - tiltX) * 0.06;

    coin.scale.set(sc, sc, sc);
    coin.position.set(bx, by + (1 - e) * 2.4 * bs + Math.sin(ct * 1.4) * 0.035 * bs, 0);
    coin.rotation.set(tiltX, (1 - e) * Math.PI * 6 + flip + tiltY + spinExtra, 0);

    var gs = bs * RR * 4.2 * (0.95 + 0.05 * Math.sin(ct * 2));
    glow.position.set(bx, by, -0.6 * bs);
    glow.scale.set(gs, gs, 1);
    glow.material.opacity = 0.5 * e;
    return true;
  }

  function resize() {
    var w = window.innerWidth, h = window.innerHeight;
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, mobile ? 1.5 : 2));
    renderer.setSize(w, h, false);
    cam.aspect = w / h;
    cam.position.z = cam.aspect < 1 ? 14 : 10;
    cam.updateProjectionMatrix();
    W = w; H = h; D = cam.position.z;
    coinCam.aspect = cam.aspect;
    coinCam.position.set(0, 0, D);
    coinCam.updateProjectionMatrix();
  }
  window.addEventListener('resize', resize);
  resize();

  var mx = 0, my = 0;
  window.addEventListener('pointermove', function (e) {
    mx = (e.clientX / window.innerWidth - 0.5) * 2;
    my = (e.clientY / window.innerHeight - 0.5) * 2;
  }, { passive: true });

  var clock = new T.Clock(), t = 0, shown = false;
  function frame() {
    requestAnimationFrame(frame);
    if (document.hidden) return;
    var dt = Math.min(clock.getDelta(), 0.05) * SPEED;
    t += dt;

    for (var n = 0; n < PN; n++) {
      var pu = pulses[n], u = (pu.u0 + t * pu.v) % 1, f = u * (SEG - 1), i0 = Math.floor(f), fr = f - i0;
      var sm = strands[pu.strand].samples, p0 = sm[i0], p1 = sm[Math.min(i0 + 1, SEG - 1)];
      ppos[n * 3] = p0.x + (p1.x - p0.x) * fr;
      ppos[n * 3 + 1] = p0.y + (p1.y - p0.y) * fr;
      ppos[n * 3 + 2] = p0.z + (p1.z - p0.z) * fr;
    }
    pAttr.needsUpdate = true;

    root.rotation.y = Math.sin(t * 0.12) * 0.12;
    root.rotation.x = Math.sin(t * 0.09) * 0.05;
    cam.position.x += (mx * 0.9 - cam.position.x) * 0.04;
    cam.position.y += (-my * 0.6 - cam.position.y) * 0.04;
    cam.lookAt(0, 0, 0);

    renderer.clear();
    renderer.render(scene, cam);
    if (updateCoin(dt)) { renderer.clearDepth(); renderer.render(coinScene, coinCam); }
    if (!shown) { shown = true; cv.classList.add('on'); }
  }
  frame();
  console.info('[fiber3d] running: ' + N + ' strands, ' + PN + ' pulses' + (mobile ? ' (mobile)' : '') + (slow ? ' (reduced motion: slowed)' : ''));
})();
</script>

</body>
</html>
