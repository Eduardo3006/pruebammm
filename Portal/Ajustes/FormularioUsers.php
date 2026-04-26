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
        header("Location: ../../no_admin.php");
        exit();
    }

    $id = $_SESSION['user_login'];

    $select_stmt = $pdo->prepare("SELECT * FROM users WHERE id = :uid");
    $select_stmt->execute([":uid"=>$id]);
    $row = $select_stmt->fetch(PDO::FETCH_ASSOC);

    $rolesPermitidos = ['admin'];

    if(!in_array($row['role'], $rolesPermitidos)){
        header("Location: ../../no_admin.php");
        exit();
    }

    if(isset($_SESSION['user_login'])){    
        $ok=$_GET['ok'] ?? null;

        $select_stmt1 = $pdo->prepare("SELECT *FROM perfil WHERE iduser =:uid;");
        $select_stmt1->execute(array(":uid"=>$id));
        $row1=$select_stmt1->fetch(PDO::FETCH_ASSOC);

        if(isset($_POST['importU'])){
            $insertados = 0;
            $duplicados = 0;

            if(isset($_FILES['archivo']) && $_FILES['archivo']['error'] == 0){
                $archivo = fopen($_FILES['archivo']['tmp_name'], "r");

                if($archivo){
                    $fila = 0;

                    while(($datos = fgetcsv($archivo, 1000, ",")) !== false){
                        if(count($datos) < 4){
                            continue;
                        }

                        $email    = trim($datos[0]);
                        $email    = str_replace("\xEF\xBB\xBF","",$email);
                        $password = $datos[1] ?: "FL3m1n6"; 
                        $pass     = $datos[2] ?: 0;
                        $inst     = $datos[3] ?: 0;
                        $role     = $datos[4] ?: "estudiante";

                        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
                            continue;
                        }

                        try{
                            $check = $pdo->prepare("SELECT id FROM users WHERE email=:email");
                            $check->execute(['email'=>$email]);

                            if($check->rowCount() > 0){
                                $duplicados++;
                                continue;
                            }

                            $pdo->beginTransaction();

                            $hash = password_hash($password, PASSWORD_DEFAULT);

                            $stmt = $pdo->prepare("INSERT INTO users(email,password,passwordN,instruccion,role)VALUES(:email,:password,:passwordN,:instruccion,:role)");

                            $stmt->execute([
                                'email'=>$email,
                                'password'=>$hash,
                                'passwordN'=>$pass,
                                'instruccion'=>$inst,
                                'role'=>$role]);
                            $pdo->commit();
                            $insertados++;

                        }
                        catch(Exception $e){
                            $pdo->rollBack();
                        }
                    }

                    fclose($archivo);
                    header("Location: FormularioUsers.php?section=importar&ins=$insertados&dup=$duplicados");
                    exit();
                }
            }
        }
        if(isset($_POST['exportU'])){

            ob_clean();

            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=usuarios.xls");
            header("Pragma: no-cache");
            header("Expires: 0");

            echo "ID\tEmail\tFecha\n";

            $stmt = $pdo->query("SELECT id, email, created_at FROM users");

            while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
                echo $row['id']."\t".
                    $row['email']."\t".
                    $row['created_at']."\n";
            }

            exit();
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
            
            <script>
                document.addEventListener("DOMContentLoaded", function(){
                    const params = new URLSearchParams(window.location.search);
                    const section = params.get("section");
                    const tab = params.get("tab");

                    if(section){
                        document.querySelectorAll('.section').forEach(sec=>{
                            sec.classList.remove('active');
                        });

                        const targetSection = document.getElementById(section);

                        if(targetSection){
                            targetSection.classList.add('active');
                        }
                    }

                    if(tab){
                        document.querySelectorAll('.tab-content').forEach(content=>{
                            content.classList.remove('active');
                        });

                        const targetTab = document.getElementById(tab);

                        if(targetTab){
                            targetTab.classList.add('active');
                        }

                        document.querySelectorAll('.tab-btn').forEach(btn=>{
                            btn.classList.remove('active');

                            if(btn.href.includes("tab="+tab)){
                                btn.classList.add('active');
                            }
                        });
                    }
                });
            </script>
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
                    <li class="menu-item" onclick="showSection('clases', this)">
                        <a>
                            <span class="icon">🏫</span>
                            <span>Carreras</span>
                        </a>
                    </li>
                    <li class="menu-item" onclick="showSection('cuentas', this)">
                        <a>
                            <span class="icon">🔒</span>
                            <span>Cuentas</span>
                        </a>
                    </li>
                    <li class="menu-item" onclick="showSection('exportar', this)">
                        <a>
                            <span class="icon">📤</span>
                            <span>Exportar</span>
                        </a>
                    </li>
                    <li class="menu-item" onclick="showSection('importar', this)">
                        <a>
                            <span class="icon">📥</span>
                            <span>Importar</span>
                        </a>
                    </li>
                    <li class="menu-item" onclick="showSection('materias', this)">      
                        <a>
                            <span class="icon">📚</span>
                            <span>Materias</span>
                        </a>
                    </li>
                    <li class="menu-item" onclick="showSection('permi', this)">
                        <a>
                            <span class="icon">🔑</span>
                            <span>Permisos</span>
                        </a>
                    </li>
                </ul>
            </aside>

            <main class="main">
                <div id="clases" class="section">
                    <div class="card">
                        <table class="permissions-table">
                            <thead>
                                <tr>
                                    <th class="center">Carrera</th>
                                    <th class="center">Duracion</th>
                                    <th class="center">Fecha de inicio</th>
                                    <th class="center">Fecha Final</th>
                                    <th class="center">Descripcion</th>
                                    <th class="center">Imagen</th>
                                </tr>
                            </thead>
                            
                            <?php
                                $carre = $pdo->prepare("SELECT *FROM carrera;");
                                $carre->execute();
                                $carre = $carre->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <?php foreach ($carre as $carre): ?>
                                <tbody>
                                    <tr class="section-row">
                                        <td><?php echo $carre['title']; ?></td>
                                        <td><?php echo $carre['duration']; ?></td>
                                        <td><?php echo $carre['end_date']; ?></td>
                                        <td><?php echo $carre['description']; ?></td>
                                        <td><?php echo $carre['image']; ?></td>
                                    </tr>                                  
                                </tbody>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>

                <div id="cuentas" class="section active">
                    <div class="tabs">
                        <button class="tab-btn" onclick="showTab('descripcion')">📝 Descripción</button>
                        <button class="tab-btn" onclick="showTab('roles')">🎯 Roles</button>
                        <button class="tab-btn active" onclick="showTab('formulario')">📋 Formulario</button>
                    </div>              

                    <div class="card">
                        <div id="descripcion" class="tab-content">
                            <button class="tab-btn" style="color:blue; border:none; background:transparent; padding: 0px;font-size: 15px;" onclick="showTab('formulario')">
                                Utilizando un formulario
                            </button>
                            <p  style="font-size: 14px;">
                                Crear cuentas utilizando un formulario web.
                            </p>
                            <button class="tab-btn" style="color:blue; border:none; background:transparent; padding: 0px;font-size: 15px;" onclick="showSection('importar', this)">
                                Importación masiva de cuentas
                            </button>
                            <p style="font-size: 14px;">
                                Cargar un archivo CSV con los detalles de las cuentas.
                            </p>
                        </div>

                        <div id="roles" class="tab-content">
                            <div class="card-list">
                                <div class="table-wrapper">
                                    <table class="roles-table">
                                        <thead>
                                            <tr>
                                                <th>Papel</th>
                                                <th>Descripción</th>
                                                <th class="center">Habilitado</th>
                                            </tr>
                                        </thead>
                                        
                                        <tbody>
                                            <tr>
                                                <td>Administrador</td>
                                                <td>Para usuarios que necesitan poder administrar todo el sitio. Concede con moderación.</td>
                                                <td class="center"><span class="status ok">✔</span></td>
                                            </tr>
                                            <tr>
                                                <td>Super administrador</td>
                                                <td>Para usuarios que necesitan permisos adicionales, como la posibilidad de cancelar la suscripción al sitio. Sólo uno por sitio.</td>
                                                <td class="center"><span class="status no">✖</span></td>
                                            </tr>
                                            <tr>
                                                <td>Administrador de contenido</td>
                                                <td>Para usuarios que necesitan crear y administrar recursos como plantillas de clases.</td>
                                                <td class="center"><span class="status no">✖</span></td>
                                            </tr>
                                            <tr>
                                                <td>Asistente de enseñanza</td>
                                                <td>Para usuarios que ayudan a los profesores con sus clases.</td>
                                                <td class="center"><span class="status no">✖</span></td>
                                            </tr>
                                            <tr>
                                                <td>Estudiante</td>
                                                <td>Para usuarios que toman clases.</td>
                                                <td class="center"><span class="status ok">✔</span></td>
                                            </tr>
                                            <tr>
                                                <td>Mentor</td>
                                                <td>Para usuarios que asesoran a estudiantes.</td>
                                                <td class="center"><span class="status no">✖</span></td>
                                            </tr>
                                            <tr>
                                                <td>Monitor</td>
                                                <td>Para usuarios que solo deberían poder ver algo en general, pero no cambiarlo.</td>
                                                <td class="center"><span class="status no">✖</span></td>
                                            </tr>
                                            <tr>
                                                <td>Padre</td>
                                                <td>Para usuarios que tienen hijos.</td>
                                                <td class="center"><span class="status no">✖</span></td>
                                            </tr>
                                            <tr>
                                                <td>Profesor</td>
                                                <td>Para usuarios que imparten clases.</td>
                                                <td class="center"><span class="status ok">✔</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div id="formulario" class="tab-content active">
                            <form action="FormularioUsersD.php" method="post">
                                <div class="form-group">
                                    <label>
                                        Rol de cuenta
                                    </label>

                                    <div class="radio-group">
                                        <label><input type="radio" name="rol" value="1">Administrador</label>
                                        <label><input type="radio" name="rol" value="2">Estudiante</label>
                                        <label><input type="radio" name="rol" value="3">Profesor</label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>
                                        Requerir al usuario cambiar su contraseña cuando accedan por primera vez
                                    </label>

                                    <div class="radio-group">
                                        <label><input type="radio" name="pass" value="1">Sí</label>
                                        <label><input type="radio" name="pass" value="0">No</label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>
                                        Enviar instrucciones para entrar
                                    </label>

                                    <div class="radio-group">
                                        <label><input type="radio" name="inst" value="1">Sí</label>
                                        <label><input type="radio" name="inst" value="0">No</label>
                                    </div>
                                </div>
                                
                                <?php if($row['role'] == 'admin'): ?>
                                    <button type="submit" class="btn-primary">
                                        Continuar
                                    </button>
                                 <?php else:?> 
                                    <p style="text-align: center;">
                                        No tiene acceso a esta area
                                    </p>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="exportar" class="section">
                    <?php if($row['role'] == 'admin'): ?>
                        <div class="card import-card">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                <div style="text-align:center;">
                                    <button type="submit" name="exportU" class="import-btn">
                                        Exportar Datos
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else:?> 
                        <div class="card import-card">
                            <h1 style="text-align: center;">
                                No tiene acceso a esta area
                            </h1>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="importar" class="section">
                    <?php if($row['role'] == 'admin'): ?>
                        <div class="card import-card">
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                <label class="upload-container">
                                    <div class="upload-text">
                                        Arrastra tu archivo aquí o haz clic para seleccionarlo
                                    </div>
                                    <div class="file-name" id="file-name">
                                        Ningún archivo seleccionado
                                    </div>
                                    <br>
                                    <input type="file" name="archivo" id="archivo" required>

                                    <button type="button" class="select-btn" onclick="document.getElementById('archivo').click()">
                                        Seleccionar archivo
                                    </button>
                                </label>

                                <div style="text-align:center;">
                                    <button type="submit" name="importU" class="import-btn">
                                        Importar Datos
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else:?> 
                        <div class="card import-card">
                            <h1 style="text-align: center;">
                                No tiene acceso a esta area
                            </h1>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="materias" class="section">
                    <div class="card">
                        <table class="permissions-table">
                            <thead>
                                <tr>
                                    <th>Permiso</th>
                                    <th class="center">Administrador</th>
                                    <th class="center">Estudiante</th>
                                    <th class="center">Profesor</th>
                                </tr>
                            </thead>
                            
                            <tbody>
                                <tr class="section-row">
                                    <td colspan="6">General</td>
                                </tr>
                                <tr>
                                    <td>Configurar</td>
                                    <td class="center check">✔</td>
                                    <td class="center cross">✖</td>
                                    <td class="center check">✔</td>
                                </tr>
                                <tr>
                                    <td>Contenido editado</td>
                                    <td class="center check">✔</td>
                                    <td class="center cross">✖</td>
                                    <td class="center check">✔</td>
                                </tr>

                                <tr class="section-row">
                                    <td colspan="6">Secciones</td>
                                </tr>
                                <tr>
                                    <td>Agregar una clase secundaria</td>
                                    <td class="center check">✔</td>
                                    <td class="center cross">✖</td>
                                    <td class="center check">✔</td>
                                </tr>

                                <tr class="section-row">
                                    <td colspan="6">Profesores</td>
                                </tr>
                                <tr>
                                    <td>Agregar coprofesores</td>
                                    <td class="center check">✔</td>
                                    <td class="center cross">✖</td>
                                    <td class="center cross">✖</td>
                                </tr>
                                <tr>
                                    <td>Eliminar coprofesores</td>
                                    <td class="center check">✔</td>
                                    <td class="center cross">✖</td>
                                    <td class="center cross">✖</td>
                                </tr>
                                <tr>
                                    <td>Enviar invitaciones a profesores por correo electrónico</td>
                                    <td class="center check">✔</td>
                                    <td class="center cross">✖</td>
                                    <td class="center cross">✖</td>
                                </tr>
                                
                                <tr class="section-row">
                                    <td colspan="6">Estudiantes</td>
                                </tr>
                                <tr>
                                    <td>Inscribir estudiantes</td>
                                    <td class="center check">✔</td>
                                    <td class="center cross">✖</td>
                                    <td class="center check">✔</td>
                                </tr>
                                <tr>
                                    <td>Dar de baja a estudiantes</td>
                                    <td class="center check">✔</td>
                                    <td class="center cross">✖</td>
                                    <td class="center check">✔</td>
                                </tr>
                                <tr>
                                    <td>Transfer students</td>
                                    <td class="center check">✔</td>
                                    <td class="center cross">✖</td>
                                    <td class="center check">✔</td>
                                </tr>
                                <tr>
                                    <td>Eliminar estudiantes</td>
                                    <td class="center check">✔</td>
                                    <td class="center cross">✖</td>
                                    <td class="center cross">✖</td>
                                </tr>
                                <tr>
                                    <td>Enviar invitaciones a estudiantes por correo electrónico</td>
                                    <td class="center check">✔</td>
                                    <td class="center cross">✖</td>
                                    <td class="center check">✔</td>
                                </tr>

                                <tr class="section-row">
                                    <td colspan="6">Tareas</td>
                                </tr>
                                <tr>
                                    <td>Listar tareas</td>
                                    <td class="center check">✔</td>
                                    <td class="center cross">✖</td>
                                    <td class="center check">✔</td>
                                </tr>
                                <tr>
                                    <td>Crear tareas</td>
                                    <td class="center check">✔</td>
                                    <td class="center cross">✖</td>
                                    <td class="center check">✔</td>
                                </tr>
                                <tr>
                                    <td>Envíos de calificaciones</td>
                                    <td class="center check">✔</td>
                                    <td class="center cross">✖</td>
                                    <td class="center check">✔</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="permi" class="section">
                    <div class="tabs">
                        <button class="tab-btn" onclick="showTab('descr')">📋 Descripción</button>
                        <button class="tab-btn" onclick="showTab('centr')">🏫 Centro</button>
                        <button class="tab-btn active" onclick="showTab('clas')">📚 Clases</button>
                    </div>              

                    <div class="card">
                        <div id="descr" class="tab-content active">
                            <h3>Permisos</h3>
                            <p style="font-size: 13px;">
                                Esta área le permite ver varios tipos de permisos en un formato de tabla conveniente. 
                                Actualmente existen dos tipos de permisos:
                            </p>
                            <label style="font-size: 12px;color: cadetblue;">
                                Permisos escolares
                            </label>
                            <p style="font-size: 11px;">
                                Estos indican permisos que se aplican dentro del alcance de su sitio. 
                                Los roles se refieren a aquellos que se muestran en la parte superior derecha del área del perfil de un usuario.
                            </p>
                            <label style="font-size: 12px;color: cadetblue;">
                                Permisos de clase
                            </label>
                            <p style="font-size: 11px;">
                                Estos indican los permisos que se aplican dentro del ámbito de una clase. 
                                Los roles "profesor de la clase" y "coprofesor de la clase" se otorgan a un usuario si es profesor o coprofesor respectivamente en una clase en particular.  
                            </p>
                        </div>

                        <div id="centr" class="tab-content">
                            <div class="card">
                                <div class="card-header">
                                    <div class="legend">
                                        <span><span class="ok">✔</span> Otorgada</span>
                                        <span><span class="no">✖</span> Denegado</span>
                                    </div>
                                </div>

                                <table class="permissions-table">
                                    <thead>
                                        <tr>
                                            <th>Permiso</th>
                                            <th class="center">Administrador</th>
                                            <th class="center">Estudiante</th>
                                            <th class="center">Profesor</th>
                                        </tr>
                                    </thead>
                                    
                                    <tbody>
                                        <tr class="section-row">
                                            <td colspan="6">General</td>
                                        </tr>
                                        <tr>
                                            <td>Utilice la búsqueda del sitio</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Acceder al calendario principal</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Habilitar el widget de tareas pendientes del panel</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Mostrar tareas pendientes automáticas</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Permitir que se configuren las notificaciones</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                        </tr>

                                        <tr class="section-row">
                                            <td colspan="6">Ayuda</td>
                                        </tr>
                                        <tr>
                                            <td>Acceder al centro de ayuda</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Acceder al contenido de ayuda</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Acceder al foro de soporte</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Acceso a noticias de productos.</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Acceda a las guías de introducción</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Accede a vídeos instructivos</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                        </tr>

                                        <tr class="section-row">
                                            <td colspan="6">Clases</td>
                                        </tr>
                                        <tr>
                                            <td>Crear clases</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Inscríbete en clases</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Ver catálogo de clases</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Listar clases</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>
                                        <tr>
                                            <td>Clases de importación masiva</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>
                                        <tr>
                                            <td>Profesores de importación masiva</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>
                                        <tr>
                                            <td>Clases de exportación masiva</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>

                                        <tr class="section-row">
                                            <td colspan="6">Grupos</td>
                                        </tr>
                                        <tr>
                                            <td>Crear Grupos</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Ver catálogo del grupo</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Listar grupos</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Acceder a grupos comunitarios</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>
                                        <tr>
                                            <td>Grupos de importación masiva</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>
                                        <tr>
                                            <td>Miembros de importación masiva</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>
                                        <tr>
                                            <td>Grupos de exportación a granel</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>

                                        <tr class="section-row">
                                            <td colspan="6">Usuarios</td>
                                        </tr>
                                        <tr>
                                            <td>Crear Usuarios</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>
                                        <tr>
                                            <td>Eliminar usuarios</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>
                                        <tr>
                                            <td>Invitaciones de usuario por correo electrónico</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>
                                        <tr>
                                            <td>Usuarios de importación masiva</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>
                                        <tr>
                                            <td>Usuarios de exportación masiva</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div id="clas" class="tab-content">
                            <div class="card">
                                <div class="card-header">
                                    <div class="legend">
                                        <span><span class="ok">✔</span> Otorgada</span>
                                        <span><span class="no">✖</span> Denegado</span>
                                    </div>
                                </div>

                                <table class="permissions-table">
                                    <thead>
                                        <tr>
                                            <th>Permiso</th>
                                            <th class="center">Administrador</th>
                                            <th class="center">Estudiante</th>
                                            <th class="center">Profesor</th>
                                        </tr>
                                    </thead>
                                    
                                    <tbody>
                                        <tr class="section-row">
                                            <td colspan="6">General</td>
                                        </tr>
                                        <tr>
                                            <td>Configurar</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Contenido editado</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center check">✔</td>
                                        </tr>

                                        <tr class="section-row">
                                            <td colspan="6">Secciones</td>
                                        </tr>
                                        <tr>
                                            <td>Agregar una clase secundaria</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center check">✔</td>
                                        </tr>

                                        <tr class="section-row">
                                            <td colspan="6">Profesores</td>
                                        </tr>
                                        <tr>
                                            <td>Agregar coprofesores</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>
                                        <tr>
                                            <td>Eliminar coprofesores</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>
                                        <tr>
                                            <td>Enviar invitaciones a profesores por correo electrónico</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>
                                        
                                        <tr class="section-row">
                                            <td colspan="6">Estudiantes</td>
                                        </tr>
                                        <tr>
                                            <td>Inscribir estudiantes</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Dar de baja a estudiantes</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Transfer students</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Eliminar estudiantes</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center cross">✖</td>
                                        </tr>
                                        <tr>
                                            <td>Enviar invitaciones a estudiantes por correo electrónico</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center check">✔</td>
                                        </tr>

                                        <tr class="section-row">
                                            <td colspan="6">Tareas</td>
                                        </tr>
                                        <tr>
                                            <td>Listar tareas</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Crear tareas</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                        <tr>
                                            <td>Envíos de calificaciones</td>
                                            <td class="center check">✔</td>
                                            <td class="center cross">✖</td>
                                            <td class="center check">✔</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <?php if($ok==1): ?>
                <script>
                    Swal.fire({
                        icon:"success",
                        text:"¡Excelente! Me alegra saber que el proceso de registro se completó correctamente."
                    });
                </script>    
            <?php endif; ?>  

            <?php if(isset($_GET['ins'])): ?>
                <script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Importación completada',
                        html: 'Usuarios insertados: <b><?=$_GET['ins']?></b><br>Usuarios duplicados: <b><?=$_GET['dup']?></b>',
                        confirmButtonText: 'Aceptar'
                    });
                </script>
            <?php endif; ?>

            <script>
                document.getElementById("archivo").addEventListener("change", function(){
                    let fileName = this.files[0].name;
                    document.getElementById("file-name").innerText = "Archivo: " + fileName;
                });

                window.onload = function(){
                    const params = new URLSearchParams(window.location.search);
                    let section = params.get("section");

                    if(!section){
                        section = "cuentas";
                    }
                    showSection(section);
                }

                function showSection(section){
                    document.querySelectorAll(".section").forEach(sec=>{
                        sec.classList.remove("active");
                    });
                    document.querySelectorAll(".menu li").forEach(li=>{
                        li.classList.remove("active");
                    });

                    const selectedSection = document.getElementById(section);
                    if(selectedSection){
                        selectedSection.classList.add("active");
                    }

                    const menuItem = document.querySelector(`.menu li[onclick*="${section}"]`);
                    if(menuItem){
                        menuItem.classList.add("active");
                    }
                }

                function showSection(sectionId, element){
                    document.querySelectorAll('.section').forEach(sec=>{
                        sec.classList.remove('active');
                    });

                    document.querySelectorAll('.menu li').forEach(li=>{
                        li.classList.remove('active');
                    });

                    document.getElementById(sectionId).classList.add('active');
                    element.classList.add('active');
                }

                function showTab(tabId, element){
                    document.querySelectorAll('.tab-content').forEach(content=>{
                        content.classList.remove('active');
                    });

                    document.querySelectorAll('.tab-btn').forEach(btn=>{
                        btn.classList.remove('active');
                    });

                    document.getElementById(tabId).classList.add('active');
                    element.classList.add('active');
                }
            
                function getParam(name){
                    const params = new URLSearchParams(window.location.search);
                    return params.get(name);
                }

                window.onload = function(){
                    let section = getParam("section");
                    document.querySelectorAll(".section").forEach(sec=>{
                        sec.classList.remove("active");
                    });

                    if(section){
                        let target = document.getElementById(section);

                        if(target){
                            target.classList.add("active");
                        }
                    }
                    else{
                        document.getElementById("acerca").classList.add("active");
                    }

                }
            </script>

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
?>