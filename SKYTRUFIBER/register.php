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

</body>
</html>