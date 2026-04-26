<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Acceso Denegado</title>
        <link rel="icon" type="image/png" href="../assets/img/logo.jpg">
        <style>
            *{
                margin:0;
                padding:0;
                box-sizing:border-box;
                font-family:'Segoe UI',sans-serif;
            }
            body{
                height:100vh;
                display:flex;
                align-items:center;
                justify-content:center;
                background:#f1f5f9;
            }
            .card{
                background:white;
                padding:40px;
                border-radius:15px;
                text-align:center;
                box-shadow:0 10px 30px rgba(0,0,0,.1);
                width:420px;
            }
            .icon{
                font-size:60px;
                color:#ef4444;
                margin-bottom:15px;
            }
            h1{
                font-size:24px;
                margin-bottom:10px;
                color:#0f172a;
            }
            p{
                color:#64748b;
                margin-bottom:25px;
            }
            .btn{
                display:inline-block;
                padding:12px 25px;
                background:#ef4444;
                color:white;
                text-decoration:none;
                border-radius:25px;
                font-weight:600;
                transition:.2s;
            }
            .btn:hover{
                background:#dc2626;
            }
        </style>
    </head>
    
    <body>
        <div class="card">

            <div class="icon">⛔</div>

            <h1>Acceso denegado</h1>

            <p>No tienes permisos de administrador para acceder a esta sección.</p>

            <a href="../index.php" class="btn">
                Cerrar sesión
            </a>
        </div>
    </body>
</html>