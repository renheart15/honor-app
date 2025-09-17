<?php
// Prevent direct access to uploads directory
http_response_code(403);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Access Denied</title>
</head>
<body>
    <h1>403 - Access Denied</h1>
    <p>You don't have permission to access this directory.</p>
</body>
</html>
