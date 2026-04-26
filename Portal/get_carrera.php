<?php
    require("../Conexion/db.php");

    header('Content-Type: application/json');

    if(!isset($_GET['id'])){
        echo json_encode(['error' => 'ID requerido']);
        exit;
    }

    $id = $_GET['id'];

    $stmt = $pdo->prepare("SELECT id, title, duration FROM carrera WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if($data){
        echo json_encode($data);
    }
    else{
        echo json_encode(['error' => 'No encontrado']);
    }
?>