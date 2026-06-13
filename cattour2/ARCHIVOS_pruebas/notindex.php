<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CatTour - Carga de Documentos</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; padding-top: 50px; background-color: #f4f4f9; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h2 { color: #333; }
        input[type="file"] { margin: 20px 0; display: block; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="card">
        <h2>🚢 CatTour: Subir Pasaporte</h2>
        <form action="procesar.php" method="post" enctype="multipart/form-data">
            <label>Selecciona el PDF del cliente:</label>
            <input type="file" name="archivo_pdf" accept="application/pdf" required>
            <button type="submit">Enviar al Servidor (HTTPS)</button>
        </form>
    </div>
</body>
</html>
