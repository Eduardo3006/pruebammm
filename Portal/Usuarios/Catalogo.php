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

    if($row['role'] != 'admin'){
        header("Location:../../no_admin.php");
        exit();
    }

    if(isset($_SESSION['user_login'])){    
        $ok=$_GET['ok'] ?? null;
        $select_stmt1 = $pdo->prepare("SELECT *FROM perfil WHERE iduser =:uid;");
        $select_stmt1->execute(array(":uid"=>$id));
        $row1=$select_stmt1->fetch(PDO::FETCH_ASSOC);
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
                .topbar {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .search input {
                    padding: 8px;
                    border-radius: 8px;
                    border: 1px solid #ddd;
                }

                .tabs {
                    margin: 20px 0;
                }

                .tabs button {
                    border: none;
                    padding: 8px 15px;
                    border-radius: 20px;
                    margin-right: 10px;
                    cursor: pointer;
                    background: #e5e7eb;
                }

                .tabs .active {
                    background: #10b981;
                    color: white;
                }

                .cards {
                    display: flex;
                    gap: 15px;
                }

                .card {
                    background: white;
                    border-radius: 15px;
                    padding: 10px;
                    width: 200px;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
                }

                .card img {
                    width: 100%;
                    border-radius: 10px;
                }

                .right-panel {
                    background: #fff;
                    padding: 20px;
                    border-left: 1px solid #eee;
                }

                .right-panel input,
                .right-panel select {
                    width: 100%;
                    padding: 8px;
                    margin-bottom: 10px;
                    border-radius: 8px;
                    border: 1px solid #ddd;
                }

                .checkbox label {
                    display: block;
                    margin-bottom: 5px;
                }
                .card.active {
                    border: 2px solid #10b981;
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
                            <a href="../Lista/perfil.php">👤 Mi perfil</a>
                            <a href="../Carrera/promedio.php?idasignatura=<?= $idAsignatura ?? '' ?>">📋 Calificaciones</a>

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
                                    $idCarreras = $rowArea['idcarrera']?? null;

                                    $asignatura = $pdo->prepare("SELECT  *FROM asignatura A
                                                                INNER JOIN carrera B ON A.idcarrera = B.id
                                                                WHERE A.idcarrera = :carrera");
                                    $asignatura->execute([':carrera' => $idCarreras ]);
                                ?>
                                <?php if($idCarreras == null): ?>
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

                            <?php if($row['role'] == 'admin' || $row['role'] == 'profesor'): ?>
                                <ul class="cta horizontal">
                                    <li>
                                        <a href="../Carrera/actividadesD.php">
                                            📦 Actividades
                                        </a>
                                    </li>

                                    <li>
                                        <a href="../Carrera/promedio.php?idasignatura=<?= $idAsignatura ?? '' ?>">
                                            📋 Promedios
                                        </a>
                                    </li>
                                
                                    <li>
                                        <a href="../Carrera/añadir.php">
                                            ➕ Añadir
                                        </a>
                                    </li>

                                    <li>
                                        <a href="../Carrera/actualizar.php">
                                            🔒 Ver
                                        </a>
                                    </li>
                                </ul>
                            <?php else: ?>
                                <ul>
                                    <li>
                                        <a href="javascript:void(0)" onclick="abrirModalAsignatura()">
                                            📦 Actividades
                                        </a>
                                    </li>

                                    <li>
                                        <a href="../Carrera/promedio.php?idasignatura=<?= $idAsignatura ?? '' ?>">
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
                        <a href="../Lista/lista.php">
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
                                        <a href="Administrador.php" style="color:#000;">🛡️ Administradores</a>
                                        <a href="Profesores.php" style="color:#000;">👨‍🏫 Profesores</a>
                                        <a href="Estudiantes.php" style="color:#000;">🎓 Estudiantes</a>
                                        <a href="Todo.php" style="color:#000;">🌐 Todo</a>
                                    </ul>
                                </div>

                                <ul class="cta horizontal">
                                    <li>
                                        <a href="#">
                                            📦 Catálogo
                                        </a>
                                    </li>
                                    <li>
                                        <a href="Todo.php">
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
                </ul>
            </aside>

            <main class="main">
                <div class="cards">
                    <div class="card" onclick="ir('all')">
                        <?php 
                            $todo = $pdo->query("SELECT COUNT(*) AS total FROM users"); 
                            $tod = $todo->fetch(PDO::FETCH_ASSOC); 
                        ?>
                        <img src="https://picsum.photos/300/150">
                        <h3>Todo</h3>
                        <p><?php echo $tod['total'];?> Usuarios</p>
                    </div>

                    <div class="card <?= $filtro=='estudiante'?'active':'' ?>" onclick="ir('estudiante')">
                        <?php 
                            $est = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE role='estudiante';"); 
                            $estu = $est->fetch(PDO::FETCH_ASSOC); 
                        ?>
                        <img src="https://picsum.photos/301/150">
                        <h3>Estudiantes</h3>
                        <p><?php echo $estu['total'];?> Usuarios</p>
                    </div>

                    <div class="card" onclick="ir('profesor')">
                        <?php 
                            $profe = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE role='profesor';"); 
                            $prof = $profe->fetch(PDO::FETCH_ASSOC); 
                        ?>
                        <img src="https://picsum.photos/302/150">
                        <h3>Profesores</h3>
                        <p><?php echo $prof['total'];?> Usuarios</p>
                    </div>

                    <div class="card" onclick="ir('archivado')">
                        <?php 
                            $arch = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE role='archivado';"); 
                            $arc = $arch->fetch(PDO::FETCH_ASSOC); 
                        ?>
                        <img src="https://picsum.photos/303/150">
                        <h3>Archivado</h3>
                        <p><?php echo $arc['total'];?> Usuarios</p>
                    </div>

                    <div class="card" onclick="ir('admin')">
                        <?php 
                            $AD = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE role='admin';"); 
                            $ADM = $AD->fetch(PDO::FETCH_ASSOC); 
                        ?>
                        <img src="https://picsum.photos/304/150">
                        <h3>Administradores</h3>
                        <p><?php echo $ADM['total'];?> Usuarios</p>
                    </div>

                </div>
            </main>

            <script>
                function ir(tipo){
                    window.location.href = "usuarios_lista.php?filtro=" + tipo;
                }

                function filtrar(tipo){
                    window.location.href = "?filtro=" + tipo;
                }
            </script>

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
                                $asignaturasAll->execute([':carrera' => $idCarrera ]);

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