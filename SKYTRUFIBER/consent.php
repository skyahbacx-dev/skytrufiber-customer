<?php
session_start();
if (empty($_SESSION['consent_csrf'])) $_SESSION['consent_csrf'] = bin2hex(random_bytes(32));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $choice = $_POST['consent'] ?? '';
    $source = $_POST['source'] ?? '';
    if (!is_string($csrf) || !hash_equals($_SESSION['consent_csrf'], $csrf)) {
        http_response_code(400);
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif ($choice !== 'yes') {
        unset($_SESSION['registration_allowed'], $_SESSION['registration_source']);
        header('Location: /fiber/no-consent'); exit;
    } elseif (!in_array($source, ['QR Code', 'Gmail', 'Others'], true)) {
        $error = 'Please choose where you learned about SkyTruFiber.';
    } else {
        session_regenerate_id(true);
        $_SESSION['registration_allowed'] = true;
        $_SESSION['registration_source'] = $source;
        $_SESSION['registration_started_at'] = time();
        $_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
        header('Location: /fiber/register'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#063b70"><title>Privacy Consent | SkyTruFiber</title>
<style>
:root{--navy:#073b70;--blue:#0879bd;--ink:#18324b;--muted:#5d7185;--line:#dce6ef}*{box-sizing:border-box}
body{margin:0;min-height:100vh;padding:24px;display:grid;place-items:center;background:radial-gradient(circle at top left,#dff3ff,transparent 42%),linear-gradient(135deg,#edf5fc,#f7f9fc);font-family:Inter,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:var(--ink)}
.card{width:min(100%,560px);background:#fff;border:1px solid #e5edf4;border-radius:24px;padding:clamp(24px,5vw,42px);box-shadow:0 18px 55px #153b5b18}
.brand{text-align:center;margin-bottom:24px}.brand img{width:104px;height:104px;object-fit:contain;border-radius:50%}.eyebrow{margin:12px 0 6px;color:var(--blue);font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
h1{margin:0;color:var(--navy);font-size:clamp(25px,5vw,32px);text-align:center}.intro{color:var(--muted);line-height:1.65;font-size:15px}
.notice{padding:16px;border:1px solid var(--line);border-radius:14px;background:#f8fbfe;line-height:1.6;font-size:14px}
.choice{display:flex;align-items:flex-start;gap:12px;padding:14px;border:1px solid var(--line);border-radius:12px;margin-top:12px;cursor:pointer;line-height:1.45}
.choice:has(input:checked){border-color:var(--blue);background:#eff8ff}.choice input{margin-top:3px;accent-color:var(--blue)}
button{width:100%;border:0;border-radius:12px;background:var(--blue);color:#fff;padding:14px 18px;font-size:16px;font-weight:700;cursor:pointer;margin-top:20px}
button:hover{background:var(--navy)}button:focus-visible,a:focus-visible,input:focus-visible{outline:3px solid #8bcfff;outline-offset:3px}
.hidden{display:none}.error{color:#a52828;background:#fff2f2;border-radius:10px;padding:12px;font-size:14px}
.back{display:block;text-align:center;margin-top:20px;color:var(--navy);font-weight:650;text-decoration:none}.small{font-size:12px;color:var(--muted);text-align:center;margin-top:20px}
@media(max-width:480px){body{padding:12px}.card{border-radius:18px}.brand img{width:86px;height:86px}}
</style></head><body><main class="card">
<div class="brand"><img src="/SKYTRUFIBER.png" alt="SkyTruFiber logo"><div class="eyebrow">AHBA Development</div></div>
<section id="consentStep"><h1>Data Privacy Notice</h1>
<p class="intro">Before continuing, please review how SkyTruFiber uses the information you provide.</p>
<div class="notice">SkyTruFiber by AHBA Development collects and processes customer information for account verification, installation records, billing, technical support, service updates, and customer service. We handle personal information in accordance with the Data Privacy Act of 2012 and apply appropriate safeguards. Your information will be accessed only for legitimate service-related purposes.</div>
<label class="choice"><input type="radio" name="consentChoice" value="yes"><span><strong>Yes, I agree.</strong><br>I consent to the collection and processing of my personal information for the purposes described above.</span></label>
<label class="choice"><input type="radio" name="consentChoice" value="no"><span><strong>No, I do not agree.</strong><br>I understand that registration cannot continue without this consent.</span></label>
<button type="button" id="continueButton">Continue</button></section>
<form id="sourceStep" class="hidden" method="post" action="/fiber/consent">
<h1>One quick question</h1><p class="intro">Where did you learn about the SkyTruFiber customer portal?</p>
<input type="hidden" name="consent" value="yes"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['consent_csrf'], ENT_QUOTES, 'UTF-8') ?>">
<label class="choice"><input type="radio" name="source" value="QR Code" required><span>QR Code</span></label>
<label class="choice"><input type="radio" name="source" value="Gmail"><span>Gmail</span></label>
<label class="choice"><input type="radio" name="source" value="Others"><span>Others</span></label>
<button type="submit">Continue to Registration</button></form>
<?php if ($error): ?><p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<a class="back" href="/fiber">← Back to Customer Portal</a><p class="small">SkyTruFiber Customer Portal · AHBA Development</p>
</main><script>
document.getElementById('continueButton').addEventListener('click',function(){
const selected=document.querySelector('input[name="consentChoice"]:checked');
if(!selected){alert('Please select YES or NO to continue.');return;}
if(selected.value==='no'){
const form=document.createElement('form');form.method='post';form.action='/fiber/consent';
form.innerHTML='<input type="hidden" name="consent" value="no"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['consent_csrf'], ENT_QUOTES, 'UTF-8') ?>">';
document.body.appendChild(form);form.submit();return;}
document.getElementById('consentStep').classList.add('hidden');document.getElementById('sourceStep').classList.remove('hidden');
});
</script></body></html>