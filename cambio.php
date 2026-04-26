<?php
    require "Conexion/db.php";

    ini_set('session.cookie_httponly',1);
    ini_set('session.use_only_cookies',1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

    session_start();

    if(!isset($_SESSION['initiated'])){
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }

    $timeout = 900;

    if(isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)){
        session_unset();
        session_destroy();
        header("Location: ../no_admin.php");
        exit();
    }

    $_SESSION['LAST_ACTIVITY'] = time();

    if(empty($_SESSION['csrf_token'])){
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin");

    if(!isset($_SESSION['user_login'])){
        header("Location: ../no_admin.php");
        exit();
    }

    $id = $_SESSION['user_login'];

    $select_stmt = $pdo->prepare("SELECT email FROM users WHERE id = :uid");
    $select_stmt->execute([":uid"=>$id]);
    $row = $select_stmt->fetch(PDO::FETCH_ASSOC);
    if(isset($_SESSION['user_login'])){
        if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Glogin'])){
            if(!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])){
                $errorMsg[] = "Error de seguridad. Recarga la página.";
            }
            else{
                $password = trim($_POST['password']);

                if(strlen($password) < 8){
                    $errorMsg[] = "La contraseña debe tener mínimo 8 caracteres.";
                }

                if(!preg_match('/[A-Z]/',$password)){
                    $errorMsg[] = "Debe contener una letra mayúscula.";
                }

                if(!preg_match('/[0-9]/',$password)){
                    $errorMsg[] = "Debe contener un número.";
                }

                if(!isset($errorMsg)){
                    $new_password = password_hash($password, PASSWORD_DEFAULT);

                    try{
                        $stmt = $pdo->prepare("UPDATE users SET password = :password, passwordN = 1 WHERE id = :uid");

                        $stmt->execute([
                            'password'=>$new_password,
                            'uid'=>$id
                        ]);

                        session_regenerate_id(true);
                        header("Location: Portal/dashboard.php?ok=1");
                        exit();
                    }
                    catch(PDOException $e){
                        $errorMsg[] = "Error: ".$e->getMessage();
                    }

                }
            }
        }
?>
    <!DOCTYPE html>
    <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width,initial-scale=1">

            <title>Cambiar contraseña</title>

            <link rel="icon" href="assets/img/logo.jpg">
            <link rel="stylesheet" href="assets/css/login.css">

            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>

        <body class="login-body">
            <div class="login-wrapper">
                <form method="POST" class="login-card" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="login-header">
                        <h2>Cambiar contraseña</h2>
                        <p><?php echo htmlspecialchars($row['email']); ?></p>
                    </div>
                    <div class="input-box">
                        <input type="password" id="password" name="password" required>
                        <label>Nueva contraseña</label>
                        <span class="toggle-pass" onclick="togglePassword()">👁</span>
                    </div>
                    <div class="password-strength">
                        <div id="strength-bar"></div>
                    </div>

                    <small id="strength-text"></small>

                    <button type="submit" name="Glogin" class="login-btn">
                        Guardar contraseña
                    </button>
                </form>
            </div>

            <?php
                if(isset($errorMsg)){
            ?>
                <script>
                    Swal.fire({
                        icon:"error",
                        text:"<?= htmlspecialchars($errorMsg[0]) ?>"
                    });
                </script>
            <?php
                }
            ?>

            <script>
                function togglePassword(){

                    const pass = document.getElementById("password");
                    if(pass.type === "password"){
                        pass.type="text";
                    }else{
                        pass.type="password";
                    }
                }

                const password = document.getElementById("password");
                const bar = document.getElementById("strength-bar");
                const text = document.getElementById("strength-text");

                password.addEventListener("input",function(){
                    let value = password.value;
                    let strength = 0;

                    if(value.length >= 8) strength++;
                    if(value.match(/[A-Z]/)) strength++;
                    if(value.match(/[0-9]/)) strength++;
                    if(value.match(/[^A-Za-z0-9]/)) strength++;

                    switch(strength){
                        case 1:
                            bar.style.width="25%";
                            bar.style.background="red";
                            text.innerText="Contraseña débil";
                        break;

                        case 2:
                            bar.style.width="50%";
                            bar.style.background="orange";
                            text.innerText="Contraseña media";
                        break;

                        case 3:
                            bar.style.width="75%";
                            bar.style.background="gold";
                            text.innerText="Contraseña buena";
                        break;

                        case 4:
                            bar.style.width="100%";
                            bar.style.background="green";
                            text.innerText="Contraseña segura";
                        break;

                        default:
                            bar.style.width="0%";
                            text.innerText="";
                    }
                });

                history.pushState(null,null,location.href);
                window.onpopstate=function(){
                    window.location.href="logout.php";
                };
            </script>
        </body>
    </html>
<?php
    }
?>