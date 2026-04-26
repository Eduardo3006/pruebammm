<?php
    require("../Conexion/db.php");
    
    session_start();

    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");

    if(!isset($_SESSION['user_login'])){
        header("Location: no_admin.php");
        exit();
    }

    $id = $_SESSION['user_login'];

    $select_stmt = $pdo->prepare("SELECT * FROM users WHERE id = :uid");
    $select_stmt->execute([":uid"=>$id]);
    $row = $select_stmt->fetch(PDO::FETCH_ASSOC);

    $rolesPermitidos = ['admin', 'profesor', 'estudiante'];

    if(!in_array($row['role'], $rolesPermitidos)){
        header("Location: no_admin.php");
        exit();
    }

    if(isset($_SESSION['user_login'])){  
        $ok=$_GET['ok'] ?? null;
        

        if(isset($_POST['inscribirs'])){
            if(!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])){
                echo "<script>Swal.fire('Error','Token inválido','error');</script>";
            } 
            else{
                $idasignatura = $_POST['idasignatura'];

                $check = $pdo->prepare("SELECT * FROM inscripciones WHERE idusuario = :u AND idasignatura = :a");
                $check->execute([':u' => $id, ':a' => $idasignatura ]);

                if($check->rowCount() > 0){
                    echo "<script>
                        Swal.fire('Aviso','Ya estás inscrito en esta asignatura','info');
                    </script>";
                } 
                else{
                    $stmt = $pdo->prepare("INSERT INTO inscripciones(idusuario, idasignatura) VALUES(:u, :a)");
                    $stmt->execute([':u'=>$id, ':a'=>$idasignatura ]);

                    echo "<script>
                        Swal.fire('Éxito','Inscripción realizada correctamente','success')
                        .then(()=> window.location='Carrera/actividades.php?idasignatura=".$idasignatura."');
                    </script>";
                }
            }
        }
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" type="image/png" href="../assets/img/logo.png">

        <title>
            <?php if($row['role'] == 'admin'): ?>
                <?php echo 'ADMINISTRADOR';?>
            <?php elseif($row['role'] == 'estudiante') :?>
                <?php echo 'ESTUDIANTES';?>
            <?php elseif($row['role'] == 'profesor') :?>
                <?php echo 'PROFESOR';?>       
            <?php endif; ?>
        </title>

        <link rel="stylesheet" href="../assets/css/style.css">
        <link rel="stylesheet" href="../assets/css/sidebar.css">
        <link rel="stylesheet" href="../assets/css/sidebaruser.css">
        <link rel="stylesheet" href="../assets/css/cards.css">

        <style>
            .dashboard-home {
                padding: 20px;
            }

            .stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
            }

            .stat-card {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: #fff;
                padding: 20px;
                border-radius: 12px;
                text-align: center;
                transition: 0.3s;
            }

            .stat-card:hover {
                transform: translateY(-5px);
            }

            .card {
                background: #fff;
                padding: 15px;
                border-radius: 12px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            }

            .list {
                list-style: none;
                padding: 0;
            }

            .list li {
                padding: 8px;
                border-bottom: 1px solid #eee;
            }
        </style>
    </head>

    <body>
        <header class="topbar">
            <div class="search-box"></div>

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
                        <a href="Carrera/promedio.php?idasignatura=<?= $idAsignatura ?? '' ?>">📋 Calificaciones</a>

                        <?php if($row['role'] == 'admin'): ?>
                            <a href="Usuarios/Todo.php">👥 Usuarios</a>
                        <?php endif; ?>

                        <hr>

                        <a href="../logout.php" class="logout">🚪 Cerrar sesión</a>
                    </div>
                </div>
            </div>

            <span class="menu-toggle" onclick="toggleMenu()">☰</span>
        </header>

        <aside class="sidebar">
            <div class="sidebar-logo">
                <img src="../assets/img/icono.png" style="width: 40%;" alt="">
            </div>

            <ul class="sidebar-menu">
                <li class="menu-item active">
                    <a href="#">
                        <span class="icon">📊</span>
                        <span>Inicio</span>
                    </a>
                </li>

                <li class="menu-item">
                    <?php
                        switch($row['role']){
                            case 'admin':
                                $link = "Ajustes/FormularioUsers.php?section=permi";
                                $icon = "🛡️";
                            break;

                            case 'profesor':
                                $link = "Lista/perfil.php";
                                $icon = "👨‍🏫";
                            break;

                            default:
                                $link = "Lista/perfil.php";
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
                                $asignatura->execute([ ':carrera' => $idCarrera ]);
                            ?>
                            <?php if($idCarrera == null): ?>
                                <a href="todo.php" class="btn-primary">
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
                                    <a href="Carrera/actividadesD.php">
                                        📦 Actividades
                                    </a>
                                </li>

                                <li>
                                    <a href="Carrera/promedio.php?idasignatura=<?= $idAsignatura ?? '' ?>">
                                        📋 Promedios
                                    </a>
                                </li>
                            
                                <li>
                                    <a href="Carrera/añadir.php">
                                        ➕ Añadir
                                    </a>
                                </li>

                                <li>
                                    <a href="Carrera/actualizar.php">
                                        🔒 Ver
                                    </a>
                                </li>
                            </ul>
                        <?php elseif($row['role'] == 'profesor'): ?>
                            <ul class="cta horizontal">
                                <li>
                                    <a href="Carrera/actividades.php?idCarrera=<?=  $idCarrera ?>">
                                        📦 Actividades
                                    </a>
                                </li>

                                <li>
                                    <a href="Carrera/promedio.php?idasignatura=<?= $idAsignatura ?? '' ?>">
                                        📋 Promedios
                                    </a>
                                </li>
                            
                                <li>
                                    <a href="Carrera/añadir.php?idCarrera=<?=  $idCarrera ?>">
                                        ➕ Añadir
                                    </a>
                                </li>

                                <li>
                                    <a href="Carrera/actualizar.php?idCarrera=<?=  $idCarrera ?>">
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
                                    <a href="Carrera/promedio.php?idasignatura=<?= $idAsignatura ?? '' ?>">
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
                        <a href="todo.php">
                            <span class="icon">🏫</span>
                            <span>Carreras</span>
                        </a>
                    <?php else:?>  
                        
                    <?php endif; ?>
                </li>

                <li class="menu-item">
                    <a href="Lista/lista.php?idCarrera=<?=  $idCarrera ?>">
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
                                    <a href="Usuarios/Administrador.php" style="color:#000;">🛡️ Administradores</a>
                                    <a href="Usuarios/Profesores.php" style="color:#000;">👨‍🏫 Profesores</a>
                                    <a href="Usuarios/Estudiantes.php" style="color:#000;">🎓 Estudiantes</a>
                                    <a href="Usuarios/Todo.php" style="color:#000;">🌐 Todo</a>
                                </ul>
                            </div>

                            <ul class="cta horizontal">
                                <li>
                                    <a href="Usuarios/Catalogo.php">
                                        📦 Catálogo
                                    </a>
                                </li>
                                <li>
                                    <a href="Usuarios/Todo.php">
                                        📋 Listado
                                    </a>
                                </li>
                                <li>
                                    <a href="Ajustes/FormularioUsers.php?section=permi">
                                        ➡ <?php echo strtoupper($row['role']);?>
                                    </a>
                                </li>
                                <li>
                                    <a href="Ajustes/FormularioUsers.php?section=cuentas">
                                        ➕ Añadir
                                    </a>
                                </li>
                            </ul>
                        </div> 
                    <?php else:?>  
                        
                    <?php endif; ?>
                </li>
            </ul>
        </aside>
        
        <main class="content main">
            <section id="inicio" class="dashboard-home">
                <div class="dashboard-header">
                    <h1>👋 Bienvenido <?= htmlspecialchars($rows['name']) ?></h1>
                    <p>Panel <?= strtoupper($row['role']) ?></p>
                </div>

                <?php if($row['role'] == 'admin'): ?>

                <!-- ================= ADMIN ================= -->
                <div class="stats">
                    <div class="stat-card">
                        <h3>👥 
                        <?php
                        $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                        echo $totalUsers;
                        ?>
                        </h3>
                        <p>Usuarios</p>
                    </div>

                    <div class="stat-card">
                        <h3>📚 
                        <?php
                        $totalCarreras = $pdo->query("SELECT COUNT(*) FROM carrera")->fetchColumn();
                        echo $totalCarreras;
                        ?>
                        </h3>
                        <p>Carreras</p>
                    </div>

                    <div class="stat-card">
                        <h3>📖 
                        <?php
                        $totalAsignaturas = $pdo->query("SELECT COUNT(*) FROM asignatura")->fetchColumn();
                        echo $totalAsignaturas;
                        ?>
                        </h3>
                        <p>Asignaturas</p>
                    </div>
                </div>

                <div class="card">
                    <h3>⚙️ Administración</h3>
                    <ul class="list">
                        <li>Gestionar usuarios</li>
                        <li>Crear carreras</li>
                        <li>Administrar asignaturas</li>
                    </ul>
                </div>

                <?php elseif($row['role'] == 'profesor'): ?>

                <!-- ================= PROFESOR ================= -->
                <div class="stats">
                    <div class="stat-card">
                        <h3>📚 
                        <?php
                        $totalAsig = $pdo->query("SELECT COUNT(*) FROM asignatura")->fetchColumn();
                        echo $totalAsig;
                        ?>
                        </h3>
                        <p>Asignaturas</p>
                    </div>

                    <div class="stat-card">
                        <h3>👨‍🎓 
                        <?php
                        $totalAlumnos = $pdo->query("SELECT COUNT(*) FROM users WHERE role='estudiante'")->fetchColumn();
                        echo $totalAlumnos;
                        ?>
                        </h3>
                        <p>Alumnos</p>
                    </div>
                </div>

                <div class="card">
                    <h3>📌 Actividades</h3>
                    <ul class="list">
                        <li>Subir tareas</li>
                        <li>Revisar entregas</li>
                        <li>Calificar alumnos</li>
                    </ul>
                </div>

                <?php else: ?>

                <!-- ================= ESTUDIANTE ================= -->
                <div class="stats">
                    <div class="stat-card">
                        <h3>📖 
                        <?php
                        $misAsig = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE idusuario=:id");
                        $misAsig->execute([':id'=>$id]);
                        echo $misAsig->fetchColumn();
                        ?>
                        </h3>
                        <p>Mis materias</p>
                    </div>

                    <div class="stat-card">
                        <h3>📝 
                        <?php
                        $tareas = rand(1,5); // luego lo haces real
                        echo $tareas;
                        ?>
                        </h3>
                        <p>Tareas pendientes</p>
                    </div>
                </div>

                <div class="card">
                    <h3>📌 Actividades</h3>
                    <ul class="list">
                        <li>Ver tareas</li>
                        <li>Enviar actividades</li>
                        <li>Revisar calificaciones</li>
                    </ul>
                </div>

                <?php endif; ?>

            </section>
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
                            $asignaturasAll = $pdo->prepare("SELECT * FROM asignatura 
                                WHERE idcarrera = :carrera");
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

                <form method="GET" action="Carrera/actividades.php"> 
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
<?php
    }
?>