<?php
    require(__DIR__ . "/../../Conexion/db.php");

    session_start();

    if(empty($_SESSION['csrf_token'])){
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");

    if(!isset($_SESSION['user_login'])){
        header("Location:../../no_admin.php");
        exit();
    }

    $id = $_SESSION['user_login'];

    $select_stmt = $pdo->prepare("SELECT * FROM users WHERE id = :uid");
    $select_stmt->execute([":uid"=>$id]);
    $row = $select_stmt->fetch(PDO::FETCH_ASSOC);

    $rolesPermitidos = ['admin'];

    if(!in_array($row['role'], $rolesPermitidos)){
        header("Location: no_admin.php");
        exit();
    }

    if(isset($_SESSION['user_login'])){
        $select_stmt1 = $pdo->prepare("SELECT * FROM perfil WHERE iduser = :uid");
        $select_stmt1->execute([":uid"=>$id]);
        $row1 = $select_stmt1->fetch(PDO::FETCH_ASSOC);

        $rol = $_POST['rol'] ?? 0;
        
        if($rol==0){
            $role = 'estudiante';
        }
        else if($rol==1){
            $role = 'admin';
        }
        else if($rol==2){
            $role = 'estudiante';
        }
        else if($rol==3){
            $role = 'profesor';
        }
    
        $pass = $_POST['pass'] ?? 0;
        $inst = $_POST['inst'] ?? 0;

        if(isset($_POST['Usuarios'])){ 
            if(!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])){
                $errorMsg[] = "Error de seguridad. Recarga la página.";
            }
            else{
                $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

                if(!$email){
                    $errorMsg[] = "Correo inválido";
                }
                else{
                    $idcarrera = $_POST['idcarrera'];
                    $idcuatrimestre = $_POST['idcuatrimestre'];
                    $nombre      = htmlspecialchars(trim($_POST['nombre']));
                    $apellido    = htmlspecialchars(trim($_POST['apellido']));
                    $sobrenombre = htmlspecialchars(trim($_POST['sobrenombre']));
                    $anio_graduacion = $_POST['anio_graduacion'] ?? null;
                    $matricula       = $_POST['matricula'] ?? null;
                    $email1 = htmlspecialchars(trim($_POST['email1']));
                    $telefono       = htmlspecialchars($_POST['telefono']);
                    $telefono_movil = htmlspecialchars($_POST['telefono_movil']);
                    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
                    $sexo             = $_POST['sexo'] ?? null;
                    $calle  = htmlspecialchars($_POST['calle']);
                    $calle2 = htmlspecialchars($_POST['calle2']);
                    $ciudad = htmlspecialchars($_POST['ciudad']);
                    $estado = htmlspecialchars($_POST['estado']);
                    $codigo_postal = filter_var($_POST['codigo_postal'], FILTER_SANITIZE_NUMBER_INT);
                    $pais          = htmlspecialchars($_POST['pais']);
                    $role = $_POST['roles'] ?? 'estudiante';
                    $pass = $_POST['pass'] ?? 0;

                    if($pass == 1){
                        $password = $_POST['password'] ?? 'Fl3m1g1234t';
                    }
                    else{
                        $password = 'Fl3m1g1234t';
                    }

                    $new_password = password_hash($password, PASSWORD_DEFAULT);
                    $inst = $_POST['inst'] ?? null;

                    try{
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $pdo->beginTransaction();

                        $check = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                        $check->execute(['email'=>$email]);

                        if($check->rowCount() > 0){
                            $errorMsg[] = "El correo ya existe";
                        }
                        else{
                            $Usuarios = $pdo->prepare("INSERT INTO users(email,password,passwordN,instruccion,role)
                                                        VALUES(:email,:password,:passwordN,:instruccion,:role)");

                            $Usuarios->execute([
                                'email'=>$email,
                                'password'=>$new_password,
                                'passwordN'=>$pass,
                                'instruccion'=>$inst,
                                'role'=>$role
                            ]);

                            $idu = $pdo->lastInsertId();

                            $Usuarios1 = $pdo->prepare("INSERT INTO perfil(iduser,idcarrera,idcuatrimestre,nombre,apellido,name,aniog,fechan,matricula,genero,email,telefonoc,telefonom,calle1,calle2,ciudad,estado,cp,pais)
                                                        VALUES(:iduser,:idcarrera,:idcuatrimestre,:nombre,:apellido,:name,:aniog,:fechan,:matricula,:genero,:email,:telefonoc,:telefonom,:calle1,:calle2,:ciudad,:estado,:cp,:pais)");

                            $Usuarios1->execute([
                                'iduser'=>$idu,
                                'idcarrera'=>$idcarrera,
                                'idcuatrimestre'=>$idcuatrimestre,
                                'nombre'=>$nombre,
                                'apellido'=>$apellido,
                                'name'=>$sobrenombre,
                                'aniog'=>$anio_graduacion,
                                'fechan'=>$fecha_nacimiento,
                                'matricula'=>$matricula,
                                'genero'=>$sexo,
                                'email'=>$email1,
                                'telefonoc'=>$telefono,
                                'telefonom'=>$telefono_movil,
                                'calle1'=>$calle,
                                'calle2'=>$calle2,
                                'ciudad'=>$ciudad,
                                'estado'=>$estado,
                                'cp'=>$codigo_postal,
                                'pais'=>$pais
                            ]);

                            $pdo->commit();

                            header("Location: FormularioUsers.php?section=cuentas&ok=1");
                            exit();
                        }
                    }
                    catch(Exception $e){
                        $pdo->rollBack();
                        $errorMsg[] = "Error al guardar usuario: ".$e->getMessage();
                    }
                }
            }
        }  
?>
    <!DOCTYPE html>
    <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <link rel="icon" type="image/png" href="../../assets/img/logo.png">

            <title>
                <?php if($row['role'] == 'admin'): ?>
                    <?php echo 'ADMINISTRADOR';?>
                <?php elseif($row['role'] == 'estudiante') :?>
                    <?php echo 'ESTUDIANTES';?>
                <?php elseif($row['role'] == 'profesor') :?>
                    <?php echo 'PROFESOR';?>       
                <?php endif; ?>
            </title>

            <link rel="stylesheet" href="../../assets/css/style.css">
            <link rel="stylesheet" href="../../assets/css/sidebar.css">
            <link rel="stylesheet" href="../../assets/css/sidebaruser.css">
            <link rel="stylesheet" href="../../assets/css/cards.css">
            
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            
        </head>

        <body>
            <header class="topbar">
                <div class="search-box">
                    
                </div>

                <div class="top-icons"> 
                    <div class="user-menu" onclick="toggleUserMenu()">
                        <?php
                            $sstmt = $pdo->prepare("SELECT * FROM perfil WHERE id = :uid");
                            $sstmt->execute([":uid"=>$id]);
                            $rows = $sstmt->fetch(PDO::FETCH_ASSOC);
                        ?>
                        <span>
                            🧭<?php echo substr($rows['name'], 0, 4); ?>
                        </span>

                        <div id="userDropdown" class="user-dropdown">
                            <a href="../Lista/perfil.php">👤 Mi perfil</a>
                            <a href="promedio.php?idasignatura=<?= $idAsignatura ?? '' ?>">📋 Calificaciones</a>

                            <?php if($row['role'] == 'admin'): ?>
                                <a href="../Usuarios/Todo.php">👥 Usuarios</a>
                            <?php endif; ?>

                            <hr>

                            <a href="../../logout.php" class="logout">🚪 Cerrar sesión</a>
                        </div>
                    </div>
                </div>

                <span class="menu-toggle" onclick="toggleMenu()">☰</span>
            </header>

            <aside class="sidebar">
                <div class="sidebar-logo">
                    <img src="../../assets/img/icono.png" style="width: 40%;" alt="">
                </div>

                <ul class="sidebar-menu">
                    <li class="menu-item">
                        <a href="../dashboard.php">
                            <span class="icon">📊</span>
                            <span>Inicio</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="FormularioUsers.php?section=clases">
                            <span class="icon">🏫</span>
                            <span>Carreras</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="FormularioUsers.php?section=cuentas">
                            <span class="icon">🔒</span>
                            <span>Cuentas</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="FormularioUsers.php?section=exportar">
                            <span class="icon">📤</span>
                            <span>Exportar</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="FormularioUsers.php?section=importar">
                            <span class="icon">📥</span>
                            <span>Importar</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="FormularioUsers.php?section=materias">
                            <span class="icon">📚</span>
                            <span>Materias</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="FormularioUsers.php?section=permi">
                            <span class="icon">🔑</span>
                            <span>Permisos</span>
                        </a>
                    </li>
                </ul>
            </aside>

            <main class="main">
                <div>                
                    <p class="desc" style="padding:1px;">
                        Entrar información de la cuenta Introduzca la información de la cuenta y, a continuación, pulse Guardar, los campos obligatorios están indicados. Si omite la identificación y/o contraseña de un usuario, se les asignan valores únicos automáticamente.
                    </p>
                    
                    <div class="card">
                        <form method="post" class="form-grid">
                            <h2>    
                                Cuenta de <?php echo $role;?>
                            </h2>
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name='roles' value="<?php echo $role; ?>">
                            <input type="hidden" name='pass'  value="<?php echo $pass; ?>">
                            <input type="hidden" name='inst'  value="<?php echo $inst; ?>">
                            
                            <h3>
                                Iniciar sesión
                            </h3>
                            <div class="grid-2">
                                <div class="input-group">
                                    <label>Correo Electrónico</label>
                                    <input type="email" name="email" required>
                                </div>
                                <div class="input-group">
                                    <label>Contraseña</label>
                                    <?php if($pass==0): ?>
                                        <?php $passgen = bin2hex(random_bytes(4)); ?>
                                            <p style="color:red;font-weight:900;font-size:17px;">
                                            <?php echo $passgen; ?>
                                            <input type="hidden" name="password" value="<?php echo $passgen; ?>">
                                            </p>
                                    
                                    <?php else:?>
                                        <input type="text" name="password" required>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php
                                $Carrera = $pdo->prepare("SELECT *FROM carrera;");
                                $Carrera->execute();
                                $Carreras = $Carrera->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <div class="grid-2">
                                <div class="input-group">
                                    <label>Carrera</label>
                                    <select name="idcarrera">
                                        <?php foreach ($Carreras as $Carreras): ?> 
                                            <option value="<?php echo $Carreras['id']; ?>">
                                                <?php echo $Carreras['title']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <?php
                                    $Cuatrimestre = $pdo->prepare("SELECT *FROM cuatrimestre;");
                                    $Cuatrimestre->execute();
                                    $Cuatrimestre = $Cuatrimestre->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                 <div class="input-group">
                                    <label>Cuatrimestre</label>
                                    <select name="idcuatrimestre">
                                        <?php foreach ($Cuatrimestre as $Cuatrimestre): ?> 
                                            <option value="<?php echo $Cuatrimestre['idcuatrimestre']; ?>"><?php echo $Cuatrimestre['temporada']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <h3>
                                Información personal
                            </h3>
                            <div class="grid-2">
                                <div class="input-group">
                                    <label>Nombre</label>
                                    <input type="text" name="nombre" required>
                                </div>
                                <div class="input-group">
                                    <label>Apellido</label>
                                    <input type="text" name="apellido" required>
                                </div>
                                <div class="input-group">
                                    <label>Sobre nombre</label>
                                    <input type="text" name="sobrenombre">
                                </div>
                                <div class="input-group">
                                    <label>Año de graduación</label>
                                    <input type="number" name="anio_graduacion">
                                </div>
                                <div class="input-group">
                                    <label>Matricula</label>
                                    <input type="text" name="matricula">
                                </div>
                            </div>

                            <h3>
                                Información básica
                            </h3>
                            <div class="grid-2">
                                <div class="input-group">
                                    <label>Fecha de nacimiento</label>
                                    <input type="date" name="fecha_nacimiento">
                                </div>
                                <div class="input-group">
                                    <label>Sexo</label>
                                    <div class="radio-group">
                                        <label><input type="radio" name="sexo" value="Hombre" required> Hombre</label>
                                        <label><input type="radio" name="sexo" value="Mujer" required> Mujer</label>
                                    </div>
                                </div>
                            </div>

                            <h3>
                                Contacto
                            </h3>
                            <div class="grid-2">
                                <div class="input-group">
                                    <label>Correo electrónico</label>
                                    <input type="email" name="email1" required>
                                </div>
                                <div class="input-group">
                                    <label>Teléfono</label>
                                    <input type="text" name="telefono"required>
                                </div>
                                <div class="input-group">
                                    <label>Teléfono móvil</label>
                                    <input type="text" name="telefono_movil" required>
                                </div>
                            </div>

                            <h3>
                                Lugar de residencia
                            </h3>
                            <div class="grid-2">
                                <div class="input-group">
                                    <label>Calle</label>
                                    <input type="text" name="calle">
                                </div>
                                <div class="input-group">
                                    <label>Calle 2</label>
                                    <input type="text" name="calle2">
                                </div>
                                <div class="input-group">
                                    <label>municipio/Delegacion</label>
                                    <input type="text" name="ciudad">
                                </div>
                                <div class="input-group">
                                    <label>Estado</label>
                                    <input type="text" name="estado">
                                </div>
                                <div class="input-group">
                                    <label>Código postal</label>
                                    <input type="text" name="codigo_postal">
                                </div>
                                <div class="input-group">
                                    <label>País</label>
                                    <select name="pais">
                                        <option value="">Seleccionar país</option>
                                        <option>México</option>
                                        <option>Estados Unidos</option>
                                        <option>Colombia</option>
                                        <option>Argentina</option>
                                        <option>España</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" name="Usuarios" class="btn-primary">
                                Continuar
                            </button>
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
                </div>
            </main>

            <script>
                function toggleMenu(){
                    document.querySelector('.sidebar').classList.toggle('active');
                }

                function toggleUserMenu(){
                    const menu = document.getElementById('userDropdown');
                    menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
                }

                document.addEventListener('click', function(e){
                    const menu = document.getElementById('userDropdown');
                    const user = document.querySelector('.user-menu');

                    if(!user.contains(e.target)){
                        menu.style.display = 'none';
                    }
                });
            </script>
        </body>
    </html>
<?php
    }
    else{
        header("Location:../no_admin.php");
        exit;
    }
    
?>