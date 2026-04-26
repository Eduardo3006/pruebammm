<?php
require(__DIR__ . "/../../Conexion/db.php");

session_start();

if(!isset($_SESSION['user_login'])){
    header("Location: ../../no_admin.php");
    exit();
}

$id = $_SESSION['user_login'];

// Obtener usuario
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = :uid");
$stmtUser->execute([":uid"=>$id]);
$row = $stmtUser->fetch(PDO::FETCH_ASSOC);

// Validar rol
$rolesPermitidos = ['admin', 'profesor', 'estudiante'];
if(!in_array($row['role'], $rolesPermitidos)){
    header("Location: ../../no_admin.php");
    exit();
}

// Obtener asignatura
$idAsignatura = $_GET['idasignatura'] ?? null;

$idAsignatura = $_GET['idasignatura'] ?? null;

//////////////////////////////////////////////////////
// 🔥 CONSULTA SEGÚN ROL (CORREGIDA)
//////////////////////////////////////////////////////

if($row['role'] == 'profesor'){

    $sql = "SELECT 
                e.identrega,
                e.archivo,
                e.calificacion,
                e.idalumno,
                u.email,
                a.titulo
            FROM entregas e
            INNER JOIN users u ON e.idalumno = u.id
            INNER JOIN actividades a ON e.idactividad = a.idactividad";

    // 🔥 si viene asignatura, filtra
    if($idAsignatura){
        $sql .= " WHERE a.idasignatura = :idasig";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':idasig'=>$idAsignatura]);
    } else {
        // 🔥 SIN FILTRO → ver TODO (para probar)
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    
}

elseif($row['role'] == 'estudiante'){

    // 🔥 ESTUDIANTE SÍ necesita asignatura
    if(!$idAsignatura){
        echo "Selecciona una asignatura";
        exit;
    }

    $stmt = $pdo->prepare("SELECT 
        e.identrega,
        e.archivo,
        e.calificacion,
        e.idalumno,
        u.email,
        a.titulo
    FROM entregas e
    INNER JOIN actividades a ON e.idactividad = a.idactividad
    INNER JOIN users u ON e.idalumno = u.id
    WHERE e.idalumno = :idusuario
    AND a.idasignatura = :idasig");

    $stmt->execute([
        ':idusuario'=>$id,
        ':idasig'=>$idAsignatura
    ]);
}

$entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);



//////////////////////////////////////////////////////
// 🔥 CALIFICAR (SOLO PROFESOR)
//////////////////////////////////////////////////////

if(isset($_POST['calificar']) && $row['role'] == 'profesor'){

    $idEntrega = $_POST['identrega'];
    $calificacion = $_POST['calificacion'];

    $update = $pdo->prepare("UPDATE entregas 
                            SET calificacion = :c 
                            WHERE identrega = :id");

    $update->execute([
        ':c'=>$calificacion,
        ':id'=>$idEntrega
    ]);

    header("Location: promedio.php" . ($idAsignatura ? "?idasignatura=".$idAsignatura : ""));
    exit;
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
        <style>
            .main{
                padding:20px;
                background:#f4f6f9;
            }

            .layout{
                display:flex;
                gap:20px;
            }

            /* SIDEBAR */
            .left{
                width:280px;
                background:#fff;
                border-radius:12px;
                padding:15px;
                box-shadow:0 2px 10px rgba(0,0,0,0.05);
                height:fit-content;
                position:sticky;
                top:20px;
            }

            .sidebar-header{
                font-weight:bold;
                margin-bottom:10px;
            }

            .menu-temas{
                list-style:none;
                padding:0;
            }

            .menu-temas li{
                display:flex;
                align-items:center;
                gap:10px;
                padding:10px;
                border-radius:10px;
                margin-bottom:5px;
                transition:.3s;
            }

            .menu-temas li:hover{
                background:#f1f5f9;
            }

            .menu-temas li.active{
                background:#e6f7f1;
            }

            .menu-temas li a{
                text-decoration:none;
                color:#333;
                flex:1;
            }

            /* CÍRCULOS */
            .circle{
                width:25px;
                height:25px;
                border-radius:50%;
                background:#e5e7eb;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:12px;
            }

            .menu-temas li.active .circle{
                background:#00c896;
                color:#fff;
            }

            /* CONTENT */
            .right-content{
                flex:1;
            }

            /* HEADER */
            .header-top{
                display:flex;
                justify-content:space-between;
                align-items:center;
            }

            /* TABS */
            .tabs{
                display:flex;
                gap:15px;
                margin:15px 0;
            }

            .tabs span{
                padding:8px 14px;
                border-radius:20px;
                background:#e5e7eb;
                cursor:pointer;
            }

            .tabs .active{
                background:#00c896;
                color:white;
            }

            /* BOTONES */
            .btn{
                padding:8px 14px;
                border:none;
                border-radius:20px;
                cursor:pointer;
            }

            .btn.green{
                background:#00c896;
                color:white;
            }

            .btn.red{
                background:#ff5c5c;
                color:white;
            }

            .mt{
                margin-top:15px;
            }

            /* CARD */
            .card-section{
                background:#fff;
                padding:15px;
                border-radius:12px;
                margin-top:10px;
                box-shadow:0 2px 10px rgba(0,0,0,0.05);
            }

            .section-header{
                display:flex;
                justify-content:space-between;
                align-items:center;
            }

            .checks label{
                margin-left:10px;
                font-size:12px;
            }
        </style>

<style>

table{
    width:100%;
    border-collapse:collapse;
    background:#fff;
}

th, td{
    padding:10px;
    border:1px solid #ddd;
    text-align:center;
}

th{
    background:#00c896;
    color:white;
}

.btn{
    padding:5px 10px;
    border:none;
    cursor:pointer;
    border-radius:5px;
}

.ver{
    background:#3498db;
    color:white;
}

.guardar{
    background:#00c896;
    color:white;
}
</style>

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
                        <a href="Lista/perfil.php">👤 Mi perfil</a>
                        <a href="promedio.php?idasignatura=<?= $idAsignatura ?? '' ?>">📋 Calificaciones</a>

                        <?php if($row['role'] == 'admin'): ?>
                            <a href="Usuarios/Todo.php">👥 Usuarios</a>
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
                <li class="menu-item active">
                    <a href="../dashboard.php">
                        <span class="icon">📊</span>
                        <span>Inicio</span>
                    </a>
                </li>

                <li class="menu-item">
                    <?php
                        switch($row['role']){
                            case 'admin':
                                $link = "../Ajustes/FormularioUsers.php?section=permi";
                                $icon = "🛡️";
                            break;

                            case 'profesor':
                                $link = "../Lista/perfil.php";
                                $icon = "👨‍🏫";
                            break;

                            default:
                                $link = "../Lista/perfil.php";
                                $icon = "🎓";
                            break;
                        }
                    ?>

                    <a href="<?= $link ?>">
                        <span class="icon"><?= $icon ?></span>
                        <span><?= strtoupper($row['role']); ?></span>
                    </a>
                </li>

                <li class="menu-item has-sub" id="menu-classes">
                    <?php if($row['role'] == 'admin'): ?>
                        <a href="javascript:void(0)">
                            <span class="icon">📚</span>
                            <span>Asignaturas</span>
                            <i class="arrow"></i>
                        </a>
                    <?php else: ?>
                        <a href="javascript:void(0)">
                            <span class="icon">📚</span>
                            <span>Asignaturas</span>
                            <i class="arrow"></i>
                        </a>
                    <?php endif; ?>

                    <div class="dropDown" id="submenu-classes">
                        <div class="dropDownHeading">
                            <?php
                                $Area = $pdo->prepare("SELECT  B.nombre ,A.role,C.title,C.id AS idcarrera, D.idcuatrimestre AS idcuatrimestre, D.temporada FROM users A
                                                    INNER JOIN perfil B ON A.id = B.iduser
                                                    INNER JOIN carrera C ON B.idcarrera = C.id
                                                    INNER JOIN cuatrimestre D ON B.idcuatrimestre = D.idcuatrimestre 
                                                    WHERE B.iduser= :uid");
                                $Area->execute([":uid"=>$id]);
                                $rowArea = $Area->fetch(PDO::FETCH_ASSOC);

                                $carrera = $rowArea['title'] ?? null;
                                $cuatrim = $rowArea['temporada'] ?? null;
                            ?>
                            
                            <?php if($carrera == null || $cuatrim == null): ?>

                            <?php else:?>
                                <span id="heading-text"><?php echo $carrera; ?></span>
                                <span><?php echo  $cuatrim;?>  Cuatrimestre</span>
                            <?php endif; ?>
                        </div>

                        <div class="scroll tab-content active" id="tab-teaching">
                            <?php
                                $idCarrera = $rowArea['idcarrera']?? null;

                                $asignatura = $pdo->prepare("SELECT  *FROM asignatura A INNER JOIN carrera B ON A.idcarrera = B.id
                                                            WHERE A.idcarrera = :carrera");
                                $asignatura->execute([':carrera' => $idCarrera ]);
                            ?>
                            <?php if($idCarrera == null): ?>
                                <a href="../todoa.php" class="btn-primary">
                                    🛠 ADMIN TOTAL
                                </a>
                            <?php else:?>
                                <label>
                                    Temas: 
                                </label>

                                <?php foreach ($asignatura as $asignaturas): ?>  
                                    <p style="margen:2px;"><?php echo $asignaturas['tema']?></p>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php if($row['role'] == 'admin'): ?>
                            <ul class="cta horizontal">
                                <li>
                                    <a href="actividadesD.php">
                                        📦 Actividades
                                    </a>
                                </li>

                                <li>
                                    <a href="promedio.php?idasignatura=<?= $idAsignatura ?? '' ?>">
                                        📋 Promedios
                                    </a>
                                </li>
                            
                                <li>
                                    <a href="añadir.php">
                                        ➕ Añadir
                                    </a>
                                </li>

                                <li>
                                    <a href="actualizar.php">
                                        🔒 Ver
                                    </a>
                                </li>
                            </ul>
                        <?php elseif($row['role'] == 'profesor'): ?>
                            <ul class="cta horizontal">
                                <li>
                                    <a href="actividades.php?idCarrera=<?=  $idCarrera ?>"">
                                        📦 Actividades
                                    </a>
                                </li>
                                <li>
                                    <a href="promedio.php?idasignatura=<?= $idAsignatura ?? '' ?>">
                                        📋 Promedios
                                    </a>
                                </li>     
                                <li>
                                    <a href="añadir.php?idCarrera=<?=  $idCarrera ?>">
                                        ➕ Añadir
                                    </a>
                                </li>
                                <li>
                                    <a href="actualizar.php?idCarrera=<?=  $idCarrera ?>">
                                        🔒 Ver
                                    </a>
                                </li>
                            </ul>
                        <?php else: ?>
                            <ul class="cta horizontal">
                                <li>
                                    <a href="javascript:void(0)" onclick="abrirModalAsignatura()">
                                        📦 Actividades
                                    </a>
                                </li>
                                <li>
                                    <a href="promedio.php?idasignatura=<?= $idAsignatura ?? '' ?>">
                                        📋 calificacion
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0)" onclick="abrirModalInscripcion()">
                                        ➡ Inscribirse
                                    </a>
                                </li>
                            </ul>       
                        <?php endif; ?>
                    </div>
                </li>

                <li class="menu-item">
                    <?php if($row['role'] == 'admin'): ?>
                        <a href="../todo.php">
                            <span class="icon">🏫</span>
                            <span>Carreras</span>
                        </a>
                    <?php else:?>  
                        
                    <?php endif; ?>
                </li>

                <li class="menu-item">
                    <a href="../Lista/lista.php?idCarrera=<?=  $idCarrera ?>">
                        <span class="icon">👥</span>
                        <span>Grupos</span>
                    </a>
                </li>

                <li class="menu-item has-sub" id="menu-usuarios">
                    <?php if($row['role'] == 'admin'): ?>
                        <a href="javascript:void(0)">
                            <span class="icon">🧑‍🤝‍🧑</span>
                            <span>Usuarios</span>
                            <i class="arrow"></i>
                        </a>

                        <div class="dropDown" id="submenu-usuarios">
                            <div class="scroll tab-content active" id="tab-teaching">
                                <ul>
                                    <a href="../Usuarios/Administrador.php" style="color:#000;">🛡️ Administradores</a>
                                    <a href="../Usuarios/Profesores.php" style="color:#000;">👨‍🏫 Profesores</a>
                                    <a href="../Usuarios/Estudiantes.php" style="color:#000;">🎓 Estudiantes</a>
                                    <a href="../Usuarios/Todo.php" style="color:#000;">🌐 Todo</a>
                                </ul>
                            </div>

                            <ul class="cta horizontal">
                                <li>
                                    <a href="../Usuarios/Catalogo.php">
                                        📦 Catálogo
                                    </a>
                                </li>
                                <li>
                                    <a href="../Usuarios/Todo.php">
                                        📋 Listado
                                    </a>
                                </li>
                                <li>
                                    <a href="../Ajustes/FormularioUsers.php?section=permi">
                                        ➡ <?php echo strtoupper($row['role']);?>
                                    </a>
                                </li>
                                <li>
                                    <a href="../Ajustes/FormularioUsers.php?section=cuentas">
                                        ➕ Añadir
                                    </a>
                                </li>
                            </ul>
                        </div> 
                    <?php else:?>  
                        
                    <?php endif; ?>
                </li>
                <li class="menu-item">
                    <a href="recursos.php">
                        <span class="icon">📦</span>
                        <span>Recursos</span>
                    </a>
                     
                </li>
            </ul>
        </aside>

<main class="main">

    <div class="card-section">
        <div class="section-header">
            <h2>📊 Entregas</h2>
        </div>

        <table class="tabla-entregas">
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Actividad</th>
                    <th>Archivo</th>
                    <th>Estado</th>

                    <?php if($row['role'] == 'profesor'): ?>
                        <th>Calificar</th>
                    <?php endif; ?>
                </tr>
            </thead>

            <tbody>
            <?php foreach($entregas as $e):  ?>
                
                <tr>

                    <td><?= htmlspecialchars($e['email']) ?></td>

                    <td><?= htmlspecialchars($e['titulo']) ?></td>

                    <td>
                        <?php if($e['archivo']): ?>
                            <a class="btn ver" href="../../uploads/<?= $e['archivo'] ?>" target="_blank">
                                📄 Ver archivo
                            </a>
                        <?php else: ?>
                            <span class="badge red">Sin archivo</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if($e['calificacion'] !== null): ?>
                            <span class="badge green">
                                ✅ <?= $e['calificacion'] ?>
                            </span>
                        <?php else: ?>
                            <span class="badge yellow">
                                ⏳ Pendiente
                            </span>
                        <?php endif; ?>
                    </td>

                    <?php if($row['role'] == 'profesor'): ?>
                    <td>
                        <form method="POST" class="form-calificar">
                            <input type="hidden" name="identrega" value="<?= $e['identrega'] ?>">

                            <input type="number" name="calificacion" min="0" max="10" required>

                            <button type="submit" name="calificar" class="btn guardar">
                                💾
                            </button>
                        </form>
                    </td>
                    <?php endif; ?>

                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    </div>

</main>
 <div id="modalInscripcion" class="modal" style="display:none;">
            <div class="modal-content">
                <h3>Inscribirse a una asignatura</h3>

                <form method="POST">     
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <label>Selecciona asignatura</label>
                    <select name="idasignatura" required>
                        <option value="">Seleccione</option>

                        <?php
                            $asignaturasAll = $pdo->prepare("SELECT * FROM asignatura WHERE idcarrera = :carrera");
                            $asignaturasAll->execute([':carrera' => $idCarrera]);

                            foreach($asignaturasAll as $asig):
                        ?>
                            <option value="<?= $asig['idasignatura'] ?>">
                                <?= $asig['tema'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <br><br>

                    <button type="submit" name="inscribirs" class="btn-primary">
                        ✅ Inscribirme
                    </button>

                    <button type="button" onclick="cerrarModalInscripcion()" class="btn-cancelar">
                        ❌ Cancelar
                    </button>
                </form>
            </div>
        </div>
        <div id="modalAsignatura" class="modal" style="display:none;">
            <div class="modal-content">
                <h3>Seleccionar asignatura</h3>

                <form method="GET" action="actividades.php"> 
                    <label>Asignatura</label>
                    <select name="idasignatura" required>
                        <option value="">Seleccione</option>

                        <?php
                            $asigModal = $pdo->prepare("SELECT a.idasignatura, a.tema FROM asignatura a
                                INNER JOIN inscripciones i ON a.idasignatura = i.idasignatura
                                WHERE i.idusuario = :uid");
                            $asigModal->execute([':uid'=>$id]);
                        ?>
                        <?php if($asigModal->rowCount() > 0): ?>
                            <?php foreach($asigModal as $as): ?>
                                <option value="<?= $as['idasignatura'] ?>">
                                    <?= $as['tema'] ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">No estás inscrito en ninguna asignatura</option>
                        <?php endif; ?>
                    </select>

                    <br><br>

                    <button type="submit" class="btn-primary">
                        📂 Ver actividades
                    </button>

                    <button type="button" onclick="cerrarModalAsignatura()" class="btn-cancelar">
                        ❌ Cancelar
                    </button>
                </form>
            </div>
        </div>        
        
        <script>
            function irContenido(id){
                window.location = "ver_tema.php?idasignatura=" + id;
            }
        </script>
        <script>
            function irCarrera(id){
                window.location = "todoa.php?idCarrera=" + id;
            }
        </script>
        <script>
            function abrirModalNuevaCarrera(){
                document.getElementById('modalNuevaCarrera').style.display = 'flex';
            }

            function cerrarModalNuevaCarrera(){
                document.getElementById('modalNuevaCarrera').style.display = 'none';
            }
        </script>
        <script>
            function abrirModalInscripcion(){
                document.getElementById('modalInscripcion').style.display = 'flex';
            }

            function cerrarModalInscripcion(){
                document.getElementById('modalInscripcion').style.display = 'none';
            }
        </script>
        <script>
            function abrirModalAsignatura(){
                document.getElementById('modalAsignatura').style.display = 'flex';
            }

            function cerrarModalAsignatura(){
                document.getElementById('modalAsignatura').style.display = 'none';
            }
        </script>
        <script>
            const menu = document.getElementById('menu-classes');
            const submenu = document.getElementById('submenu-classes');

            function openSubmenu(){
                const rect = menu.getBoundingClientRect();
                submenu.style.top = rect.top + 'px';
                submenu.classList.add('active');
            }
            function closeSubmenu(){
                submenu.classList.remove('active');
            }
            menu.addEventListener('mouseenter', openSubmenu);
            menu.addEventListener('mouseleave', () => {
                setTimeout(() => {
                    if (!submenu.matches(':hover')) closeSubmenu();
                }, 150);
            });
            submenu.addEventListener('mouseleave', closeSubmenu);
            menu.querySelector('a').addEventListener('click', e => {
                e.preventDefault();
                submenu.classList.contains('active') ? closeSubmenu() : openSubmenu();
            });
        </script>
        <script>
            const menuusuarios     = document.getElementById('menu-usuarios');
            const submenuusuarios  = document.getElementById('submenu-usuarios');

            if(menuusuarios && submenuusuarios){
                function openSubmenuusuarios(){
                    const rect = menuusuarios.getBoundingClientRect();
                    submenuusuarios.style.top = rect.top + 'px';
                    submenuusuarios.classList.add('active');
                }
                function closeSubmenuusuarios(){
                    submenuusuarios.classList.remove('active');
                }

                menuusuarios.addEventListener('mouseenter', openSubmenuusuarios);

                menuusuarios.addEventListener('mouseleave', () => {
                    setTimeout(() => {
                        if (!submenuusuarios.matches(':hover')) closeSubmenuusuarios();
                    }, 150);
                });

                submenuusuarios.addEventListener('mouseleave', closeSubmenuusuarios);

                menuusuarios.querySelector('a').addEventListener('click', e => {
                    e.preventDefault();
                    submenuusuarios.classList.contains('active') ? closeSubmenuusuarios() : openSubmenuusuarios();
                });
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