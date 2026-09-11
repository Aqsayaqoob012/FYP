<?php
/* DATABASE CONNECTION */
$host = "localhost";
$user = "root";
$pass = "";
$db   = "monitor_ai";
$conn = mysqli_connect($host, $user, $pass, $db);

session_start();
$msg = ""; $msg_type = "";

/* USER REGISTRATION & DATABASE STORAGE */
if (isset($_POST['signup_action'])) {
    $name = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = trim(mysqli_real_escape_string($conn, $_POST['email']));
    
    $raw_pass = trim($_POST['signup_password']);
    $password = password_hash($raw_pass, PASSWORD_DEFAULT);
    
    // JSON secure
    $face_data = mysqli_real_escape_string($conn, $_POST['face_data_json']); 

    $check = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");
    if (mysqli_num_rows($check) > 0) {
        $msg = "Email already registered.";
        $msg_type = "err";
    } else {
        $sql = "INSERT INTO users (fullname, email, password, face_data) VALUES ('$name', '$email', '$password', '$face_data')";
        if (mysqli_query($conn, $sql)) {
            $msg = "Account & Biometrics saved successfully! You can now Login.";
            $msg_type = "ok";
        } else {
            $msg = "Database Error: " . mysqli_error($conn);
            $msg_type = "err";
        }
    }
}

/* LOGIN LOGIC (STRICT 2-FACTOR: PASSWORD + 3-STEP FACE) */
if (isset($_POST['login_action'])) {
    $email = trim(mysqli_real_escape_string($conn, $_POST['email']));
    
    // 🔥 FIX 2: Login password ka name alag kiya aur trim lagaya
    $raw_pass = trim($_POST['login_password']); 
    $face_verified = isset($_POST['face_verified']) ? $_POST['face_verified'] : '0';

    $result = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    $user = mysqli_fetch_assoc($result);

    if ($user) {
        if ($face_verified == '1') {
            if (password_verify($raw_pass, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['fullname'];
                $_SESSION['user_email'] = $user['email'];
                header("Location: exam.php");
                exit();
            } else {
                $hash_len = strlen($user['password']);
                $msg = "Face Verified ✅ BUT Password Incorrect ❌ (DB Hash Length: $hash_len)";
                $msg_type = "err";
            }
        } else {
            $msg = "Face not verified by AI! Access Denied.";
            $msg_type = "err";
        }
    } else {
        $msg = "User not found. Please register first.";
        $msg_type = "err";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Monitor AI - Secure Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet" />
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    :root{ --bg:#03060f;--surface:#0d1425; --border:rgba(80,140,255,.2);--accent:#3b82f6;--accent2:#60a5fa; --text:#e8edf8;--muted:#7a8aaa;--err:#ef4444; }
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;overflow-x:hidden}
    h1{font-family:'Syne',sans-serif}
    #bg-canvas{position:fixed;inset:0;z-index:0;pointer-events:none}
    .grid-overlay{position:fixed;inset:0;z-index:0;pointer-events:none; background-image:linear-gradient(rgba(59,130,246,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(59,130,246,.04) 1px,transparent 1px); background-size:60px 60px;mask-image:radial-gradient(ellipse 80% 80% at 50% 50%,black 20%,transparent 100%)}
    .login-container{position:relative;z-index:10;height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
    .login-card{background:rgba(13,20,37,.92);border:1px solid var(--border);border-radius:16px;backdrop-filter:blur(20px);padding:1.5rem 1.8rem;width:100%;max-width:420px;box-shadow:0 32px 64px rgba(0,0,0,.4)}
    .logo-section{text-align:center;margin-bottom:1rem}
    .logo-img{width:56px;height:56px;border-radius:50%;object-fit:cover;margin-bottom:8px;border:2px solid rgba(80,140,255,.3);box-shadow:0 6px 20px rgba(59,130,246,.2)}
    .logo-heading{font-size:1.35rem;font-weight:800;letter-spacing:-.3px}
    .logo-heading span{color:var(--accent2)}
    .tabs{display:flex;margin-bottom:1.2rem;border-bottom:1px solid rgba(80,140,255,.2)}
    .tab{flex:1;padding:.6rem 0;border:none;background:none;color:rgba(232,237,248,.4);font-family:'Syne',sans-serif;font-size:.82rem;font-weight:600;cursor:pointer;border-bottom:2px solid transparent}
    .tab.active{color:#e8edf8;border-bottom-color:var(--accent)}
    .form{display:none}.form.active{display:block}
    .form-group{margin-bottom:.85rem}
    .form-group label{display:block;font-size:.76rem;font-weight:500;color:rgba(232,237,248,.7);margin-bottom:.25rem}
    .form-group input{width:100%;background:rgba(13,20,37,.8);border:1px solid rgba(80,140,255,.25);border-radius:8px;padding:.6rem .8rem;color:#e8edf8;outline:none}
    .btn-primary{width:100%;background:linear-gradient(135deg,#3b82f6 0%,#60a5fa 100%);color:white;border:none;padding:.7rem;border-radius:8px;font-weight:600;cursor:pointer; margin-bottom: 8px;}
    .toast{position:fixed;top:20px;right:20px;z-index:999;padding:10px 16px;border-radius:8px;font-size:.78rem;font-weight:600;color:#fff;transform:translateX(120%);transition:transform .3s}
    .toast.show{transform:translateX(0)}
    .toast-ok{background:#10b981}.toast-err{background:#ef4444}
    
    .instruction-box { background: rgba(59,130,246,0.1); border: 2px solid var(--accent); border-radius: 8px; padding: 15px; margin-bottom: 15px; text-align: center; }
    .active-instruction { color: var(--accent2); font-weight: 800; font-size: 1.2rem; }
    .camera-wrapper { width: 100%; height: 220px; background: #000; border-radius: 12px; overflow: hidden; border: 1px solid var(--border); margin-bottom: 15px; position:relative; }
    .video-feed { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
  </style>
</head>
<body>

  <canvas id="bg-canvas"></canvas>
  <div class="grid-overlay"></div>

  <div class="login-container">
    <div class="login-card">
      <div class="logo-section">
        <img class="logo-img" src="https://z-cdn-media.chatglm.cn/files/d14597eb-c852-4886-9f40-02e45359a007.jpg" alt="Monitor AI" />
        <h1 class="logo-heading">Monitor <span>AI</span></h1>
      </div>

      <div class="tabs">
        <button class="tab active" data-tab="login">Sign In</button>
        <button class="tab" data-tab="signup">Create Account</button>
      </div>

      <form id="loginForm" class="form active" method="POST" onsubmit="event.preventDefault(); startFaceLogin();">
        <div id="login-fields">
            <div class="form-group"><label>Email</label><input type="email" name="email" id="l-email" required /></div>
            <div class="form-group"><label>Password</label><input type="password" name="login_password" id="l-pass" required /></div>
            
            <button type="submit" class="btn-primary">Next: Verify Face & Login</button>
        </div>

        <div id="login-biometric-flow" style="display:none;">
            <div class="instruction-box">
                <p id="login-instruction" class="active-instruction">Camera Starting...</p>
            </div>
            <div class="camera-wrapper">
                <video id="login-video-feed" class="video-feed" autoplay muted playsinline></video>
            </div>
            
            <input type="hidden" name="face_verified" id="face_verified" value="0" />
            <input type="hidden" name="login_action" value="1" />
        </div>
      </form>

      <form id="signupForm" class="form" method="POST" onsubmit="event.preventDefault(); startSignupCameraPhase();">
        <div id="registration-fields">
            <div class="form-group"><label>Full Name</label><input type="text" name="fullname" id="s-name" required /></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" id="s-email" required /></div>
            <div class="form-group"><label>Password</label><input type="password" name="signup_password" id="s-pass" required /></div>
            
            <button type="submit" class="btn-primary">Next: Face Registration</button>
        </div>

        <div id="signup-biometric-flow" style="display:none;">
            <div class="instruction-box">
                <p id="signup-instruction" class="active-instruction">Camera Starting...</p>
            </div>
            <div class="camera-wrapper">
                <video id="signup-video-feed" class="video-feed" autoplay muted playsinline></video>
            </div>
            
            <input type="hidden" name="face_data_json" id="face_data_json" />
            <input type="hidden" name="signup_action" value="1" />
        </div>
      </form>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script>
    document.querySelectorAll('.tab').forEach(t => t.addEventListener('click', function() {
        document.querySelectorAll('.tab, .form').forEach(el => el.classList.remove('active'));
        this.classList.add('active');
        document.getElementById(this.dataset.tab + 'Form').classList.add('active');
    }));

    function toast(m,t){
      const el=document.getElementById('toast'); el.textContent=m;
      el.className=`toast toast-${t} show`;
      setTimeout(()=>el.classList.remove('show'),3000);
    }

    function captureCanvas(videoEl) {
        const canvas = document.createElement('canvas');
        canvas.width = 320; canvas.height = 240;
        canvas.getContext('2d').drawImage(videoEl, 0, 0, 320, 240);
        return canvas.toDataURL('image/jpeg');
    }

    /* ================= LOGIN ================= */
    let isLoginProcessing = false;
    let loginChallenges = []; 
    let currentLoginStep = 0; 
    
    function getThreeRandomAngles() {
        let allAngles = ["Left", "Right", "Up", "Down", "Forward"];
        return allAngles.sort(() => 0.5 - Math.random()).slice(0, 3);
    }

    async function startFaceLogin() {
        const email = document.getElementById('l-email').value;
        const pass = document.getElementById('l-pass').value;

        if (!email || !pass) return toast("Please enter BOTH Email and Password!", "err");

        document.getElementById('login-fields').style.display = 'none';
        document.getElementById('login-biometric-flow').style.display = 'block';

        loginChallenges = getThreeRandomAngles();
        currentLoginStep = 0;
        
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            document.getElementById('login-video-feed').srcObject = stream;
            processLoginFrame();
        } catch (e) { toast("Camera Access Denied", "err"); }
    }

    async function processLoginFrame() {
        if (isLoginProcessing) return;
        isLoginProcessing = true;

        if (currentLoginStep >= 3) {
            toast("✅ Verification Complete!", "ok");
            document.getElementById('login-instruction').textContent = "Logging you in...";
            document.getElementById('login-video-feed').srcObject.getTracks().forEach(t => t.stop());
            
            document.getElementById('face_verified').value = '1';
            document.getElementById('loginForm').submit();
            return;
        }

        const currentTarget = loginChallenges[currentLoginStep];
        document.getElementById('login-instruction').textContent = `Step ${currentLoginStep + 1}/3: Look ${currentTarget}`;

        const frame = captureCanvas(document.getElementById('login-video-feed'));
        const email = document.getElementById('l-email').value;

        try {
            const response = await fetch('http://localhost:5000/verify_login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image: frame, target: currentTarget, email: email })
            });
            const data = await response.json();

            if (data.status === "success") {
                toast(`✅ ${currentTarget} Verified!`, "ok");
                currentLoginStep++; 
                isLoginProcessing = false;
                setTimeout(processLoginFrame, 1000); 
            } 
            else if (data.status === "failed" || data.status === "error") {
                toast("❌ " + data.message, "err");
                document.getElementById('login-instruction').textContent = "Face Verification Failed.";
                document.getElementById('login-video-feed').srcObject.getTracks().forEach(t => t.stop());
                
                setTimeout(() => {
                    document.getElementById('login-fields').style.display = 'block';
                    document.getElementById('login-biometric-flow').style.display = 'none';
                    isLoginProcessing = false;
                }, 3000);
            } 
            else {
                isLoginProcessing = false;
                setTimeout(processLoginFrame, 500);
            }
        } catch (e) {
            toast("Error: Python Backend not running!", "err");
            isLoginProcessing = false;
        }
    }

    /* ================= SIGNUP ================= */
    const steps = [
        { target: "Forward", text: "Look Forward"},
        { target: "Left", text: "Look Left" },
        { target: "Right", text: "Look Right" },
        { target: "Up", text: "Look Up" },
        { target: "Down", text: "Look Down" }
    ];
    let currentStep = 0;
    let embeddingsDB = {}; 

    async function startSignupCameraPhase() {
        const email = document.getElementById('s-email').value;
        if (!email || !document.getElementById('s-pass').value) return toast("Please fill the details first", "err");

        document.getElementById('registration-fields').style.display = 'none';
        document.getElementById('signup-biometric-flow').style.display = 'block';

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            document.getElementById('signup-video-feed').srcObject = stream;
            processSignupFrame(); 
        } catch (e) { toast("Camera Access Denied", "err"); }
    }

    async function processSignupFrame() {
        if (currentStep >= steps.length) {
            document.getElementById('signup-instruction').textContent = "All Done ✅";
            document.getElementById('signup-video-feed').srcObject.getTracks().forEach(t => t.stop()); 
            
            document.getElementById('face_data_json').value = JSON.stringify(embeddingsDB);
            document.getElementById('signupForm').submit();
            return;
        }

        const currentTask = steps[currentStep];
        document.getElementById('signup-instruction').textContent = currentTask.text;

        const frame = captureCanvas(document.getElementById('signup-video-feed'));
        const email = document.getElementById('s-email').value;

        try {
            const response = await fetch('http://localhost:5000/process_frame', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image: frame, target: currentTask.target, email: email })
            });
            const data = await response.json();

            if (data.status === "match") {
                toast(`${currentTask.text} - ✅ SUCCESS!`, "ok");
                embeddingsDB[currentTask.target] = data.embedding;
                currentStep++;
                setTimeout(processSignupFrame, 1000); 
            } else {
                setTimeout(processSignupFrame, 300);
            }
        } catch (e) {
            document.getElementById('signup-instruction').textContent = "Error: Python Backend not running!";
        }
    }

    var cv=document.getElementById('bg-canvas'),cx=cv.getContext('2d'),W,H,pts=[];
    function rsz(){W=cv.width=innerWidth;H=cv.height=innerHeight}
    function init(){pts=[];for(var i=0;i<80;i++)pts.push({x:Math.random()*W,y:Math.random()*H,r:Math.random()*1.2,vx:(Math.random()-.5)*.3,vy:(Math.random()-.5)*.3,o:Math.random()*.5})}
    function draw(){
      cx.clearRect(0,0,W,H);
      for(var i=0;i<pts.length;i++){
        var p=pts[i]; cx.beginPath(); cx.arc(p.x,p.y,p.r,0,Math.PI*2); cx.fillStyle=`rgba(96,165,250,${p.o})`; cx.fill();
        p.x+=p.vx; p.y+=p.vy; if(p.x<0)p.x=W; if(p.x>W)p.x=0; if(p.y<0)p.y=H; if(p.y>H)p.y=0;
      }
      requestAnimationFrame(draw);
    }
    addEventListener('resize',()=>{rsz();init();}); rsz();init();draw();

    <?php if($msg): ?> toast("<?= $msg ?>", "<?= $msg_type ?>"); <?php endif; ?>
  </script>
</body>
</html>