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

        if(isset($_POST['guardarCarrera'])){
            $title = $_POST['title'];
            $duration = $_POST['duration'];
            $start = $_POST['start_date'];
            $end = $_POST['end_date'];
            $desc = $_POST['description'];

            $nombreImagen = null;
            
            if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){

                $carpeta = "../assets/img/carreras/";
                
                // Crear carpeta si no existe
                if(!is_dir($carpeta)){
                    mkdir($carpeta, 0777, true);
                }

                $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $nombreImagen = uniqid("carrera_") . "." . $extension;

                $ruta = $carpeta . $nombreImagen;

                move_uploaded_file($_FILES['image']['tmp_name'], $ruta);
            }

            // Validar duplicado
            $check = $pdo->prepare("SELECT * FROM carrera WHERE title = :title");
            $check->execute([':title'=>$title]);

            if($check->rowCount() > 0){
                echo "<script>Swal.fire('Error','La carrera ya existe','error');</script>";
            }
            else{
                $stmt = $pdo->prepare("INSERT INTO carrera 
                    (title, duration, start_date, end_date, description, image) 
                    VALUES (:title, :duration, :start, :end, :desc, :img)");

                $stmt->execute([
                    ':title'=>$title,
                    ':duration'=>$duration,
                    ':start'=>$start,
                    ':end'=>$end,
                    ':desc'=>$desc,
                    ':img'=>$nombreImagen
                ]);

                echo "<script>
                    Swal.fire('Éxito','Carrera creada correctamente','success')
                    .then(()=> location.reload());
                </script>";
            }
        }
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
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            .paginacion {
                display: flex;
                justify-content: center;
                align-items: center;
                margin-top: 25px;
                gap: 8px;
                flex-wrap: wrap;
            }

            .paginacion .btn {
                padding: 8px 14px;
                background: #2c2c2c;
                color: #fff;
                border-radius: 8px;
                text-decoration: none;
                transition: 0.3s;
                font-size: 14px;
            }

            .paginacion .btn:hover {
                background: #444;
            }

            .paginacion .activo {
                background: #f1c40f;
                color: #000;
                font-weight: bold;
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
                                $idCarrera = $rowArea['idcarrera']?? null;

                                $asignatura = $pdo->prepare("SELECT  *FROM asignatura A
                                                            INNER JOIN carrera B ON A.idcarrera = B.id
                                                            WHERE A.idcarrera = :carrera");
                                $asignatura->execute([':carrera' => $idCarrera ]);
                            ?>
                            <?php if($idCarrera == null): ?>
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
                        <a href="#">
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
            </ul>
        </aside>
        
        <main class="content main">
            <?php
$limite = 6;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

$buscar = $_GET['buscar'] ?? '';

$query = "SELECT * FROM carrera 
          WHERE title LIKE :buscar 
          LIMIT :limite OFFSET :offset";

$stmt = $pdo->prepare($query);
$stmt->bindValue(':buscar', "%$buscar%");
$stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// TOTAL
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM carrera WHERE title LIKE :buscar");
$totalStmt->execute([':buscar' => "%$buscar%"]);
$total = $totalStmt->fetchColumn();

$totalPaginas = ceil($total / $limite);
?>

<!-- 🔍 BUSCADOR -->
<form method="GET" style="margin-bottom:20px;">
    <input type="text" name="buscar" placeholder="Buscar carrera..." 
           value="<?= htmlspecialchars($buscar) ?>" 
           style="padding:8px; width:200px;">
    <button type="submit">🔍</button>
</form>

<div class="cards-container1">

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
];
?>

<?php foreach ($users as $user): ?> 

<?php
if(!empty($user['image'])){
    $img = "../assets/img/carreras/" . $user['image'];
}else{
    $index = $user['id'] % count($imagenesDefault);
    $img = $imagenesDefault[$index];
}
?>

<div class="card-netflix" onclick="irCarrera(<?= $user['id']; ?>)">
    <img src="<?= $img; ?>" class="card-bg">
    <div class="overlay"></div>

    <div class="card-info">
        <h3><?= $user['title']; ?></h3>
        <p><?= $user['duration']; ?> meses</p>
    </div>

    <?php if($row['role'] == 'admin'): ?>
    <div class="card-actions" onclick="event.stopPropagation();">
        <a href="javascript:void(0)" onclick="abrirModalEditar(<?= $user['id']; ?>)">⚙️</a>
        <a href="javascript:void(0)" onclick="eliminarCarrera(<?= $user['id']; ?>)">🗑️</a>
    </div>
    <?php endif; ?>

</div>

<?php endforeach; ?>
</div>

<!-- 📄 PAGINACIÓN -->
<div class="paginacion">
    <?php if($pagina > 1): ?>
        <a href="?pagina=<?= $pagina-1 ?>&buscar=<?= urlencode($buscar) ?>" class="btn">⏮</a>
    <?php endif; ?>

    <?php
    $inicio = max(1, $pagina - 2);
    $fin = min($totalPaginas, $pagina + 2);

    for($i = $inicio; $i <= $fin; $i++):
    ?>
        <a href="?pagina=<?= $i ?>&buscar=<?= urlencode($buscar) ?>"
           class="btn <?= ($i == $pagina) ? 'activo' : '' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>

    <?php if($pagina < $totalPaginas): ?>
        <a href="?pagina=<?= $pagina+1 ?>&buscar=<?= urlencode($buscar) ?>" class="btn">⏭</a>
    <?php endif; ?>
</div>
        </main>

        <div id="modalEditarCarrera" class="modal">
            <div class="modal-content">

                <h2>✏️ Editar Carrera</h2>

                <form id="formEditarCarrera" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="title" id="edit_title" required>
                    </div>

                    <div class="form-group">
                        <label>Duración (meses)</label>
                        <input type="number" name="duration" id="edit_duration" required>
                    </div>

                    <div class="form-group">
                        <label>Fecha inicio</label>
                        <input type="date" name="start_date" id="edit_start">
                    </div>

                    <div class="form-group">
                        <label>Fecha fin</label>
                        <input type="date" name="end_date" id="edit_end">
                    </div>

                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="description" id="edit_desc"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Imagen</label>
                        <input type="file" name="image" id="edit_image" accept="image/*">

                        <div class="preview-container">
                            <img id="preview_img" src="" alt="Vista previa">
                        </div>
                    </div>

                    <div class="modal-buttons">
                        <button type="submit" class="btn-save">💾 Guardar</button>
                        <button type="button" onclick="cerrarModalEditar()" class="btn-cancel">❌ Cancelar</button>
                    </div>
                </form>

            </div>
        </div>

        <div id="modalNuevaCarrera" class="modal" style="display:none;">
            <div class="modal-content">
                <h3>Agregar nueva carrera</h3>

                <form method="POST" enctype="multipart/form-data">
                    <label>Nombre</label>
                    <input type="text" name="title" required>

                    <label>Imagen</label>
                    <input type="file" name="image" accept="image/*">

                    <label>Duración (meses)</label>
                    <input type="number" name="duration" required>

                    <label>Fecha inicio</label>
                    <input type="date" name="start_date">

                    <label>Fecha fin</label>
                    <input type="date" name="end_date">

                    <label>Descripción</label>
                    <textarea name="description"></textarea>

                    <br><br>

                    <button type="submit" name="guardarCarrera" class="btn-primary">
                        ✅ Guardar
                    </button>

                    <button type="button" onclick="cerrarModalNuevaCarrera()" class="btn-cancelar">
                        ❌ Cancelar
                    </button>
                </form>
            </div>
        </div>

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

        <script>
            function abrirModalEditar(id){

    fetch('get_carrera.php?id=' + id)
    .then(res => res.json())
    .then(data => {

        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_title').value = data.title;
        document.getElementById('edit_duration').value = data.duration;
        document.getElementById('edit_start').value = data.start_date;
        document.getElementById('edit_end').value = data.end_date;
        document.getElementById('edit_desc').value = data.description;

        // 👇 IMAGEN ACTUAL
        let img = data.image 
            ? "../assets/img/carreras/" + data.image 
            : "../assets/img/default/1.jpg";

        document.getElementById('preview_img').src = img;

        document.getElementById('modalEditarCarrera').style.display = 'flex';
    });
}

            function cerrarModalEditar(){
                document.getElementById('modalEditarCarrera').style.display = 'none';
            }

            document.getElementById('formEditarCarrera').addEventListener('submit', function(e){
    e.preventDefault();

    let formData = new FormData(this);

    fetch('editar_carrera.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {

        if(data.success){

            Swal.fire({
                icon: 'success',
                title: 'Actualizado',
                text: 'La carrera se actualizó correctamente'
            }).then(()=>{
                location.reload();
            });

        }else{
            Swal.fire('Error', data.message, 'error');
        }

    });
});

document.getElementById('edit_image').addEventListener('change', function(e){

    const file = e.target.files[0];

    if(file){
        const reader = new FileReader();

        reader.onload = function(e){
            document.getElementById('preview_img').src = e.target.result;
        }

        reader.readAsDataURL(file);
    }
});
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
        <script>
            function eliminarCarrera(id){
                Swal.fire({
                    title: '¿Eliminar?',
                    text: 'No podrás recuperar esta carrera',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar'
                }).then((result)=>{
                    if(result.isConfirmed){
                        fetch('eliminar_carrera.php?id=' + id)
                        .then(res => res.json())
                        .then(data=>{
                            if(data.success){
                                Swal.fire('Eliminado','','success')
                                .then(()=> location.reload());
                            }else{
                                Swal.fire('Error', data.message, 'error');
                            }
                        });
                    }
                });
            }
        </script>
    </body>
</html>
<?php
    }
?>