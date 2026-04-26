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

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $stmt = $pdo->prepare("UPDATE perfil SET nombre = :nombre, apellido = :apellido,  name = :name, estado = :estado, ciudad = :ciudad, calle1 = :calle1, calle2 = :calle2, cp = :cp, pais = :pais
                WHERE iduser = ( SELECT id FROM users WHERE email = :email )");
            $stmt->execute([
                ':nombre' => $_POST['nombre'],
                ':apellido' => $_POST['apellido'],
                ':name' => $_POST['name'],
                ':estado' => $_POST['estado'],
                ':ciudad' => $_POST['ciudad'],
                ':calle1' => $_POST['calle1'],
                ':calle2' => $_POST['calle2'],
                ':cp' => $_POST['cp'],
                ':pais' => $_POST['pais'],
                ':email' => $_POST['email'] ]);

            $stmt2 = $pdo->prepare("UPDATE users SET role = :role WHERE email = :email");
            $stmt2->execute([
                ':role' => $_POST['role'],
                ':email' => $_POST['email'] ]);

            header("Location: Administrador.php?ok=1");
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
                            <a href="../Carrera/promedio.php?idasignatura=<?= $idAsignatura ?? '' ?>">📋 Calificaciones</a>

                            <?php if($row['role'] == 'admin'): ?>
                                <a href="Todo.php">👥 Usuarios</a>
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
                                        <a href="#" style="color:#000;">🛡️ Administradores</a>
                                        <a href="Profesores.php" style="color:#000;">👨‍🏫 Profesores</a>
                                        <a href="Estudiantes.php" style="color:#000;">🎓 Estudiantes</a>
                                        <a href="Todo.php" style="color:#000;">🌐 Todo</a>
                                    </ul>
                                </div>

                                <ul class="cta horizontal">
                                    <li>
                                        <a href="Catalogo.php">
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
                <div>
                    <div class="card">
                        <table class="permissions-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Sobrenombre</th>
                                    <th class="center">Correo Electronico</th>
                                    <th class="center">Rol</th>
                                    <th class="center">Direccion</th>
                                    <th class="center">Acciones</th>
                                </tr>
                            </thead>

                            <?php
                                $Usuariost = $pdo->prepare("SELECT B.nombre, B.name, B.apellido, A.email AS EM, A.role, B.estado, B.ciudad, B.calle1, B.calle2, B.cp, B.pais FROM users A INNER JOIN perfil B ON A.ID = B.iduser WHERE A.role='archivado';");
                                $Usuariost->execute();
                                $users = $Usuariost->fetchAll(PDO::FETCH_ASSOC);
                            ?>

                            <?php foreach ($users as $user): ?> 
                                <tbody>
                                    <tr>
                                        <td><?php echo $user['nombre'].' '.$user['apellido']; ?> </td>
                                        <td><?php echo $user['name']; ?> </td>
                                        <td><?php echo $user['EM']; ?></td>
                                        <td><?php echo $user['role']; ?></td>
                                        <td><?php echo $user['estado'].'  '.$user['ciudad'].'  '.$user['calle1'].' '.$user['calle2'].'  '.$user['cp'].'  '.$user['pais'] ; ?></td>
                                        <td class="center">
                                            <button class="btn-edit"
                                                data-id="<?php echo $user['EM']; ?>"
                                                data-nombre="<?php echo $user['nombre']; ?>"
                                                data-apellido="<?php echo $user['apellido']; ?>"
                                                data-name="<?php echo $user['name']; ?>"
                                                data-email="<?php echo $user['EM']; ?>"
                                                data-estado="<?php echo $user['estado']; ?>"
                                                data-ciudad="<?php echo $user['ciudad']; ?>"
                                                data-calle1="<?php echo $user['calle1']; ?>"
                                                data-calle2="<?php echo $user['calle2']; ?>"
                                                data-cp="<?php echo $user['cp']; ?>"
                                                data-pais="<?php echo $user['pais']; ?>"
                                                data-role="<?php echo $user['role']; ?>"
                                            >
                                                Editar
                                            </button>
                                        </td>
                                    </tr>                            
                                </tbody>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>     
            </main>

            <?php if($ok==1): ?>
                <script>
                    Swal.fire({
                        icon:"success",
                        text:"El usuario ha sido modificado."
                    });
                </script>    
            <?php endif; ?> 

            <div id="editModal" class="modal">
                <div class="modal-content">

                    <div class="modal-header">
                        <h2>Editar Usuario</h2>
                        <span class="close" onclick="closeModal()">&times;</span>
                    </div>

                    <form method="POST" class="modal-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="email" id="edit_email">

                        <div class="grid">
                            <div class="input-group">
                                <label>Nombre</label>
                                <input type="text" name="nombre" id="edit_nombre">
                            </div>

                            <div class="input-group">
                                <label>Apellido</label>
                                <input type="text" name="apellido" id="edit_apellido">
                            </div>

                            <div class="input-group">
                                <label>Sobrenombre</label>
                                <input type="text" name="name" id="edit_name">
                            </div>

                            <div class="input-group">
                                <label>Estado</label>
                                <input type="text" name="estado" id="edit_estado">
                            </div>

                            <div class="input-group">
                                <label>Ciudad</label>
                                <input type="text" name="ciudad" id="edit_ciudad">
                            </div>

                            <div class="input-group">
                                <label>Código Postal</label>
                                <input type="text" name="cp" id="edit_cp">
                            </div>

                            <div class="input-group">
                                <label>Calle 1</label>
                                <input type="text" name="calle1" id="edit_calle1">
                            </div>

                            <div class="input-group">
                                <label>Calle 2</label>
                                <input type="text" name="calle2" id="edit_calle2">
                            </div>

                            <div class="input-group full">
                                <label>País</label>
                                <input type="text" name="pais" id="edit_pais">
                            </div>

                            <div class="input-group full">
                                <label>Rol</label>
                                <select name="role" id="edit_role">
                                    <option value="admin">Admin</option>
                                    <option value="estudiante">Estudiante</option>
                                    <option value="profesor">Profesor</option>
                                    <option value="archivado">Archivado</option>
                                </select>
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button type="submit" class="btn-save">Guardar</button>
                            <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                document.querySelectorAll('.btn-edit').forEach(button => {
                    button.addEventListener('click', function(){
                        document.getElementById('edit_email').value = this.dataset.email;
                        document.getElementById('edit_nombre').value = this.dataset.nombre;
                        document.getElementById('edit_apellido').value = this.dataset.apellido;
                        document.getElementById('edit_name').value = this.dataset.name;
                        document.getElementById('edit_estado').value = this.dataset.estado;
                        document.getElementById('edit_ciudad').value = this.dataset.ciudad;
                        document.getElementById('edit_calle1').value = this.dataset.calle1;
                        document.getElementById('edit_calle2').value = this.dataset.calle2;
                        document.getElementById('edit_cp').value = this.dataset.cp;
                        document.getElementById('edit_pais').value = this.dataset.pais;
                        document.getElementById('edit_role').value = this.dataset.role;

                        document.getElementById('editModal').style.display = 'flex';
                    });
                });

                function closeModal(){
                    document.getElementById('editModal').style.display = 'none';
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