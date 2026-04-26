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

    $rolesPermitidos = ['admin', 'profesor'];

    if(!in_array($row['role'], $rolesPermitidos)){
        header("Location: ../no_admin.php");
        exit();
    }

    if(isset($_SESSION['user_login'])){  
        $ok=$_GET['ok'] ?? null;

        if(isset($_POST['btnSave'])){ 
            if(!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])){
                $errorMsg[] = "Error de seguridad. Recarga la página.";
            } 
            else{
                $tema = trim($_POST['tema'] ?? '');
                $idcarrera = $_POST['idcarrera'] ?? '';

                if(empty($tema)){
                    $errorMsg[] = "El nombre de la asignatura es obligatorio.";
                } 
                else{
                    try{
                        $check = $pdo->prepare("SELECT COUNT(*) FROM asignatura WHERE tema = :tema AND idcarrera = :idcarrera");
                        $check->execute([':tema' => $tema, ':idcarrera' => $idcarrera]);

                        if($check->fetchColumn() > 0){
                            $errorMsg[] = "⚠️ Esta asignatura ya existe.";
                        } 
                        else{
                            $stmt = $pdo->prepare("INSERT INTO asignatura(idcarrera, tema) VALUES (:idcarrera, :tema)");
                            $stmt->execute([':idcarrera' => $idcarrera, ':tema' => $tema ]);

                            header("Location: añadir.php?ok=1");
                            exit();
                        }
                    } 
                    catch(Exception $e){
                        $errorMsg[] = "Error al guardar: " . $e->getMessage();
                    }
                }
            }
        }
?>
    <!DOCTYPE html>
    <html lang="en">
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
            <link rel="stylesheet" href="../../assets/css/formul.css">

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
                            <a href="Lista/perfil.php">👤 Mi perfil</a>
                            <a href="promedio.php?idasignatura=<?= $idAsignatura ?? '' ?>">📋 Calificaciones</a>

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
                                        <a href="#">
                                            ➕ Añadir
                                        </a>
                                    </li>

                                    <li>
                                        <a href="actualizar.php">
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
                        <a href="Rescursos.php">
                            <span class="icon">📦</span>
                            <span>Recursos</span>
                        </a>
                        
                    </li>
                </ul>
            </aside>

            <main class="content main">
                <section id="inicio" class="panel active dashboard-home">
                    <div class="card">
                        <h1 style="">
                            Nueva Asignatura
                        </h1>
                        <form method="POST" enctype="multipart/form-data" class="course-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Nombre de la Asignatura</label>
                                    <input name="tema" required>
                                </div>
                                <?php if($row['role'] == 'admin'): ?>
                                    <?php
                                        $Carrera = $pdo->prepare("SELECT *FROM carrera;");
                                        $Carrera->execute();
                                        $Carreras = $Carrera->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                        <div class="form-group">
                                            <label>Carrera</label>
                                            <select name="idcarrera">
                                                <?php foreach ($Carreras as $Carreras): ?> 
                                                    <option value="<?php echo $Carreras['id']; ?>">
                                                        <?php echo $Carreras['title']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php
                                        $Area = $pdo->prepare("SELECT  B.nombre ,A.role,C.title,C.id AS idcarrera, D.idcuatrimestre AS idcuatrimestre, D.temporada FROM users A
                                                                    INNER JOIN perfil B ON A.id = B.iduser
                                                                    INNER JOIN carrera C ON B.idcarrera = C.id
                                                                    INNER JOIN cuatrimestre D ON B.idcuatrimestre = D.idcuatrimestre 
                                                                    WHERE B.iduser= :uid");
                                        $Area->execute([":uid"=>$id]);
                                        $rowArea = $Area->fetch(PDO::FETCH_ASSOC);
                                    ?>                  
                                    <div class="form-group">
                                        <label>Carrera</label>
                                        <p>
                                            <?php echo $rowArea['title']?>
                                        </p>
                                        <input type="hidden" name="idcarrera" value="<?php echo $rowArea['idcarrera']?>">
                                    </div>

                                    <div class="form-group">
                                        <label>cuatrimestre</label>
                                        <p>
                                            <?php echo $rowArea['temporada']?>
                                        </p>
                                        <input type="hidden" name="idcuatrimestre" value="<?php echo $rowArea['idcuatrimestre']?>">
                                    </div>                     
                                <?php endif; ?>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="btnSave" class="btn-primary">
                                    Guardar
                                </button>
                            </div>
                        </form>
                    </div>
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

            <?php if($ok==1): ?>
                <script>
                    Swal.fire({
                        icon:"success",
                        text:"¡Excelente! Me alegra saber que el proceso de registro se completó correctamente."
                    });
                </script>    
            <?php endif; ?>  

            <?php if(!empty($errorMsg)): ?>
                <script>
                    Swal.fire({
                        icon: "error",
                        html: `
                            <?php foreach($errorMsg as $e): ?>
                                <p><?= htmlspecialchars($e) ?></p>
                            <?php endforeach; ?>
                        `
                    });
                </script>
            <?php endif; ?>

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
                document.querySelectorAll('.btn-upload').forEach(btn => {
                    btn.addEventListener('click', () => {
                        document.getElementById('idactividad').value = btn.dataset.id;
                        document.getElementById('modalSubir').style.display = 'flex';
                    });
                });

                function cerrarModal(){
                    document.getElementById('modalSubir').style.display = 'none';
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