<?php
    require 'Conexion/db.php';
    
    session_start();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    session_regenerate_id(true);

    if(!isset($_SESSION['login_attempts'])){
        $_SESSION['login_attempts'] = 0;
    }

    if(!isset($_SESSION['last_attempt'])){
        $_SESSION['last_attempt'] = time();
    }

    if($_SESSION['login_attempts'] >= 5){
        if(time() - $_SESSION['last_attempt'] < 60){
            $errorMsg[] = "Demasiados intentos. Espera 1 minuto.";
        }
        else{
            $_SESSION['login_attempts'] = 0;
        }
    }

    if(isset($_POST['Blogin'])){
        if(!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])){
            $errorMsg[] = "Error de seguridad. Recarga la página.";
        }
        else{
            $email = trim($_POST['email']);
            $password = trim($_POST['password']);

            if(empty($email) || empty($password)){
                $errorMsg[] = "No puede haber campos vacíos";
            }
            else{
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
                $stmt->execute(['email'=>$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if($user){
                    if(password_verify($password,$user['password'])){
                        session_regenerate_id(true);

                        $_SESSION["user_login"] = $user["id"];
                        $_SESSION["role"] = $user["role"];
                        
                        if($user["passwordN"]== 0){
                            header("Location: cambio.php");
                            exit;
                        }
                        else{
                            header("Location: Portal/dashboard.php");
                            exit;
                        }                       
                    }
                    else{
                        $errorMsg[] = "Contraseña incorrecta";
                    }
                }
                else{
                    $errorMsg[] = "El usuario no existe";
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

        <title>Iniciar sesión</title>

        <link rel="icon" href="assets/img/logo.png">

        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <link rel="stylesheet" href="assets/css/login.css">
    </head>

    <body class="login-body">
        <div class="login-wrapper">
            <form method="POST" autocomplete="off" class="login-card">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="login-header">
                    <img src="assets/img/icono.png">
                    <p>Accede a tu plataforma educativa</p>
                </div>

                <div class="input-box">
                    <input type="email" name="email" required maxlength="100">
                    <label>Correo electrónico</label>
                </div>

                <div class="input-box">
                    <input type="password" name="password" required maxlength="60">
                    <label>Contraseña</label>
                </div>

                <button type="submit" name="Blogin" class="login-btn">
                    Iniciar sesión
                </button>

                <div class="login-footer">
                    <a href="#">¿Olvidaste tu contraseña?</a>
                </div>

                <?php
                    if(isset($errorMsg)){
                        foreach ($errorMsg as $error){
                ?>
                    <script>
                        Swal.fire({
                            icon:"error",
                            text:"<?= htmlspecialchars($error) ?>"
                        });
                    </script>
                <?php
                        }
                    }   
                ?>
            </form>
        </div>
    </body>
</html>