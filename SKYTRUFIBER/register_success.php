<?php
// Simple success page – no DB needed
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Registration Successful</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: linear-gradient(to bottom right, #cceeff, #e6f7ff);
    margin: 0;
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}

.box {
    width: 520px;
    max-width: 92%;
    background: white;
    padding: 40px;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    animation: fadeIn 0.6s ease-out;
}

.check-icon {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    border: 4px solid #2ecc71;
    color: #2ecc71;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 48px;
    margin: 0 auto 20px auto;
}

button {
    padding: 12px 25px;
    background: #0099cc;
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    margin-top: 20px;
    font-size: 16px;
}
button:hover {
    background: #007a99;
}

a {
    display: block;
    margin-top: 15px;
    color: #0077a3;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<script>
// Auto redirect after 2 seconds
setTimeout(() => {
    window.location.href = "/fiber";
}, 2000);
</script>

</head>
<body>

<div class="box">
    <div class="check-icon">✔</div>

    <h2>Your response was submitted</h2>

    <p>You have successfully completed your registration.</p>
    <p>You may now browse the customer portal.</p>

    <button onclick="window.location.href='/fiber'">Go to Customer Portal</button>
</div>

</body>
</html>
