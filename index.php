<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

require 'config.php';
// ... resto de tu código

$uploadDir = 'uploads/';

// Crear carpeta si no existe
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
$maxSize = 10 * 1024 * 1024;

$mensaje = '';
$error_upload = '';

// --- LÓGICA DE SUBIDA Y CRUD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = $_POST['id'] ?? null;

    $num_exp = $_POST['numero_expediente'];
    $nombre = $_POST['alumno_nombre'];
    $apellido = $_POST['alumno_apellido'];
    $fecha_nac = $_POST['fecha_nacimiento'];
    $grado = $_POST['grado_actual'];
    $estatus = $_POST['estatus'];
    $observaciones = $_POST['observaciones'];

    $direccion = $_POST['direccion'];
    $correo = $_POST['correo'];
    $telefono = $_POST['telefono'];
    $num_control = $_POST['numero_control'];
    $carrera = $_POST['carrera'];
    $tutor = $_POST['tutor_asignado'];
    $tipo_programa = $_POST['tipo_programa'];

    $db_foto_perfil = null;

    // 1. SUBIDA DE FOTO
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['foto_perfil']['tmp_name'];
        $fileExt = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
        $allowedFoto = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($fileExt, $allowedFoto)) {
            $newFotoName = 'foto_' . time() . '.' . $fileExt;
            if (move_uploaded_file($fileTmpPath, $uploadDir . $newFotoName)) {
                $db_foto_perfil = $newFotoName;
            }
        }
    }

    // --- GESTIÓN DE MÚLTIPLES ARCHIVOS ---
    $db_documentos = [];

    if (isset($_FILES['archivos']) && is_array($_FILES['archivos']['name'])) {
        $numFiles = count($_FILES['archivos']['name']);

        for ($i = 0; $i < $numFiles; $i++) {
            if ($_FILES['archivos']['name'][$i] === '') {
                continue;
            }

            $fileName = $_FILES['archivos']['name'][$i];
            $fileTmpPath = $_FILES['archivos']['tmp_name'][$i];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($_FILES['archivos']['size'][$i] > $maxSize) {
                $error_upload .= "El archivo '{$fileName}' supera 10MB. ";
            } elseif (!in_array($fileExt, $allowedExtensions)) {
                $error_upload .= "El archivo '{$fileName}' no es permitido. ";
            } else {
                $newFileName = 'doc_' . time() . '_' . $i . '.' . $fileExt;
                if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                    $db_documentos[] = [
                        'nombre' => trim($_POST['nombre_documento_personalizado'][$i] ?? $fileName),
                        'original' => $newFileName,
                        'tipo' => $fileExt,
                        'path' => $newFileName
                    ];
                }
            }
        }
    }

    if ($error_upload) {
        $mensaje = "<div class='alert alert-danger'>{$error_upload}</div>";
    } else {
        try {
            $pdo->beginTransaction();

            if ($accion === 'crear') {
                $sql = "INSERT INTO expedientes (numero_expediente, alumno_nombre, alumno_apellido, fecha_nacimiento, grado_actual, estatus, observaciones, 
                        direccion, correo, telefono, numero_control, carrera, tutor_asignado, tipo_programa, foto_perfil) 
                        VALUES (:num, :nom, :ape, :fec, :gra, :est, :obs, 
                        :dir, :corr, :tel, :nc, :car, :tut, :tp, :foto)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':num' => $num_exp,
                    ':nom' => $nombre,
                    ':ape' => $apellido,
                    ':fec' => $fecha_nac,
                    ':gra' => $grado,
                    ':est' => $estatus,
                    ':obs' => $observaciones,
                    ':dir' => $direccion,
                    ':corr' => $correo,
                    ':tel' => $telefono,
                    ':nc' => $num_control,
                    ':car' => $carrera,
                    ':tut' => $tutor,
                    ':tp' => $tipo_programa,
                    ':foto' => $db_foto_perfil
                ]);
                $lastId = $pdo->lastInsertId();

                foreach ($db_documentos as $doc) {
                    $sqlDoc = "INSERT INTO documentos (expediente_id, nombre_archivo, archivo_original, tipo_archivo) 
                               VALUES (:exp_id, :nom, :orig, :tipo)";
                    $stmtDoc = $pdo->prepare($sqlDoc);
                    $stmtDoc->execute([
                        ':exp_id' => $lastId,
                        ':nom' => $doc['nombre'],
                        ':orig' => $doc['original'],
                        ':tipo' => $doc['tipo']
                    ]);
                }
                $mensaje = "<div class='alert alert-success'>✅ Expediente creado exitosamente.</div>";
            } elseif ($accion === 'actualizar') {
                if (!$db_foto_perfil) {
                    $stmtOld = $pdo->prepare("SELECT foto_perfil FROM expedientes WHERE id=:id");
                    $stmtOld->execute([':id' => $id]);
                    $oldFoto = $stmtOld->fetch();
                    $db_foto_perfil = $oldFoto['foto_perfil'];
                } else {
                    $stmtOld = $pdo->prepare("SELECT foto_perfil FROM expedientes WHERE id=:id");
                    $stmtOld->execute([':id' => $id]);
                    $oldFoto = $stmtOld->fetch();
                    if ($oldFoto && file_exists($uploadDir . $oldFoto['foto_perfil'])) {
                        unlink($uploadDir . $oldFoto['foto_perfil']);
                    }
                }

                $sql = "UPDATE expedientes SET alumno_nombre=:nom, alumno_apellido=:ape, fecha_nacimiento=:fec, 
                        grado_actual=:gra, estatus=:est, observaciones=:obs,
                        direccion=:dir, correo=:corr, telefono=:tel, numero_control=:nc, carrera=:car, 
                        tutor_asignado=:tut, tipo_programa=:tp,
                        foto_perfil=:foto WHERE id=:id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id' => $id,
                    ':nom' => $nombre,
                    ':ape' => $apellido,
                    ':fec' => $fecha_nac,
                    ':gra' => $grado,
                    ':est' => $estatus,
                    ':obs' => $observaciones,
                    ':dir' => $direccion,
                    ':corr' => $correo,
                    ':tel' => $telefono,
                    ':nc' => $num_control,
                    ':car' => $carrera,
                    ':tut' => $tutor,
                    ':tp' => $tipo_programa,
                    ':foto' => $db_foto_perfil
                ]);

                foreach ($db_documentos as $doc) {
                    $sqlDoc = "INSERT INTO documentos (expediente_id, nombre_archivo, archivo_original, tipo_archivo) 
                               VALUES (:exp_id, :nom, :orig, :tipo)";
                    $stmtDoc = $pdo->prepare($sqlDoc);
                    $stmtDoc->execute([
                        ':exp_id' => $id,
                        ':nom' => $doc['nombre'],
                        ':orig' => $doc['original'],
                        ':tipo' => $doc['tipo']
                    ]);
                }

                $mensaje = "<div class='alert alert-success'>✅ Expediente actualizado exitosamente.</div>";
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

// ELIMINAR EXPEDIENTE
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT foto_perfil FROM expedientes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $file = $stmt->fetch();

        if ($file && file_exists($uploadDir . $file['foto_perfil'])) {
            unlink($uploadDir . $file['foto_perfil']);
        }

        $stmtDocs = $pdo->prepare("SELECT archivo_original FROM documentos WHERE expediente_id = :id");
        $stmtDocs->execute([':id' => $id]);
        $docs = $stmtDocs->fetchAll();

        foreach ($docs as $doc) {
            if (file_exists($uploadDir . $doc['archivo_original'])) {
                unlink($uploadDir . $doc['archivo_original']);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM expedientes WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $pdo->commit();
        $mensaje = "<div class='alert alert-success'>✅ Expediente eliminado.</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

// ELIMINAR DOCUMENTO
if (isset($_GET['eliminar_doc'])) {
    $doc_id = $_GET['eliminar_doc'];
    try {
        $stmt = $pdo->prepare("SELECT archivo_original FROM documentos WHERE id = :id");
        $stmt->execute([':id' => $doc_id]);
        $doc = $stmt->fetch();

        if ($doc && file_exists($uploadDir . $doc['archivo_original'])) {
            unlink($uploadDir . $doc['archivo_original']);
        }

        $stmt = $pdo->prepare("DELETE FROM documentos WHERE id = :id");
        $stmt->execute([':id' => $doc_id]);

        $mensaje = "<div class='alert alert-success'>✅ Documento eliminado.</div>";
    } catch (Exception $e) {
        $mensaje = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

// OBTENER DATOS
$expedientes = $pdo->query("SELECT * FROM expedientes ORDER BY creado_en DESC")->fetchAll();
$editar = null;
$verPerfil = null;
$documentos = [];

// OBTENER TODOS LOS DOCUMENTOS
$todosDocumentos = $pdo->query("
    SELECT d.*, e.alumno_nombre, e.alumno_apellido, e.numero_expediente 
    FROM documentos d 
    JOIN expedientes e ON d.expediente_id = e.id 
    ORDER BY d.fecha_subida DESC
")->fetchAll();

if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM expedientes WHERE id = :id");
    $stmt->execute([':id' => $_GET['editar']]);
    $editar = $stmt->fetch();
}

if (isset($_GET['ver'])) {
    $id_ver = $_GET['ver'];
    $stmt = $pdo->prepare("SELECT * FROM expedientes WHERE id = :id");
    $stmt->execute([':id' => $id_ver]);
    $verPerfil = $stmt->fetch();

    $stmtDocs = $pdo->prepare("SELECT * FROM documentos WHERE expediente_id = :id ORDER BY fecha_subida DESC");
    $stmtDocs->execute([':id' => $id_ver]);
    $documentos = $stmtDocs->fetchAll();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Sistema de Gestión de Expedientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }

        .fb-cover {
            height: 250px;
            background: linear-gradient(135deg, #1877F2, #166fe5);
            border-radius: 12px;
            margin-bottom: -60px;
            position: relative;
        }

        .fb-profile-img {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 4px solid white;
            position: absolute;
            bottom: -60px;
            left: 30px;
            object-fit: cover;
            background: #ddd;
        }

        .fb-name {
            font-size: 26px;
            font-weight: bold;
            color: #1c2129;
        }

        .fb-info {
            color: #65676b;
            font-size: 14px;
        }

        .modal-header {
            background: #0d6efd;
            color: white;
        }

        .btn-close-white {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .info-group {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: 600;
            color: #444;
            font-size: 0.9rem;
        }

        .info-value {
            color: #1c2129;
            font-size: 1rem;
        }

        .doc-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #0d6efd;
            margin-bottom: 10px;
        }

        .doc-card:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-white mb-4">
        <div class="container">
            <a class="navbar-brand text-primary fw-bold" href="index.php"><i class="bi bi-file-earmark-text"></i> Sistema Escolar</a>
            <div class="ms-auto">
                <a href="?logout=true" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Salir</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if ($mensaje): echo $mensaje;
        endif; ?>

        <ul class="nav nav-tabs mb-4" id="mainTabs">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#lista">Lista de Expedientes</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#documentos">📄 Documentos</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#perfil">Perfil Individual</a></li>
        </ul>

        <div class="tab-content">

            <!-- PESTAÑA 1: LISTA DE EXPEDIENTES -->
            <div class="tab-pane fade show active" id="lista">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-people"></i> Expedientes Registrados (<?= count($expedientes) ?>)</span>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalForm">
                            <i class="bi bi-plus-lg"></i> Nuevo Expediente
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th># Exp.</th>
                                        <th>Alumno</th>
                                        <th>Carrera</th>
                                        <th>Grado</th>
                                        <th>Programa</th>
                                        <th>Estatus</th>
                                        <th>Tutor</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expedientes as $exp): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($exp['numero_expediente']) ?></strong></td>
                                            <td><?= htmlspecialchars($exp['alumno_nombre'] . ' ' . $exp['alumno_apellido']) ?></td>
                                            <td><?= htmlspecialchars($exp['carrera']) ?></td>
                                            <td><?= htmlspecialchars($exp['grado_actual']) ?></td>
                                            <td>
                                                <?php if ($exp['tipo_programa'] != 'Ninguno'): ?>
                                                    <span class="badge bg-info text-dark"><?= htmlspecialchars($exp['tipo_programa']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= ($exp['estatus'] == 'Activo') ? 'success' : (($exp['estatus'] == 'Baja') ? 'danger' : 'warning') ?>">
                                                    <?= htmlspecialchars($exp['estatus']) ?>
                                                </span>
                                            </td>
                                          <td><?= htmlspecialchars($exp['tutor_asignado'] ?? '') ?></td>
<td style="white-space: nowrap; width: 1%;">
    <div class="d-flex flex-nowrap justify-content-center gap-1">
        <a href="index.php?editar=<?= $exp['id'] ?>" class="btn btn-sm btn-warning" title="Editar"><i class="bi bi-pencil-square"></i></a>
        <a href="index.php?ver=<?= $exp['id'] ?>" class="btn btn-sm btn-primary" title="Ver"><i class="bi bi-eye-fill"></i></a>
        <a href="index.php?eliminar=<?= $exp['id'] ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirm('¿Eliminar?')"><i class="bi bi-trash"></i></a>
    </div>
</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PESTAÑA 2: TODOS LOS DOCUMENTOS CON FILTRADO -->
            <div class="tab-pane fade" id="documentos">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-folder2-open"></i> Todos los Documentos (<span id="totalDocs"><?= count($todosDocumentos) ?></span>)</span>
                            <div class="gap-2 d-flex">
                                <button class="btn btn-primary btn-sm" id="btnFiltrar">
                                    <i class="bi bi-funnel"></i> Filtrar por Nombre
                                </button>
                                <button class="btn btn-warning btn-sm" id="btnLimpiar" style="display: none;">
                                    <i class="bi bi-arrow-clockwise"></i> Limpiar Filtro
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- MOSTRAR FILTRO ACTIVO -->
                        <div id="filtroActivo" class="alert alert-info alert-dismissible fade show" style="display: none;">
                            <strong>Filtro activo:</strong> <span id="textoFiltro"></span>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>

                        <?php if (!empty($todosDocumentos)): ?>
                            <div class="row" id="documentosContainer">
                                <?php foreach ($todosDocumentos as $doc): ?>
                                    <div class="col-md-6 col-lg-4 mb-3 documento-item" data-alumno="<?= strtolower($doc['alumno_nombre'] . ' ' . $doc['alumno_apellido']) ?>" data-expediente="<?= strtolower($doc['numero_expediente']) ?>" data-documento="<?= strtolower($doc['nombre_archivo']) ?>">
                                        <div class="doc-card">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <i class="bi bi-file-earmark-<?= $doc['tipo_archivo'] ?> text-primary"></i>
                                                        <?= htmlspecialchars($doc['nombre_archivo']) ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <strong>Alumno:</strong> <?= htmlspecialchars($doc['alumno_nombre'] . ' ' . $doc['alumno_apellido']) ?><br>
                                                        <strong>Exp:</strong> <?= htmlspecialchars($doc['numero_expediente']) ?><br>
                                                        <strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($doc['fecha_subida'])) ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="mt-2 d-flex gap-2">
                                                <a href="uploads/<?= $doc['archivo_original'] ?>" target="_blank" class="btn btn-sm btn-info flex-grow-1">
                                                    <i class="bi bi-eye"></i> Ver
                                                </a>
                                                <a href="uploads/<?= $doc['archivo_original'] ?>" download="<?= htmlspecialchars($doc['nombre_archivo']) ?>" class="btn btn-sm btn-success">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                <a href="?eliminar_doc=<?= $doc['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div id="noResults" class="alert alert-warning text-center" style="display: none;">
                                <i class="bi bi-search"></i> No se encontraron documentos que coincidan con tu búsqueda.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="bi bi-info-circle"></i> No hay documentos registrados.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- PESTAÑA 3: PERFIL INDIVIDUAL -->
            <div class="tab-pane fade" id="perfil">
                <?php if ($verPerfil): ?>
                    <div class="row justify-content-center">
                        <div class="col-12 col-lg-10">
                            <div class="fb-cover"></div>
                            <div class="text-center mb-3">
                                <img src="<?= $uploadDir . ($verPerfil['foto_perfil'] ?? 'default.jpg') ?>" class="fb-profile-img"
                                    alt="Foto" onerror="this.src='https://via.placeholder.com/140?text=Foto'">
                            </div>

                            <div class="card pt-5 pb-4">
                                <div class="card-body">
                                    <h2 class="fb-name"><?= htmlspecialchars($verPerfil['alumno_nombre'] . ' ' . $verPerfil['alumno_apellido']) ?></h2>
                                    <p class="fb-info mb-4"><i class="bi bi-graduation-cap"></i> <?= htmlspecialchars($verPerfil['carrera']) ?> - <?= htmlspecialchars($verPerfil['grado_actual']) ?></p>

                                    <div class="row">
                                        <div class="col-md-8">
                                            <h5 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-person-badge"></i> Información Personal</h5>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="info-group">
                                                        <div class="info-label">Número de Control</div>
                                                        <div class="info-value"><?= htmlspecialchars($verPerfil['numero_control'] ?? '-') ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="info-group">
                                                        <div class="info-label">Teléfono</div>
                                                        <div class="info-value"><?= htmlspecialchars($verPerfil['telefono'] ?? '-') ?></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="info-group">
                                                <div class="info-label">Correo</div>
                                                <div class="info-value"><?= htmlspecialchars($verPerfil['correo'] ?? '-') ?></div>
                                            </div>

                                            <div class="info-group">
                                                <div class="info-label">Dirección</div>
                                                <div class="info-value"><?= htmlspecialchars($verPerfil['direccion'] ?? '-') ?></div>
                                            </div>

                                            <div class="info-group">
                                                <div class="info-label">Tutor/Asesor</div>
                                                <div class="info-value"><?= htmlspecialchars($verPerfil['tutor_asignado'] ?? '-') ?></div>
                                            </div>

                                            <div class="info-group">
                                                <div class="info-label">Programa</div>
                                                <div class="info-value">
                                                    <?php if ($verPerfil['tipo_programa'] != 'Ninguno'): ?>
                                                        <span class="badge bg-info"><?= htmlspecialchars($verPerfil['tipo_programa']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sin programa</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <h5 class="border-bottom pb-2 mb-3 mt-4 text-primary"><i class="bi bi-folder2-open"></i> Documentos (<?= count($documentos) ?>)</h5>

                                            <?php if (!empty($documentos)): ?>
                                                <div class="list-group mb-4">
                                                    <?php foreach ($documentos as $doc): ?>
                                                        <div class="list-group-item d-flex align-items-center justify-content-between">
                                                            <div class="flex-grow-1">
                                                                <i class="bi bi-file-earmark-<?= $doc['tipo_archivo'] ?> text-primary fs-5 me-2"></i>
                                                                <strong><?= htmlspecialchars($doc['nombre_archivo']) ?></strong>
                                                            </div>
                                                            <div class="gap-2 d-flex">
                                                                <a href="uploads/<?= $doc['archivo_original'] ?>" target="_blank" class="btn btn-sm btn-info">
                                                                    <i class="bi bi-eye"></i> Ver
                                                                </a>
                                                                <a href="uploads/<?= $doc['archivo_original'] ?>" download="<?= htmlspecialchars($doc['nombre_archivo']) ?>" class="btn btn-sm btn-success">
                                                                    <i class="bi bi-download"></i>
                                                                </a>
                                                                <a href="?eliminar_doc=<?= $doc['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar?')">
                                                                    <i class="bi bi-trash"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-info">No hay documentos.</div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-md-4 text-md-end mt-4 mt-md-0 border-start">
                                            <a href="?editar=<?= $verPerfil['id'] ?>" class="btn btn-primary w-100 mb-2">
                                                <i class="bi bi-pencil-square"></i> Editar
                                            </a>
                                            <a href="?eliminar=<?= $verPerfil['id'] ?>" class="btn btn-danger w-100" onclick="return confirm('¿Eliminar?')">
                                                <i class="bi bi-trash"></i> Eliminar
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center py-5">
                        <i class="bi bi-info-circle fs-1"></i>
                        <h4 class="mt-3">Selecciona un expediente</h4>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL -->
    <div class="modal fade" id="modalForm" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= $editar ? 'Editar' : 'Nuevo' ?> Expediente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php" enctype="multipart/form-data">
                        <input type="hidden" name="accion" value="<?= $editar ? 'actualizar' : 'crear' ?>">
                        <?php if ($editar): ?>
                            <input type="hidden" name="id" value="<?= $editar['id'] ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Número Exp.</label>
                                <input type="text" class="form-control" name="numero_expediente" value="<?= $editar['numero_expediente'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nombre</label>
                                <input type="text" class="form-control" name="alumno_nombre" value="<?= $editar['alumno_nombre'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Apellido</label>
                                <input type="text" class="form-control" name="alumno_apellido" value="<?= $editar['alumno_apellido'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Grado</label>
                                <input type="text" class="form-control" name="grado_actual" value="<?= $editar['grado_actual'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha Nac.</label>
                                <input type="date" class="form-control" name="fecha_nacimiento" value="<?= $editar['fecha_nacimiento'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estatus</label>
                                <select class="form-select" name="estatus">
                                    <option value="Activo" <?= ($editar['estatus'] ?? '') == 'Activo' ? 'selected' : '' ?>>Activo</option>
                                    <option value="Baja" <?= ($editar['estatus'] ?? '') == 'Baja' ? 'selected' : '' ?>>Baja</option>
                                    <option value="Egresado" <?= ($editar['estatus'] ?? '') == 'Egresado' ? 'selected' : '' ?>>Egresado</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Número Control</label>
                                <input type="text" class="form-control" name="numero_control" value="<?= $editar['numero_control'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Carrera</label>
                                <input type="text" class="form-control" name="carrera" value="<?= $editar['carrera'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" name="telefono" value="<?= $editar['telefono'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Correo</label>
                                <input type="email" class="form-control" name="correo" value="<?= $editar['correo'] ?? '' ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Dirección</label>
                                <input type="text" class="form-control" name="direccion" value="<?= $editar['direccion'] ?? '' ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Tutor/Asesor</label>
                                <input type="text" class="form-control" name="tutor_asignado" value="<?= $editar['tutor_asignado'] ?? '' ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Programa</label>
                                <select class="form-select" name="tipo_programa">
                                    <option value="Ninguno" <?= ($editar['tipo_programa'] ?? '') == 'Ninguno' ? 'selected' : '' ?>>Ninguno</option>
                                    <option value="Servicio Social" <?= ($editar['tipo_programa'] ?? '') == 'Servicio Social' ? 'selected' : '' ?>>Servicio Social</option>
                                    <option value="Residencias Profesionales" <?= ($editar['tipo_programa'] ?? '') == 'Residencias Profesionales' ? 'selected' : '' ?>>Residencias Profesionales</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Observaciones</label>
                                <textarea class="form-control" name="observaciones" rows="2"><?= $editar['observaciones'] ?? '' ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Foto</label>
                                <input type="file" class="form-control" name="foto_perfil" accept="image/*">
                                <?php if ($editar && $editar['foto_perfil']): ?>
                                    <img src="uploads/<?= $editar['foto_perfil'] ?>" class="img-thumbnail mt-2" style="width: 80px; height: 80px;">
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Documentos (Múltiples)</label>
                                <input type="file" class="form-control" name="archivos[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx">
                                <small class="text-muted">Máx 10MB cada uno</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Nombre Documento</label>
                                <input type="text" class="form-control" name="nombre_documento_personalizado[]" placeholder="Nombre personalizado">
                            </div>
                        </div>

                        <div class="modal-footer mt-4">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if ($editar): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('modalForm');
            const modal = new bootstrap.Modal(modalEl);
            modal.show();

            // Al cerrar el modal, regresa a la lista limpia
            modalEl.addEventListener('hidden.bs.modal', function () {
                window.location.href = 'index.php';
            });
        });
    </script>
    <?php endif; ?>

    <?php if ($verPerfil): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tab = document.querySelector('a[href="#perfil"]');
            bootstrap.Tab.getOrCreateInstance(tab).show();
        });
    </script>
    <?php endif; ?>

    <script>
        // ========== FILTRADO DE DOCUMENTOS POR PROMPT ==========
        document.getElementById('btnFiltrar').addEventListener('click', function () {
            const searchTerm = prompt('Ingresa el nombre para filtrar documentos:\n(Alumno, Expediente o Documento)');

            if (searchTerm === null) {
                return;
            }

            const searchLower = searchTerm.toLowerCase().trim();
            const documentoItems = document.querySelectorAll('.documento-item');
            const noResults = document.getElementById('noResults');
            const filtroActivo = document.getElementById('filtroActivo');
            const textoFiltro = document.getElementById('textoFiltro');
            const btnLimpiar = document.getElementById('btnLimpiar');
            let visibleCount = 0;

            if (searchTerm === '') {
                alert('Por favor ingresa un término de búsqueda');
                return;
            }

            documentoItems.forEach(item => {
                const alumno = item.getAttribute('data-alumno');
                const expediente = item.getAttribute('data-expediente');
                const documento = item.getAttribute('data-documento');

                if (alumno.includes(searchLower) || expediente.includes(searchLower) || documento.includes(searchLower)) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            if (visibleCount > 0) {
                filtroActivo.style.display = 'block';
                textoFiltro.textContent = `"${searchTerm}" (${visibleCount} resultado${visibleCount !== 1 ? 's' : ''})`;
                noResults.style.display = 'none';
                btnLimpiar.style.display = 'inline-block';
                alert(`Se encontraron ${visibleCount} documento(s) que coinciden con "${searchTerm}"`);
            } else {
                noResults.style.display = 'block';
                filtroActivo.style.display = 'none';
                btnLimpiar.style.display = 'inline-block';
            }
        });

        // ========== LIMPIAR FILTROS ==========
        document.getElementById('btnLimpiar').addEventListener('click', function () {
            const documentoItems = document.querySelectorAll('.documento-item');
            const noResults = document.getElementById('noResults');
            const filtroActivo = document.getElementById('filtroActivo');
            const btnLimpiar = document.getElementById('btnLimpiar');

            documentoItems.forEach(item => {
                item.style.display = 'block';
            });

            noResults.style.display = 'none';
            filtroActivo.style.display = 'none';
            btnLimpiar.style.display = 'none';

            alert('Filtro limpiado. Se muestran todos los documentos.');
        });

        // ========== SCRIPT PARA MÚLTIPLES DOCUMENTOS ==========
        document.addEventListener('DOMContentLoaded', function () {
            const fileInput = document.querySelector('input[name="archivos[]"]');
            if (fileInput) {
                fileInput.addEventListener('change', function () {
                    const numFiles = this.files.length;
                    const container = document.querySelector('.modal-body');

                    const oldFields = container.querySelectorAll('.doc-name-field');
                    oldFields.forEach(field => field.remove());

                    for (let i = 0; i < numFiles; i++) {
                        const fileName = this.files[i].name;
                        const div = document.createElement('div');
                        div.className = 'doc-name-field mt-2';
                        div.innerHTML = `
                            <label class="form-label small">Nombre para: <strong>${fileName}</strong></label>
                            <input type="text" class="form-control form-control-sm" name="nombre_documento_personalizado[]"
                                   placeholder="Dejar vacío para usar el nombre original">
                        `;
                        container.appendChild(div);
                    }
                });
            }
        });
    </script>
<?php if ($editar): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('modalForm');
        new bootstrap.Modal(modalEl).show();

        modalEl.addEventListener('hidden.bs.modal', function () {
            window.location.href = 'index.php';
        });
    });
</script>
<?php endif; ?>

<?php if ($verPerfil): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        bootstrap.Tab.getOrCreateInstance(document.querySelector('a[href="#perfil"]')).show();
    });
</script>
<?php endif; ?>
</body>

</html>