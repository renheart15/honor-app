<?php
// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error_types = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];
    
    $error_type = $error_types[$errno] ?? 'Unknown Error';
    $error_message = "[$error_type] $errstr in $errfile on line $errline";
    
    // Log error
    error_log($error_message);
    
    // Don't show errors in production
    $http_host = $_SERVER['HTTP_HOST'] ?? '';
    if ($http_host !== 'localhost' && strpos($http_host, '127.0.0.1') === false) {
        return true; // Don't execute PHP internal error handler
    }
    
    return false; // Execute PHP internal error handler
}

// Custom exception handler
function customExceptionHandler($exception) {
    $error_message = "Uncaught Exception: " . $exception->getMessage() . 
                    " in " . $exception->getFile() . 
                    " on line " . $exception->getLine();
    
    error_log($error_message);
    
    // Show user-friendly error page
    $http_host = $_SERVER['HTTP_HOST'] ?? '';
    if ($http_host !== 'localhost' && strpos($http_host, '127.0.0.1') === false) {
        include 'error-page.php';
        exit();
    }
    
    echo "<h1>System Error</h1>";
    echo "<p>An error occurred. Please try again later.</p>";
    echo "<pre>" . $exception->getTraceAsString() . "</pre>";
}

// Set error and exception handlers
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        error_log("Fatal Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        
        $http_host = $_SERVER['HTTP_HOST'] ?? '';
        if ($http_host !== 'localhost' && strpos($http_host, '127.0.0.1') === false) {
            include 'error-page.php';
        }
    }
});
?>
