<?php
require 'db_config.php';
require 'auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pits - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="scripts.js"></script>
</head>
<body>
<?php
require 'header.php';
?>
<div class="container mt-3">
    <h1>Pits</h1>
    <h2>Models</h2>
    <a href="models.php" class="btn btn-primary mb-3">Manage Models</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>