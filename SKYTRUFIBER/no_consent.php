<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Consent Declined – SkyTruFiber</title>

<style>
body {
  margin:0;
  background:linear-gradient(to bottom right, #cceeff, #e6f7ff);
  font-family:"Segoe UI", Arial, sans-serif;
  display:flex; 
  justify-content:center; 
  align-items:center;
  min-height:100vh; 
  text-align:center;
}

.container {
  background:white;
  padding:40px 45px;
  border-radius:15px;
  width:600px;
  max-width:92%;
  box-shadow:0 4px 12px rgba(0,0,0,0.18);
  animation:fade .6s ease-out;
}

@keyframes fade { 
  from { opacity:0; } 
  to { opacity:1; } 
}

.checkmark {
  width:80px;
  height:80px;
  border-radius:50%;
  border:4px solid #28a745;
  display:flex;
  justify-content:center;
  align-items:center;
  margin:0 auto 15px auto;
  animation:pop .5s ease-out;
}

@keyframes pop { 
  from { transform:scale(0.6); opacity:0; } 
  to { transform:scale(1); opacity:1; } 
}

.checkmark svg {
  width:45px;
  stroke:#28a745;
  stroke-width:5;
  stroke-linecap:round;
  stroke-linejoin:round;
  fill:none;
  stroke-dasharray:45;
  stroke-dashoffset:45;
  animation:draw .8s .2s forwards ease-in-out;
}

@keyframes draw { 
  to { stroke-dashoffset:0; } 
}

.btn {
  margin-top:15px;
  padding:12px 25px;
  background:#0099cc;
  color:white;
  border:none;
  border-radius:8px;
  font-size:15px;
  cursor:pointer;
  margin-bottom:8px;
  transition:.3s;
}

.btn:hover { background:#007a99; }

.linkbtn {
  display:block;
  margin-top:10px;
  font-weight:bold;
  color:#004466;
  text-decoration:none;
}
.linkbtn:hover { text-decoration:underline; }
</style>
</head>

<body>
<div class="container">

  <div class="checkmark">
    <svg viewBox="0 0 50 50">
      <polyline points="12,28 22,38 38,14"/>
    </svg>
  </div>

  <h2 style="color:#006600;">Your response was submitted</h2>

  <p style="font-size:15px;">
    You have chosen <b>not to give consent</b> for data processing.<br><br>
    We respect your decision.  
    You may still browse public information, but<br>
    <b>customer portal access requires data consent</b> for account verification.
  </p>

  <button class="btn" onclick="window.print()">🖨 Print Confirmation</button>

  <!-- Clean route → encrypted automatically by index.php -->
  <a href="/fiber" class="linkbtn">
    ⬅ Go back to Customer Portal
  </a>

</div>
</body>
</html>
