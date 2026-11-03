<?php
session_start();

$message = '';
$source = $_SESSION['registration_source'] ?? '';
$registrationStarted = (int)($_SESSION['registration_started_at'] ?? 0);
$registrationAllowed = !empty($_SESSION['registration_allowed'])
    && $registrationStarted > 0
    && (time() - $registrationStarted) <= 1800;

if (!$registrationAllowed || !is_string($source) || !in_array($source, ['QR Code', 'Gmail', 'Others'], true)) {
    http_response_code(403);
    exit('Please complete the privacy consent step before registering.');
}

require_once __DIR__ . '/../db_connect.php';

/* Keep submitted values when validation fails. */
$formData = [
    'account_number' => '',
    'full_name' => '',
    'email' => '',
    'district' => '',
    'location' => '',
    'date_installed' => '',
    'remarks' => '',
    'rating' => ''
];

/* Server-side district/barangay allowlist; never trust the browser's hidden field. */
$allowedBarangays = [
    'District 1' => ["Alicia","Bagong Pag-asa","Bahay Toro","Balingasa","Bungad","Damar","Damayan","Del Monte","Katipunan","Lourdes","Maharlika","Manresa","Mariblo","Masambong","N.S. Amoranto (Gintong Silahis)","Nayong Kanluran","Paang Bundok","Pag-ibig sa Nayon","Paltok","Paraiso","Phil-Am","Project 6","Ramon Magsaysay","Saint Peter","Salvacion","San Antonio","San Isidro Labrador","San Jose","Santa Cruz","Santa Teresita","Sto. Cristo","Santo Domingo (Matalahib)","Siena","Talayan","Vasra","Veterans Village","West Triangle"],
    'District 2' => ["Bagong Silangan","Batasan Hills","Commonwealth","Holy Spirit","Payatas"],
    'District 3' => ["Amihan","Bagumbayan","Bagumbuhay","Bayanihan","Blue Ridge A","Blue Ridge B","Camp Aguinaldo","Claro (Quirino 3-B)","Dioquino Zobel","Duyan-duyan","E. Rodriguez","East Kamias","Escopa I","Escopa II","Escopa III","Escopa IV","Libis","Loyola Heights","Mangga","Marilag","Masagana","Matandang Balara","Milagrosa","Pansol","Quirino 2-A","Quirino 2-B","Quirino 2-C","Quirino 3-A","St. Ignatius","San Roque","Silangan","Socorro","Tagumpay","Ugong Norte","Villa Maria Clara","West Kamias","White Plains"],
    'District 4' => ["Bagong Lipunan ng Crame","Botocan","Central","Damayang Lagi","Don Manuel","Doña Aurora","Doña Imelda","Doña Josefa","Horseshoe","Immaculate Concepcion","Kalusugan","Kamuning","Kaunlaran","Kristong Hari","Krus na Ligas","Laging Handa","Malaya","Mariana","Obrero","Old Capitol Site","Paligsahan","Pinagkaisahan","Pinyahan","Roxas","Sacred Heart","San Isidro Galas","San Martin de Porres","San Vicente","Santol","Sikatuna Village","South Triangle","Sto. Niño","Tatalon","Teacher's Village East","Teacher's Village West","U.P. Campus","U.P. Village","Valencia"],
    'District 5' => ["Bagbag","Capri","Fairview","Greater Lagro","Gulod","Kaligayahan","Nagkaisang Nayon","North Fairview","Novaliches Proper","Pasong Putik Proper","San Agustin","San Bartolome","Sta. Lucia","Sta. Monica"],
    'District 6' => ["Apolonio Samson","Baesa","Balong Bato","Culiat","New Era","Pasong Tamo","Sangandaan","Sauyo","Talipapa","Tandang Sora","Unang Sigaw"]
];

/* ============================================================
   FORM SUBMISSION HANDLER
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $account_number = preg_replace('/\\D+/', '', (string)($_POST['account_number'] ?? ''));
    $full_name      = trim((string)($_POST['full_name'] ?? ''));
    $email          = trim((string)($_POST['email'] ?? ''));
    $district       = trim((string)($_POST['district'] ?? ''));
    $barangay       = trim((string)($_POST['location'] ?? ''));
    $date_installed = trim((string)($_POST['date_installed'] ?? ''));
    $remarks        = trim((string)($_POST['remarks'] ?? ''));
    $rating         = isset($_POST['rating']) && $_POST['rating'] !== '' ? (int)$_POST['rating'] : null;
    $password       = $account_number;
    $postedCsrf     = $_POST['csrf'] ?? '';

    $formData = [
        'account_number' => $account_number,
        'full_name' => $full_name,
        'email' => $email,
        'district' => $district,
        'location' => $barangay,
        'date_installed' => $date_installed,
        'remarks' => $remarks,
        'rating' => $rating === null ? '' : (string)$rating
    ];

    $validDate = DateTime::createFromFormat('!Y-m-d', $date_installed);
    $dateIsValid = $validDate && $validDate->format('Y-m-d') === $date_installed
        && $date_installed <= date('Y-m-d');
    $barangayIsValid = isset($allowedBarangays[$district])
        && in_array($barangay, $allowedBarangays[$district], true);

    if (!is_string($postedCsrf) || !isset($_SESSION['registration_csrf']) || !hash_equals($_SESSION['registration_csrf'], $postedCsrf)) {
        $message = "Your session expired. Please refresh the page and try again.";
    } elseif (!preg_match('/^187870[0-9]{7}$/', $account_number)) {
        $message = "Invalid account number. SkyTruFiber subscriber account numbers must start with 187870. Please check your bill or account details and enter the correct number.";
    } elseif ($full_name === '' || strlen($full_name) > 120) {
        $message = "Please enter your full name (up to 120 characters).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $message = "Please enter a valid email address.";
    } elseif (!$barangayIsValid) {
        $message = "Please select a valid barangay for your chosen district.";
    } elseif (!$dateIsValid) {
        $message = "Please enter a valid installation date. Future dates are not allowed.";
    } elseif ($rating === null || $rating < 1 || $rating > 5) {
        $message = "Please select a service satisfaction rating.";
    } elseif (strlen($remarks) > 2000) {
        $message = "Feedback must be 2,000 characters or fewer.";
    } else {

        try {
            /* Prevent duplicates */
            $check = $conn->prepare("
                SELECT id FROM users 
                WHERE account_number = :acc 
                OR email = :email
                LIMIT 1
            ");
            $check->execute([
                ':acc'   => $account_number,
                ':email' => $email
            ]);

            if ($check->fetch()) {
                $message = "This account number or email is already registered.";
            } else {

                $conn->beginTransaction();
                $hash = password_hash($password, PASSWORD_BCRYPT);

                /* Insert user. RETURNING id is used instead of
                   PDO::lastInsertId() because that call is unreliable on
                   Postgres without an explicit sequence name. */
                $stmt = $conn->prepare("
                    INSERT INTO users 
                        (account_number, full_name, email, password, district, barangay, date_installed, privacy_consent, source, created_at)
                    VALUES 
                        (:acc, :name, :email, :pw, :district, :barangay, :installed, 'yes', :source, NOW())
                    RETURNING id
                ");
                $stmt->execute([
                    ':acc'      => $account_number,
                    ':name'     => $full_name,
                    ':email'    => $email,
                    ':pw'       => $hash,
                    ':district' => $district,
                    ':barangay' => $barangay,
                    ':installed'=> $date_installed,
                    ':source'   => $source
                ]);
                $newUserId = (int)$stmt->fetchColumn();

                /* Insert survey response: the rating is now required by the
                   form, feedback text stays optional. user_id is now linked
                   (previously left NULL, which is why the admin survey view's
                   join to users could come up empty for new signups). */
                if ($rating !== null || $remarks) {
                    $stmt2 = $conn->prepare("
                        INSERT INTO survey_responses 
                            (client_name, account_number, district, location, feedback, rating, source, user_id, created_at)
                        VALUES 
                            (:name, :acc, :district, :barangay, :feedback, :rating, :source, :uid, NOW())
                    ");
                    $stmt2->execute([
                        ':name'     => $full_name,
                        ':acc'      => $account_number,
                        ':district' => $district,
                        ':barangay' => $barangay,
                        ':feedback' => $remarks,
                        ':rating'   => $rating,
                        ':source'   => $source,
                        ':uid'      => $newUserId
                    ]);
                }

                $conn->commit();

                /* Clear used token */
                unset($_SESSION['registration_allowed'], $_SESSION['registration_source'], $_SESSION['registration_started_at'], $_SESSION['registration_csrf']);
                session_regenerate_id(true);

                /* Redirect to success page */
                header("Location: /fiber/register/success");
                exit;
            }

        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log('SkyTruFiber registration failed: ' . $e->getMessage());
            $message = "We could not complete your registration right now. Please try again later.";
        }

    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="/SKYTRUFIBER.png">
<link rel="apple-touch-icon" href="/SKYTRUFIBER.png">
<meta charset="UTF-8">
<title>Customer Registration – SkyTruFiber</title>
<!-- Standalone portal styling is contained in this page; no main-site CSS dependency. -->

<style>
:root{--navy:#073b70;--blue:#1a4fc4;--ink:#18324b;--muted:#64788b;--line:#dce6ef}
*{box-sizing:border-box}
body{font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;background:radial-gradient(ellipse at 50% 45%,#14327a 0%,#08142f 58%,#050c1e 100%);background-attachment:fixed;margin:0;min-height:100vh;padding:24px 14px 40px;display:flex;flex-direction:column;justify-content:flex-start;align-items:center;color:var(--ink);overflow-x:hidden}
form{background:#fff;padding:clamp(22px,4vw,38px);border:1px solid #e5edf4;border-radius:22px;width:min(100%,620px);box-shadow:0 18px 55px #0005;position:relative;z-index:1;margin:0 auto 10px}
@media(min-width:760px){form{margin:0 auto 16px}}
@media(max-width:480px){body{padding:12px 10px}form{padding:20px 16px;border-radius:18px}}

/* Logo inside form */
.logo-inside {
    display: flex;
    justify-content: center;
    margin-bottom: 10px;
}
.logo-inside img{width:112px;max-width:35vw;border-radius:50%;background:#fff;padding:8px;border:2px solid var(--blue)}

h2{text-align:center;color:var(--navy);font-size:clamp(23px,4vw,29px);margin:8px 0 6px}
.form-intro{text-align:center;color:var(--muted);font-size:14px;line-height:1.55;margin:0 auto 24px;max-width:460px}
.section-title{font-size:14px;color:var(--navy);font-weight:800;margin:24px 0 10px;padding-bottom:8px;border-bottom:1px solid var(--line)}
.field-help{display:block;color:var(--muted);font-size:12px;line-height:1.45;margin:5px 0 8px}
.account-hint{display:flex;gap:10px;align-items:flex-start;background:#eef8ff;border:1px solid #cfe9fa;border-radius:11px;padding:11px 12px;margin:8px 0 10px;color:#174c70;font-size:13px;line-height:1.45}
.account-hint strong{white-space:nowrap;color:var(--navy)}
input::placeholder,textarea::placeholder{color:#98a8b7}
input:invalid:not(:placeholder-shown){border-color:#d97777}
input:focus,select:focus,textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px #0879bd1a}

/* Labels */
label {
    display: block;
    margin-top: 14px;
    margin-bottom: 6px;
    font-weight: 700;
    font-size: 14px;
    color: #29445d;
}

/* Inputs */
input, select, textarea {
    width: 100%;
    padding: 12px;
    border-radius: 10px;
    border: 1px solid #d5e0e9;
    font-size: 15px;
    font-family: inherit;
    box-sizing: border-box;
}

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: #2f6fe0;
    box-shadow: 0 0 0 3px #e8eefc;
}

/* Account number formatting */
input[name='account_number'] {
    letter-spacing: 1px;
}

button {
    width: 100%;
    padding: 14px;
    background: var(--blue);
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    margin-top: 20px;
    font-size: 17px;
    font-weight: bold;
}
button:hover { background: #123a91; }
button:focus-visible,a:focus-visible{outline:3px solid #8bcfff;outline-offset:3px}
button:disabled{opacity:.65;cursor:not-allowed}

.message{color:#9b2525;background:#fff2f2;border:1px solid #f3cccc;border-radius:10px;padding:12px;text-align:left;margin-top:14px;font-size:14px;line-height:1.5}

/* Barangay dropdown */
.dropdown-wrapper { position: relative; }
.searchable-select { cursor: pointer; }
.dropdown-list {
    position: absolute;
    width: 100%;
    background: white;
    border: 1px solid #ccc;
    border-radius: 8px;
    max-height: 220px;
    overflow-y: auto;
    display: none;
    z-index: 999;
}
.dropdown-item {
    padding: 10px;
    cursor: pointer;
}
.dropdown-item:hover {
    background: #e8f4ff;
}
.rating-scale {
    display: flex;
    justify-content: space-between;
    gap: 6px;
    margin: 6px 0 14px;
}
.rating-option {
    flex: 1;
    text-align: center;
    cursor: pointer;
    padding: 8px 4px;
    border-radius: 10px;
    border: 1px solid #d7dbe4;
    font-size: 26px;
    transition: border-color .15s, background .15s;
}
.rating-option input { position: absolute; opacity: 0; width: 0; height: 0; }
.rating-option:has(input:checked) {
    border-color: #1a4fc4;
    background: #e8eefc;
}
.rating-option:hover { border-color: #1a4fc4; }

/* Login-page fibre background and floating logo */
#fx{position:fixed;inset:0;width:100%;height:100%;z-index:0;pointer-events:none;opacity:0;transition:opacity 1.2s ease}
#fx.on{opacity:1}
.coin-anchor{display:none;flex:none;width:clamp(120px,22vh,180px);height:clamp(120px,22vh,180px);margin:4px 0 14px;pointer-events:none;position:relative;z-index:1}
.fx-on .coin-anchor{display:block}
.fx-on .logo-inside{display:none}
@media(max-width:480px){body{padding:18px 10px 30px}.coin-anchor{width:116px;height:116px;margin-bottom:12px}form{padding:20px 16px}}
@media(prefers-reduced-motion:reduce){#fx{transition:none}}
</style>

</head>
<body>

<canvas id="fx" aria-hidden="true"></canvas>
<div class="coin-anchor" id="coinAnchor"></div>
<form method="POST">

    <div class="logo-inside">
        <img src="/SKYTRUFIBER.png" alt="SkyTruFiber Logo">
    </div>

    <h2>Customer Registration</h2>
    <p class="form-intro">Register your SkyTruFiber account and share your service feedback. Please make sure your details match your subscriber records.</p>

    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['registration_csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="source" value="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>">

    <div class="section-title">Subscriber Information</div>
    <label for="account_number">Account Number</label>
    <input id="account_number" type="text" name="account_number" value="<?= htmlspecialchars($formData['account_number'], ENT_QUOTES, 'UTF-8') ?>" minlength="13" maxlength="13"
           inputmode="numeric" autocomplete="off" pattern="187870[0-9]{3,7}"
           title="Your account number must start with 187870 and contain exactly 13 digits."
           placeholder="187870XXXXX" aria-describedby="accountHelp" required>
    <div class="account-hint" id="accountHelp"><strong>Important</strong><span>SkyTruFiber subscriber account numbers must start with <strong>187870</strong>. Enter the complete number shown on your bill or subscriber record (exactly 13 digits).</span></div>

    <label for="full_name">Full Name</label>
    <input type="text" name="full_name" value="<?= htmlspecialchars($formData['full_name'], ENT_QUOTES, 'UTF-8') ?>" maxlength="120" autocomplete="name" placeholder="Enter full name" required>

    <label for="email">Email Address</label>
    <input type="email" name="email" value="<?= htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8') ?>" maxlength="254" autocomplete="email" placeholder="example@email.com" required>

    <div class="section-title">Service Location</div>
    <label for="district">District</label>
    <select id="district" name="district" required>
        <option value="">Select District</option>
        <?php for ($districtNumber = 1; $districtNumber <= 6; $districtNumber++): $districtOption = 'District ' . $districtNumber; ?>
            <option value="<?= $districtOption ?>" <?= $formData['district'] === $districtOption ? 'selected' : '' ?>><?= $districtOption ?></option>
        <?php endfor; ?>
    </select>

    <label for="barangaySelector">Barangay</label>
    <span class="field-help">Choose your district first, then search and select your barangay from the list.</span>
    <div class="dropdown-wrapper">
        <input type="text" id="barangaySelector" class="searchable-select" value="<?= htmlspecialchars($formData['location'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Search or select barangay..." autocomplete="off" aria-label="Search barangay">
        <input type="hidden" id="location" name="location" value="<?= htmlspecialchars($formData['location'], ENT_QUOTES, 'UTF-8') ?>" required>
        <div id="dropdownList" class="dropdown-list"></div>
    </div>

    <label for="date_installed">Installation Date</label>
    <input type="date" id="date_installed" name="date_installed" value="<?= htmlspecialchars($formData['date_installed'], ENT_QUOTES, 'UTF-8') ?>" max="<?= date('Y-m-d') ?>" required>

    <div class="section-title">Service Feedback</div>
    <label>How satisfied are you with your service?</label>
    <div class="rating-scale" role="radiogroup" aria-label="Satisfaction rating">
        <?php $ratingFaces = [1 => '😡', 2 => '😕', 3 => '😐', 4 => '🙂', 5 => '😍']; ?>
        <?php foreach ($ratingFaces as $val => $face): ?>
            <label class="rating-option">
                <input type="radio" name="rating" value="<?= $val ?>" <?= $formData['rating'] === (string)$val ? 'checked' : '' ?> required>
                <span><?= $face ?></span>
            </label>
        <?php endforeach; ?>
    </div>

    <label for="remarks">Feedback / Comments <span style="font-weight:400;color:#718397">(Optional)</span></label>
    <textarea name="remarks" maxlength="2000" placeholder="Your feedback helps us improve"><?= htmlspecialchars($formData['remarks'], ENT_QUOTES, 'UTF-8') ?></textarea>

    <button type="submit" id="submitRegistration">Submit Registration</button>

    <?php if ($message): ?>
        <p class="message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <p style="text-align:center;">Already registered?
        <a href="/fiber">Login here</a>
    </p>

</form>

<script>
/* ---------- ACCOUNT NUMBER VALIDATION ---------- */
const accountInput=document.getElementById("account_number");
accountInput.addEventListener("input",()=>{
    accountInput.value=accountInput.value.replace(/\D/g,"").slice(0,13);
    accountInput.setCustomValidity("");
});
accountInput.addEventListener("invalid",()=>{
    if(accountInput.value && !/^187870[0-9]{7}$/.test(accountInput.value)){
        accountInput.setCustomValidity("Incorrect account number. SkyTruFiber subscriber numbers must start with 187870 and contain exactly 13 digits.");
    }
});
accountInput.addEventListener("input",()=>accountInput.setCustomValidity(""));

/* ---------- BARANGAY SEARCH SYSTEM ---------- */
const barangays = {
  "District 1": ["Alicia","Bagong Pag-asa","Bahay Toro","Balingasa","Bungad","Damar","Damayan","Del Monte","Katipunan","Lourdes","Maharlika","Manresa","Mariblo","Masambong","N.S. Amoranto (Gintong Silahis)","Nayong Kanluran","Paang Bundok","Pag-ibig sa Nayon","Paltok","Paraiso","Phil-Am","Project 6","Ramon Magsaysay","Saint Peter","Salvacion","San Antonio","San Isidro Labrador","San Jose","Santa Cruz","Santa Teresita","Sto. Cristo","Santo Domingo (Matalahib)","Siena","Talayan","Vasra","Veterans Village","West Triangle"],
  "District 2": ["Bagong Silangan","Batasan Hills","Commonwealth","Holy Spirit","Payatas"],
  "District 3": ["Amihan","Bagumbayan","Bagumbuhay","Bayanihan","Blue Ridge A","Blue Ridge B","Camp Aguinaldo","Claro (Quirino 3-B)","Dioquino Zobel","Duyan-duyan","E. Rodriguez","East Kamias","Escopa I","Escopa II","Escopa III","Escopa IV","Libis","Loyola Heights","Mangga","Marilag","Masagana","Matandang Balara","Milagrosa","Pansol","Quirino 2-A","Quirino 2-B","Quirino 2-C","Quirino 3-A","St. Ignatius","San Roque","Silangan","Socorro","Tagumpay","Ugong Norte","Villa Maria Clara","West Kamias","White Plains"],
  "District 4": ["Bagong Lipunan ng Crame","Botocan","Central","Damayang Lagi","Don Manuel","Doña Aurora","Doña Imelda","Doña Josefa","Horseshoe","Immaculate Concepcion","Kalusugan","Kamuning","Kaunlaran","Kristong Hari","Krus na Ligas","Laging Handa","Malaya","Mariana","Obrero","Old Capitol Site","Paligsahan","Pinagkaisahan","Pinyahan","Roxas","Sacred Heart","San Isidro Galas","San Martin de Porres","San Vicente","Santol","Sikatuna Village","South Triangle","Sto. Niño","Tatalon","Teacher's Village East","Teacher's Village West","U.P. Campus","U.P. Village","Valencia","San Juan","San Roque","Santa Mesa Heights"],
  "District 5": ["Bagbag","Capri","Fairview","Greater Lagro","Gulod","Kaligayahan","Nagkaisang Nayon","North Fairview","Novaliches Proper","Pasong Putik Proper","San Agustin","San Bartolome","Sta. Lucia","Sta. Monica"],
  "District 6": ["Apolonio Samson","Baesa","Balong Bato","Culiat","New Era","Pasong Tamo","Sangandaan","Sauyo","Talipapa","Tandang Sora","Unang Sigaw"]
};

const districtSelect = document.getElementById("district");
const searchInput = document.getElementById("barangaySelector");
const dropdownList = document.getElementById("dropdownList");
const hiddenBarangay = document.getElementById("location");

function populateDropdown(filter) {
    dropdownList.innerHTML = "";
    const district = districtSelect.value;
    if (!barangays[district]) return;

    barangays[district]
        .filter(b => b.toLowerCase().includes(filter))
        .forEach(brgy => {
            let div = document.createElement("div");
            div.className = "dropdown-item";
            div.textContent = brgy;
            div.onclick = () => {
                searchInput.value = brgy;
                hiddenBarangay.value = brgy;
                dropdownList.style.display = "none";
            };
            dropdownList.appendChild(div);
        });
}

/* Show dropdown */
searchInput.addEventListener("focus", () => {
    populateDropdown("");
    dropdownList.style.display = "block";
});

/* Live search */
searchInput.addEventListener("input", () => {
    hiddenBarangay.value = "";
    populateDropdown(searchInput.value.toLowerCase());
    dropdownList.style.display = "block";
});

/* Close when clicking outside */
document.addEventListener("click", (e) => {
    if (!dropdownList.contains(e.target) && e.target !== searchInput) {
        dropdownList.style.display = "none";
    }
});

/* Restore selected barangay after a validation error. */
if (districtSelect.value && hiddenBarangay.value) {
    populateDropdown("");
    searchInput.value = hiddenBarangay.value;
}

/* Clear barangay whenever the district changes. */
districtSelect.addEventListener("change", () => {
    searchInput.value = "";
    hiddenBarangay.value = "";
    dropdownList.style.display = "none";
});

/* Prevent accidental double submission and give immediate feedback. */
document.querySelector("form").addEventListener("submit", (event) => {
    if (!hiddenBarangay.value) {
        event.preventDefault();
        searchInput.setCustomValidity("Please select a barangay from the list.");
        searchInput.reportValidity();
        searchInput.addEventListener("input", () => searchInput.setCustomValidity(""), { once: true });
        return;
    }
    const submitButton = document.getElementById("submitRegistration");
    submitButton.disabled = true;
    submitButton.textContent = "Submitting…";
});
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