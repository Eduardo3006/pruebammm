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
        $idCarrera=$_GET['idCarrera'];

        
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
        
        if(isset($_POST['accion']) && $_POST['accion'] == 'crear_asignatura'){

            $tema = trim($_POST['tema']);
            $idcarrera = $_POST['idcarrera'];

            if(!empty($tema)){
                $stmt = $pdo->prepare("
                    INSERT INTO asignatura (tema, idcarrera) 
                    VALUES (:t, :c)
                ");

                $stmt->execute([
                    ':t' => $tema,
                    ':c' => $idcarrera
                ]);

                echo "<script>
                    Swal.fire('Éxito','Asignatura creada correctamente','success')
                    .then(()=> window.location='?idCarrera=".$idcarrera."');
                </script>";
            }else{
                echo "<script>
                    Swal.fire('Error','El nombre es obligatorio','error');
                </script>";
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
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <style>
            .btn.green{
                background: #00c896;
                color: #fff;
                border: none;
                padding: 10px 18px;
                border-radius: 25px;
                cursor: pointer;
                font-weight: 600;
                transition: 0.3s;
            }

            .btn.green:hover{
                background: #00b386;
                transform: translateY(-2px);
            }

            /* BOTÓN CANCELAR */
            .btn.red{
                background: #ff5c5c;
                color: #fff;
                border: none;
                padding: 10px 18px;
                border-radius: 25px;
                cursor: pointer;
                font-weight: 600;
            }

            /* MODAL FONDO */
            .modal{
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.6);
                backdrop-filter: blur(5px);
                justify-content: center;
                align-items: center;
                z-index: 999;
            }

            /* CONTENIDO DEL MODAL */
            .modal-content{
                background: #fff;
                width: 400px;
                max-width: 90%;
                border-radius: 16px;
                padding: 25px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                animation: fadeIn .3s ease;
            }

            /* ANIMACIÓN */
            @keyframes fadeIn{
                from{
                    transform: scale(.9);
                    opacity: 0;
                }
                to{
                    transform: scale(1);
                    opacity: 1;
                }
            }

            /* TITULO */
            .modal-content h3{
                margin-bottom: 15px;
            }

            /* INPUT */
            .modal-content input{
                width: 100%;
                padding: 10px;
                border-radius: 10px;
                border: 1px solid #ccc;
                margin-bottom: 15px;
                font-size: 14px;
            }

            /* ACCIONES */
            .modal-content .actions{
                display: flex;
                justify-content: space-between;
                gap: 10px;
            }

            /* CARDS GRID MEJORADO */
            .cards{
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }

            /* CARD */
            .card{
                background: #fff;
                border-radius: 16px;
                padding: 15px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.08);
                cursor: pointer;
                transition: 0.3s;
                text-align: center;
            }

            .card:hover{
                transform: translateY(-5px);
            }

            /* IMAGEN */
            .card img{
                width: 100%;
                height: 130px;
                object-fit: cover;
                border-radius: 12px;
                margin-bottom: 10px;
            }

            /* TITULO */
            .card h3{
                font-size: 16px;
                margin-bottom: 5px;
            }

            /* TEXTO */
            .card p{
                font-size: 13px;
                color: #666;
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
                    <a href="dashboard.php">
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
                                $idCarreras = $rowArea['idcarrera']?? null;

                                $asignatura = $pdo->prepare("SELECT  *FROM asignatura A
                                                            INNER JOIN carrera B ON A.idcarrera = B.id
                                                            WHERE A.idcarrera = :carrera");
                                $asignatura->execute([':carrera' => $idCarreras ]);
                            ?>
                            <?php if($idCarreras == null): ?>
                                <a href="todoa.php" class="btn-primary">
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
                        <?php else: ?>
                            <ul>
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
                    <a href="Lista/lista.php">
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
                <li class="menu-item">
                    <a href="#">
                        <span class="icon">📦</span>
                        <span>Recursos</span>
                    </a>
                     
                </li>
            </ul>
        </aside>
    
        <main class="main">
            <?php if($row['role'] == 'admin' || $row['role'] == 'profesor'): ?>
                <button class="btn green" onclick="abrirModalAsignaturaNueva()">
                    ➕ Nueva Asignatura
                </button>
            <?php endif; ?>
            <?php
                $imagenesDefault = [
                    "../assets/img/default/1.jpg",
                    "../assets/img/default/2.jpg",
                    "../assets/img/default/3.jpg",
                    "../assets/img/default/4.jpg",
                    "../assets/img/default/5.jpg",
                    "../assets/img/default/6.jpg",
                    "../assets/img/default/7.jpg",
                    "../assets/img/default/8.jpg",
                    "../assets/img/default/9.jpg",
                    "../assets/img/default/10.jpg",
                    "../assets/img/default/11.jpg",
                    "../assets/img/default/12.jpg",
                    "../assets/img/default/13.jpg",
                    "../assets/img/default/14.jpg",
                    "../assets/img/default/15.jpg",
                    "../assets/img/default/16.jpg",
                    "../assets/img/default/17.jpg",
                ];
                    
                $img = $imagenesDefault;
                $w = $pdo->prepare("SELECT * FROM carrera WHERE id = :idd");
                $w->execute([":idd"=>$idCarrera]);
                $ww = $w->fetch(PDO::FETCH_ASSOC);
            ?>
            <h2><?= htmlspecialchars($ww['title']) ?></h2>

           
            <?php
                $Usuariost = $pdo->prepare("SELECT *FROM asignatura WHERE idcarrera = :carrera;");
                $Usuariost->execute([':carrera' => $idCarrera ]);
                $users = $Usuariost->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="cards">
                <?php foreach ($users as $users1): ?>  
                    <div class="card" onclick="irAsignatura(<?= $users1['idasignatura'] ?>)">
                        <img src="<?= $img[array_rand($img)] ?>">

                        <h3><?= htmlspecialchars($users1['tema']) ?></h3>

                        <p>ID: <?= htmlspecialchars($users1['idasignatura']) ?></p>
                    </div>             
                <?php endforeach; ?>
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
        
        <div id="modalNuevaAsignatura" class="modal" style="display:none;">
            <div class="modal-content">
                <h3>➕ Nueva Asignatura</h3>

                <form method="POST">
                    <input type="hidden" name="accion" value="crear_asignatura">

                    <label>Nombre de la asignatura</label>
                    <input type="text" name="tema" required>

                    <input type="hidden" name="idcarrera" value="<?= $idCarrera ?>">

                    <br><br>

                    <button type="submit" class="btn green">Guardar</button>
                    <button type="button" onclick="cerrarModalAsignaturaNueva()" class="btn red">Cancelar</button>
                </form>
            </div>
        </div>
        <script>
            function abrirModalAsignaturaNueva(){
                document.getElementById('modalNuevaAsignatura').style.display = 'flex';
            }

            function cerrarModalAsignaturaNueva(){
                document.getElementById('modalNuevaAsignatura').style.display = 'none';
            }
        </script>

        <script>
            function irAsignatura(id){
                window.location = "Carrera/actividades.php?idasignatura=" + id;
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
<?php
    }
?>