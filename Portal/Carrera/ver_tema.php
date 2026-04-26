<?php
    require(__DIR__ . "/../../Conexion/db.php");
    
    session_start();
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

    $rolesPermitidos = ['admin', 'profesor', 'estudiante'];
    if(!in_array($row['role'], $rolesPermitidos)){
        header("Location: ../../no_admin.php");
        exit();
    }

    if(isset($_SESSION['user_login'])){  
        $ok=$_GET['ok'] ?? null;
        $idAsignatura=$_GET['idasignatura'];

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
                        .then(()=> window.location='actividades.php?idasignatura=".$idasignatura."');
                    </script>";
                }
            }
        }
// ✅ VALIDAR INSCRIPCIÓN (SOLO ESTUDIANTE)
        if($row['role'] == 'estudiante'){
            $checkIns = $pdo->prepare("SELECT * FROM inscripciones WHERE idusuario = :u AND idasignatura = :a");
            $checkIns->execute([
                ':u' => $id,
                ':a' => $idAsignatura
            ]);

            if($checkIns->rowCount() == 0){
                echo "<h3>No estás inscrito en esta asignatura</h3>";
                exit;
            }
        }

        // ✅ GUARDAR ENTREGA
        if(isset($_POST['subirActividad'])){

            $idactividad = $_POST['idactividad'];

            // Validar archivo
            if(isset($_FILES['archivo']) && $_FILES['archivo']['error'] == 0){

                $uploadDir = "../../uploads/";

                // Crear carpeta si no existe
                if(!is_dir($uploadDir)){
                    mkdir($uploadDir, 0777, true);
                }

                // Nombre único
                $nombreArchivo = time() . "_" . basename($_FILES['archivo']['name']);
                $rutaDestino = $uploadDir . $nombreArchivo;

                // Mover archivo
                if(move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaDestino)){

                    // Verificar si ya entregó
                    $check = $pdo->prepare("SELECT * FROM entregas 
                        WHERE idactividad = :act AND idalumno = :alu");
                    $check->execute([
                        ':act'=>$idactividad,
                        ':alu'=>$id
                    ]);

                    if($check->rowCount() == 0){

                        // INSERTAR
                        $stmt = $pdo->prepare("INSERT INTO entregas 
                            (idactividad, idalumno, archivo) 
                            VALUES (:act, :alu, :arc)");

                        $stmt->execute([
                            ':act'=>$idactividad,
                            ':alu'=>$id,
                            ':arc'=>$nombreArchivo
                        ]);

                        echo "<script>
                            Swal.fire('Éxito','Actividad subida','success')
                            .then(()=> location.reload());
                        </script>";

                    }else{
                        echo "<script>
                            Swal.fire('Aviso','Ya entregaste esta actividad','info');
                        </script>";
                    }

                }else{
                    echo "<script>Swal.fire('Error','No se pudo subir el archivo','error');</script>";
                }

            }else{
                echo "<script>Swal.fire('Error','Selecciona un archivo','error');</script>";
            }
        }
        if($_POST){
            $uploadDir = "../../uploads/";

            if(!is_dir($uploadDir)){
                mkdir($uploadDir, 0777, true);
            }

            $pdfPath = null;

            if(!empty($_FILES['pdf']['name'])){
                $pdfName = time() . "_pdf_" . $_FILES['pdf']['name'];
                move_uploaded_file($_FILES['pdf']['tmp_name'], $uploadDir . $pdfName);
                $pdfPath = $pdfName;
            }

            $videoPath = null;

            if(!empty($_FILES['video_file']['name'])){
                $videoName = time() . "_video_" . $_FILES['video_file']['name'];

                move_uploaded_file($_FILES['video_file']['tmp_name'], $uploadDir . $videoName);

                $videoPath = $videoName;
            }

            $videoURL = $_POST['video_url'] ?? null;
    
            if($_POST['accion'] == 'crear'){
                $stmt = $pdo->prepare("INSERT INTO subtema (name, descriccion, objetivo, pdf, video, idasig) VALUES(:n,:d,:o,:p,:v,:i)");
                $stmt->execute([
                    ':n'=>$_POST['name'],
                    ':d'=>$_POST['descripcion'],
                    ':o'=>$_POST['objetivo'],
                    ':p'=>$pdfPath,
                    ':v'=>$videoPath ?? $videoURL,
                    ':i'=>$idAsignatura ]);
            }

            if($_POST['accion'] == 'editar'){
                $sql = "UPDATE subtema SET 
                        name=:n, descriccion=:d, objetivo=:o";

                if($pdfPath){
                    $sql .= ", pdf=:p";
                }

                if($videoPath || $videoURL){
                    $sql .= ", video=:v";
                }

                $sql .= " WHERE idsubtema=:id";

                $stmt = $pdo->prepare($sql);

                $params = [
                    ':n'=>$_POST['name'],
                    ':d'=>$_POST['descripcion'],
                    ':o'=>$_POST['objetivo'],
                    ':id'=>$_POST['id']
                ];

                if($pdfPath){
                    $params[':p'] = $pdfPath;
                }

                if($videoPath){
                    $params[':v'] = $videoPath;
                } 
                elseif($videoURL){
                    $params[':v'] = $videoURL;
                }

                $stmt->execute($params);
            }

            if($_POST['accion'] == 'eliminar'){
                $stmt = $pdo->prepare("DELETE FROM subtema WHERE idsubtema=:id");
                $stmt->execute([':id'=>$_POST['id']]);
            }

            header("Location: actividades.php?idasignatura=".$idAsignatura);
            exit;
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
            <!-- QUILL CSS -->
            <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

            <style>
                .main{
                    padding:20px;
                    background:#f4f6f9;
                }
                .layout{
                    display:flex;
                    gap:20px;
                }
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

                .right-content{
                    flex:1;
                }

                .header-top{
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                }

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
                .modal{
                    display:none;
                    position:fixed;
                    inset:0;
                    background:rgba(0,0,0,0.6);
                    backdrop-filter: blur(5px);
                    justify-content:center;
                    align-items:center;
                    z-index:999;
                }

                .modal-content{
                    background:#fff;
                    color: #000 !important;
                    width:500px;
                    max-width:90%;
                    border-radius:16px;
                    padding:25px;
                    box-shadow:0 10px 40px rgba(0,0,0,0.2);
                    animation:fadeIn .3s ease;
                }

                @keyframes fadeIn{
                    from{ transform:scale(.9); opacity:0;}
                    to{ transform:scale(1); opacity:1;}
                }

                .modal-content h3{
                    margin-bottom:15px;
                }

                .modal-content input,
                .modal-content textarea{
                    width:100%;
                    padding:10px;
                    border-radius:10px;
                    border:1px solid #121212;
                    margin-bottom:10px;
                }

                .modal-content input[type="file"]{
                    border:none;
                }

                .modal-content .actions{
                    display:flex;
                    justify-content:flex-end;
                    gap:10px;
                }

                .btn{
                    padding:8px 14px;
                    border:none;
                    border-radius:20px;
                    cursor:pointer;
                }

                .btn.green{
                    background:#00c896;
                    color:#fff;
                }

                .btn.red{
                    background:#ff5c5c;
                    color:#fff;
                }

                video, iframe{
                    margin-top:10px;
                    border-radius:10px;
                }
                .section-content{
                    display:block;
                    width:100%;
                    line-height:1.6;
                    font-size:15px;
                }

                .section-content p{
                    margin-bottom:10px;
                }

                .section-content ol,
                .section-content ul{
                    padding-left:20px;
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
                                    $idCarrera= $rowArea['idcarrera']?? null;

                                    $asignatura = $pdo->prepare("SELECT  *FROM asignatura A
                                                                INNER JOIN carrera B ON A.idcarrera = B.id
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
                                        <a href="actividad.php?idCarrera=<?=  $idCarrera ?>">
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
                        <a href="recurso.php">
                            <span class="icon">📦</span>
                            <span>Recursos</span>
                        </a>
                    </li>
                </ul>
            </aside>

            <?php
                $stmt = $pdo->prepare("SELECT * FROM asignatura WHERE idasignatura = :id");
                $stmt->execute([':id'=>$idAsignatura]);
                $asignatura = $stmt->fetch(PDO::FETCH_ASSOC);

                $lista = $pdo->prepare("SELECT * FROM asignatura WHERE idcarrera = :carrera");
                $lista->execute([':carrera'=>$asignatura['idcarrera']]);
            ?>
                
            <main class="main">
                <div class="layout">

                    <aside class="left">
                        <div class="sidebar-header">
                            <span>📘 Temas</span>
                        </div>

                        <ul class="menu-temas">
                            <?php $i=1; foreach($lista as $item): ?>
                                <li class="<?= $item['idasignatura']==$idAsignatura ? 'active':'' ?>">
                                    
                                    <span class="circle"><?= $i++ ?></span>

                                    <a href="actividades.php?idasignatura=<?= $item['idasignatura'] ?>">
                                        <?= htmlspecialchars($item['tema']) ?>
                                    </a>

                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </aside>

                    <div class="right-content">
                        <?php if($row['role'] != 'estudiante'): ?>
                            <button class="btn green" onclick="abrirModalCrear()">
                                ➕ Añadir lección
                            </button>
                        <?php endif; ?>

                        <div class="header-top">
                            <h2><?= htmlspecialchars($asignatura['tema']) ?></h2>
                            <br>
                            
                        </div>
 <div class="card_section">
                                <div>
                                    <?php
                                        $actividades = $pdo->prepare("SELECT * FROM actividades WHERE idasignatura = :id AND estado='activo'");
                                        $actividades->execute([':id'=>$idAsignatura]);
                                    ?>

                                    <h2>📋 Actividades</h2>

                                    <?php foreach($actividades as $act): 

                                        // verificar si ya entregó
                                        $entrega = $pdo->prepare("SELECT * FROM entregas WHERE idactividad = :a AND idalumno = :u");
                                        $entrega->execute([
                                            ':a' => $act['idactividad'],
                                            ':u' => $id
                                        ]);

                                        $yaEntrego = $entrega->rowCount() > 0;
                                    ?>
                                        <div class="card-section">
                                            <h3><?= htmlspecialchars($act['titulo']) ?></h3>
                                            <p><?= htmlspecialchars($act['descripcion']) ?></p>

                                            <?php if($yaEntrego): ?>
                                                <span style="color:green;">✅ Ya entregaste esta actividad</span>
                                            <?php else: ?>
                                                <button class="btn green" onclick="abrirModalEntrega(<?= $act['idactividad'] ?>)">
                                                    📤 Subir actividad
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php
                            $listas = $pdo->prepare("SELECT * FROM subtema WHERE idasig = :carrera");
                            $listas->execute([':carrera'=>$idAsignatura]);
                        ?>
                        <?php foreach($listas as $items): ?>
                            <?php if($row['role'] != 'estudiante'): ?>
                                <div>
                                    <button class="btn" onclick='abrirModalEditar(
                                        <?= json_encode($items["idsubtema"]?? "") ?>,
                                        <?= json_encode($items["name"]?? "") ?>,
                                        <?= json_encode($items["descriccion"]?? "") ?>,
                                        <?= json_encode($items["objetivo"]?? "") ?>
                                    )'>
                                        ✏️ Editar
                                    </button>

                                    <button class="btn red" onclick="confirmarEliminar(<?= $items['idsubtema'] ?>)">
                                        🗑 Eliminar
                                    </button>
                                </div>
                            <?php endif; ?>
                           

                            <?php if($items['name'] == null): ?>        
                            <?php else: ?>
                                <div class="card-section">
                                    <div class="section-header">
                                        
                                        <?= htmlspecialchars($items['name']) ?>
                                        <br>
                                        <br>                            
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if($items['descriccion'] == null): ?>
                                        
                            <?php else: ?>
                                <div class="card-section">
                                    <div class="section-content">
                                        <?= $items['descriccion']; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if($items['objetivo'] == null): ?>
                                        
                            <?php else: ?>
                                <div class="card-section">
                                    <h2>Objetivo</h2>
                                    <div class="section-content">
                                        <?= $items['objetivo']; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if($items['pdf']): ?>
                                <div class="card-section">
                                    <a href="../../uploads/<?= $items['pdf'] ?>" target="_blank" class="btn">
                                        📄 Ver PDF
                                    </a>
                                </div> 
                            <?php endif; ?>

                            <?php if($items['video'] && strpos($items['video'], '.mp4')): ?>
                                <div class="card-section">
                                    <video width="100%" controls>
                                        <source src="../../uploads/<?= $items['video'] ?>">
                                    </video>
                                </div>
                            <?php endif; ?>

                            <?php if($items['video'] && strpos($items['video'], 'youtube')): ?>
                                <div class="card-section">
                                    <iframe width="100%" height="300" src="<?= str_replace('watch?v=', 'embed/', $items['video']) ?>" frameborder="0" allowfullscreen> </iframe>
                                </div>
                            <?php endif; ?>        
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>   
        
            <div id="modalCrear" class="modal">
                <div class="modal-content">
                    <h3>➕ Nueva lección</h3>

                    <form method="POST" enctype="multipart/form-data" onsubmit="guardarContenido()">
                        <input type="hidden" name="accion" value="crear">

                        <input type="text" name="name" placeholder="Nombre" required>

                        <div id="editorDescripcion"></div>
                        <input type="hidden" name="descripcion" id="inputDescripcion">
                        
                        <br>
                        
                        <div id="editorObjetivo"></div>
                        <input type="hidden" name="objetivo" id="inputObjetivo">

                        <input type="file" name="pdf">
                        <input type="file" name="video_file">

                        <input type="text" name="video_url" placeholder="URL YouTube">

                        <div class="actions">
                            <button type="submit" class="btn green">Guardar</button>
                            <button type="button" onclick="cerrarModalCrear()">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="modalEditar" class="modal">
                <div class="modal-content">
                    <h3>✏️ Editar lección</h3>

                    <form method="POST" enctype="multipart/form-data" onsubmit="guardarEdit()">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" id="edit_id">

                        <input type="text" name="name" id="edit_name">

                        <div id="editorEditDesc"></div>
                        <input type="hidden" name="descripcion" id="edit_desc">

                        <br>

                        <div id="editorEditObj"></div>
                        <input type="hidden" name="objetivo" id="edit_obj">

                        <input type="file" name="pdf"><br><br>
                        <input type="file" name="video_file"><br><br>

                        <input type="text" name="video_url" placeholder="URL de YouTube">

                        <button type="submit" class="btn green">Actualizar</button>
                        <button type="button" onclick="cerrarModalEditar()">Cancelar</button>
                    </form>
                </div>
            </div>

            <script>
                var quillDesc = new Quill('#editorDescripcion', {
                    theme: 'snow'
                });

                var quillObj = new Quill('#editorObjetivo', {
                    theme: 'snow'
                });

                function guardarContenido(){
                    document.getElementById('inputDescripcion').value = quillDesc.root.innerHTML;
                    document.getElementById('inputObjetivo').value = quillObj.root.innerHTML;
                }
            </script>

            <script>
                function confirmarEliminar(id){
                    Swal.fire({
                        title: '¿Eliminar?',
                        text: "No podrás recuperar este subtema",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#00c896',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, eliminar'
                    }).then((result) => {
                        if (result.isConfirmed) {

                            let form = document.createElement('form');
                            form.method = 'POST';

                            let input1 = document.createElement('input');
                            input1.type = 'hidden';
                            input1.name = 'accion';
                            input1.value = 'eliminar';

                            let input2 = document.createElement('input');
                            input2.type = 'hidden';
                            input2.name = 'id';
                            input2.value = id;

                            form.appendChild(input1);
                            form.appendChild(input2);

                            document.body.appendChild(form);
                            form.submit();
                        }
                    })
                }
            </script>

            <script>
                function abrirModalCrear(){
                    document.getElementById('modalCrear').style.display='flex';
                }
                function cerrarModalCrear(){
                    document.getElementById('modalCrear').style.display='none';
                }

            </script>

            <script>
                let quillEditDesc;
                let quillEditObj;

                // Inicializar editores UNA vez
                document.addEventListener("DOMContentLoaded", function() {
                    quillEditDesc = new Quill('#editorEditDesc', {
                        theme: 'snow'
                    });

                    quillEditObj = new Quill('#editorEditObj', {
                        theme: 'snow'
                    });
                });

                // Abrir modal
                function abrirModalEditar(id, name, desc, obj){
                    document.getElementById('modalEditar').style.display = 'flex';

                    document.getElementById('edit_id').value = id || '';
                    document.getElementById('edit_name').value = name || '';

                    // Evita errores si vienen null
                    quillEditDesc.root.innerHTML = desc || '';
                    quillEditObj.root.innerHTML = obj || '';
                }

                // Guardar contenido antes de enviar
                function guardarEdit(){
                    document.getElementById('edit_desc').value = quillEditDesc.root.innerHTML;
                    document.getElementById('edit_obj').value = quillEditObj.root.innerHTML;
                }

                // Cerrar modal
                function cerrarModalEditar(){
                    document.getElementById('modalEditar').style.display = 'none';
                }
            </script>
          <div id="modalEntrega" class="modal">
    <div class="modal-content">
        <h3>Subir Actividad</h3>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="idactividad" id="idactividad">

            <input type="file" name="archivo" required>

            <br><br>

            <button type="submit" name="subirActividad" class="btn green">
                📤 Enviar
            </button>

            <button type="button" onclick="cerrarModalEntrega()">
                ❌ Cancelar
            </button>
        </form>
    </div>
</div>
<script>
function abrirModalEntrega(id){
    document.getElementById('modalEntrega').style.display = 'flex';
    document.getElementById('idactividad').value = id;
}

function cerrarModalEntrega(){
    document.getElementById('modalEntrega').style.display = 'none';
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
            <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
        </body>
    </html>
<?php
    }
?>