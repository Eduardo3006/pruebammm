<?php
    require("../Conexion/db.php");

    header('Content-Type: application/json');

    $id = $_POST['id'] ?? null;

    if(!$id){
        echo json_encode(["success"=>false,"message"=>"ID requerido"]);
        exit;
    }

    $title = $_POST['title'];
    $duration = $_POST['duration'];
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];
    $desc = $_POST['description'];

    $nombreImagen = null;

    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
        $carpeta = "../assets/img/carreras/";

        if(!is_dir($carpeta)){
            mkdir($carpeta, 0777, true);
        }

        $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $nombreImagen = uniqid("carrera_") . "." . $extension;
        
        move_uploaded_file($_FILES['image']['tmp_name'], $carpeta . $nombreImagen);
    }

    if($nombreImagen){
        $stmt = $pdo->prepare("UPDATE carrera SET title=?, duration=?, start_date=?, end_date=?, description=?, image=? WHERE id=?");
        $stmt->execute([$title,$duration,$start,$end,$desc,$nombreImagen,$id]);

    }
    else{
        $stmt = $pdo->prepare("UPDATE carrera SET title=?, duration=?, start_date=?, end_date=?, description=? WHERE id=?");

        $stmt->execute([$title,$duration,$start,$end,$desc,$id]);
    }

    echo json_encode(["success"=>true]);
?>