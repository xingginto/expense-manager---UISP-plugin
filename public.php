<?php

// Handle API calls first before any HTML output
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

// Debug: Check for user authentication data (must be before auth check)
if ($action === 'debug-auth') {
    header('Content-Type: application/json');
    $allHeaders = getallheaders();
    
    // Try to decode JWT for debug info
    $jwtPayload = null;
    if (isset($_COOKIE['jwt'])) {
        $jwtData = json_decode($_COOKIE['jwt'], true);
        if ($jwtData && isset($jwtData['token'])) {
            $parts = explode('.', $jwtData['token']);
            if (count($parts) >= 2) {
                $payloadBase64 = str_replace(['-', '_'], ['+', '/'], $parts[1]);
                switch (strlen($payloadBase64) % 4) {
                    case 2: $payloadBase64 .= '=='; break;
                    case 3: $payloadBase64 .= '='; break;
                }
                $jwtPayload = json_decode(base64_decode($payloadBase64), true);
            }
        }
    }
    
    // Test current-user API call
    $currentUserApiResult = null;
    $ucrmJsonPath = __DIR__ . '/data/ucrm.json';
    if (file_exists($ucrmJsonPath)) {
        $ucrmData = json_decode(file_get_contents($ucrmJsonPath), true);
        $ucrmUrl = $ucrmData['ucrmLocalUrl'] ?? $ucrmData['ucrmPublicUrl'] ?? null;
        if ($ucrmUrl) {
            $ucrmUrl = rtrim($ucrmUrl, '/') . '/';
            $cookies = [];
            if (isset($_COOKIE['PHPSESSID'])) $cookies[] = 'PHPSESSID=' . preg_replace('~[^a-zA-Z0-9-]~', '', $_COOKIE['PHPSESSID']);
            if (isset($_COOKIE['nms-crm-php-session-id'])) $cookies[] = 'nms-crm-php-session-id=' . preg_replace('~[^a-zA-Z0-9-]~', '', $_COOKIE['nms-crm-php-session-id']);
            if (isset($_COOKIE['nms-session'])) $cookies[] = 'nms-session=' . preg_replace('~[^a-zA-Z0-9-]~', '', $_COOKIE['nms-session']);
            
            $context = stream_context_create([
                'http' => ['method' => 'GET', 'header' => ['Content-Type: application/json', 'Cookie: ' . implode('; ', $cookies)], 'ignore_errors' => true, 'timeout' => 5],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]);
            $response = @file_get_contents($ucrmUrl . 'current-user', false, $context);
            $currentUserApiResult = ['url' => $ucrmUrl . 'current-user', 'cookies_sent' => $cookies, 'response' => $response ? json_decode($response, true) : null, 'raw' => $response];
        }
    }

    echo json_encode([
        'all_headers' => $allHeaders,
        'cookies' => $_COOKIE,
        'jwt_payload' => $jwtPayload,
        'jwt_payload_keys' => $jwtPayload ? array_keys($jwtPayload) : null,
        'ucrm_json_exists' => file_exists(__DIR__ . '/data/ucrm.json'),
        'ucrm_json_data' => file_exists(__DIR__ . '/data/ucrm.json') ? json_decode(file_get_contents(__DIR__ . '/data/ucrm.json'), true) : null,
        'current_user_api' => $currentUserApiResult,
        'authenticated' => isAuthenticated(),
        'current_user' => getCurrentUser()
    ], JSON_PRETTY_PRINT);
    exit;
}

// Authentication check - comprehensive check of all methods
function isAuthenticated() {
    // 1. Check multiple potential paths for ucrm.json (Standard Plugin Method)
    $paths = [
        __DIR__ . '/data/ucrm.json',
        dirname(__DIR__) . '/data/ucrm.json',
        'data/ucrm.json'
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            $content = file_get_contents($path);
            if ($content) {
                $ucrmData = json_decode($content, true);
                if ($ucrmData && isset($ucrmData['userId']) && $ucrmData['userId'] > 0) {
                    return true;
                }
            }
        }
    }

    // 2. Check Headers (API/Proxy Method)
    $headers = getallheaders();
    $headersLower = array_change_key_case($headers, CASE_LOWER);
    
    // Check standard headers
    if (isset($headersLower['x-auth-token']) || isset($headersLower['a-auth-token'])) {
        return true;
    }
    
    // Check Authorization header (Bearer token)
    if (isset($headersLower['authorization'])) {
        return true;
    }
    
    // Check $_SERVER variables (fallback if getallheaders misses them)
    if (isset($_SERVER['HTTP_X_AUTH_TOKEN']) || isset($_SERVER['HTTP_A_AUTH_TOKEN'])) {
        return true;
    }
    if (isset($_SERVER['HTTP_AUTHORIZATION']) || isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return true;
    }

    // 3. Check Cookies (Iframe/Browser Method)
    // Primary check: nms-session (Standard UISP session)
    if (isset($_COOKIE['nms-session'])) {
        return true;
    }
    
    // Secondary check: jwt cookie (contains token and userId)
    if (isset($_COOKIE['jwt'])) {
        $jwtData = json_decode($_COOKIE['jwt'], true);
        if ($jwtData && isset($jwtData['userId'])) {
            return true;
        }
    }

    // Fallback: Check other known cookies
    if (isset($_COOKIE['unms_token']) || isset($_COOKIE['uisp_token']) || isset($_COOKIE['PHPSESSID'])) {
        return true;
    }

    // Log failure for debugging
    error_log("Auth failed. Headers: " . implode(',', array_keys($headers)) . " Cookies: " . implode(',', array_keys($_COOKIE)));

    return false;
}

function getCurrentUser() {
    // Method 1: Call UCRM API current-user endpoint (official SDK method)
    $ucrmJsonPath = __DIR__ . '/data/ucrm.json';
    if (file_exists($ucrmJsonPath)) {
        $ucrmData = json_decode(file_get_contents($ucrmJsonPath), true);
        $ucrmUrl = $ucrmData['ucrmLocalUrl'] ?? $ucrmData['ucrmPublicUrl'] ?? null;
        
        if ($ucrmUrl) {
            $ucrmUrl = rtrim($ucrmUrl, '/') . '/';
            
            // Build cookie string (same as SDK)
            $cookies = [];
            if (isset($_COOKIE['PHPSESSID'])) {
                $cookies[] = 'PHPSESSID=' . preg_replace('~[^a-zA-Z0-9-]~', '', $_COOKIE['PHPSESSID']);
            }
            if (isset($_COOKIE['nms-crm-php-session-id'])) {
                $cookies[] = 'nms-crm-php-session-id=' . preg_replace('~[^a-zA-Z0-9-]~', '', $_COOKIE['nms-crm-php-session-id']);
            }
            if (isset($_COOKIE['nms-session'])) {
                $cookies[] = 'nms-session=' . preg_replace('~[^a-zA-Z0-9-]~', '', $_COOKIE['nms-session']);
            }
            
            if (!empty($cookies)) {
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'header' => [
                            'Content-Type: application/json',
                            'Cookie: ' . implode('; ', $cookies)
                        ],
                        'ignore_errors' => true,
                        'timeout' => 5
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]);
                
                $response = @file_get_contents($ucrmUrl . 'current-user', false, $context);
                if ($response) {
                    $userData = json_decode($response, true);
                    if ($userData && isset($userData['username'])) {
                        return $userData['username'];
                    }
                }
            }
        }
    }
    
    // Method 2: Try to get user from jwt cookie
    if (isset($_COOKIE['jwt'])) {
        $jwtData = json_decode($_COOKIE['jwt'], true);
        
        // Try to decode token payload to get username
        if ($jwtData && isset($jwtData['token'])) {
            $parts = explode('.', $jwtData['token']);
            if (count($parts) >= 2) {
                $payloadBase64 = str_replace(['-', '_'], ['+', '/'], $parts[1]);
                switch (strlen($payloadBase64) % 4) {
                    case 2: $payloadBase64 .= '=='; break;
                    case 3: $payloadBase64 .= '='; break;
                }
                $payload = json_decode(base64_decode($payloadBase64), true);
                
                // Check for common username fields (nameForView is used by UISP)
                $userFields = ['nameForView', 'username', 'login', 'fullName', 'name', 'user', 'email', 'sub', 'preferred_username'];
                foreach ($userFields as $field) {
                    if ($payload && isset($payload[$field]) && !empty($payload[$field])) {
                        return $payload[$field];
                    }
                }
                
                // Try firstName + lastName combination
                if ($payload && isset($payload['firstName']) && !empty($payload['firstName'])) {
                    $name = $payload['firstName'];
                    if (isset($payload['lastName']) && !empty($payload['lastName'])) {
                        $name .= ' ' . $payload['lastName'];
                    }
                    return $name;
                }
            }
        }

        if ($jwtData && isset($jwtData['userId'])) {
            return 'User #' . $jwtData['userId'];
        }
    }
    
    // Fallback: Check Headers
    $headers = getallheaders();
    if (isset($headers['PHP_AUTH_USER'])) {
        return $headers['PHP_AUTH_USER'];
    }
    
    return 'Unknown User';
}

// For API endpoints, require authentication
if ($action) {
    if (!isAuthenticated()) {
        header('HTTP-Reason: Authentication required');
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }
}

if ($action) {
    header('Content-Type: application/json');
    
    $dataFile = __DIR__ . '/data/expenses.json';
    
    function getExpenses() {
        global $dataFile;
        if (file_exists($dataFile)) {
            $json = file_get_contents($dataFile);
            return $json ? json_decode($json, true) ?: [] : [];
        }
        return [];
    }
    
    function saveExpenses($expenses) {
        global $dataFile;
        $dataDir = dirname($dataFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        file_put_contents($dataFile, json_encode($expenses, JSON_PRETTY_PRINT));
    }
    
    switch ($action) {
        case 'get':
            if ($id) {
                $expenses = getExpenses();
                $found = null;
                foreach ($expenses as $expense) {
                    if ($expense['id'] === $id) {
                        $found = $expense;
                        break;
                    }
                }
                if ($found) {
                    echo json_encode(['success' => true, 'expense' => $found]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Expense not found']);
                }
            } else {
                $expenses = getExpenses();
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'expenses' => $expenses,
                        'total' => count($expenses),
                        'page' => 1,
                        'pages' => 1
                    ]
                ]);
            }
            exit;
            
        case 'add':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if ($input) {
                    $expenses = getExpenses();
                    $expense = [
                        'id' => uniqid(),
                        'date' => $input['date'] ?? date('Y-m-d'),
                        'description' => $input['description'] ?? '',
                        'amount' => floatval($input['amount'] ?? 0),
                        'category' => $input['category'] ?? 'General',
                        'payment_method' => $input['payment_method'] ?? 'Cash',
                        'added_by' => $input['added_by'] ?? getCurrentUser(),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    $expenses[] = $expense;
                    saveExpenses($expenses);
                    echo json_encode(['success' => true, 'expense' => $expense]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid input']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            exit;
            
        case 'update':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if ($input && $id) {
                    $expenses = getExpenses();
                    $updated = false;
                    $currentUserName = getCurrentUser();
                    foreach ($expenses as &$expense) {
                        if ($expense['id'] === $id) {
                            // Check if current user is the owner
                            if (isset($expense['added_by']) && $expense['added_by'] !== $currentUserName) {
                                echo json_encode(['success' => false, 'error' => 'You can only edit expenses that you created']);
                                exit;
                            }
                            $expense['date'] = $input['date'] ?? $expense['date'];
                            $expense['description'] = $input['description'] ?? $expense['description'];
                            $expense['amount'] = floatval($input['amount'] ?? $expense['amount']);
                            $expense['category'] = $input['category'] ?? $expense['category'];
                            $expense['payment_method'] = $input['payment_method'] ?? $expense['payment_method'];
                            // Don't allow changing the owner
                            $expense['updated_at'] = date('Y-m-d H:i:s');
                            $updated = $expense;
                            break;
                        }
                    }
                    if ($updated) {
                        saveExpenses($expenses);
                        echo json_encode(['success' => true, 'expense' => $updated]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Expense not found']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid input']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            exit;
            
        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if ($id) {
                    $expenses = getExpenses();
                    $found = false;
                    $currentUserName = getCurrentUser();
                    foreach ($expenses as $key => $expense) {
                        if ($expense['id'] === $id) {
                            // Check if current user is the owner
                            if (isset($expense['added_by']) && $expense['added_by'] !== $currentUserName) {
                                echo json_encode(['success' => false, 'error' => 'You can only delete expenses that you created']);
                                exit;
                            }
                            unset($expenses[$key]);
                            $found = true;
                            break;
                        }
                    }
                    if ($found) {
                        saveExpenses(array_values($expenses));
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Expense not found']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'No expense ID provided']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            exit;
            
        case 'export':
            $expenses = getExpenses();
            if (!empty($expenses)) {
                // Direct CSV download instead of file URL
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="expenses_export_' . date('Y-m-d_H-i-s') . '.csv"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['ID', 'Date', 'Description', 'Amount', 'Category', 'Payment Method', 'Added By', 'Created At', 'Updated At']);
                foreach ($expenses as $expense) {
                    fputcsv($output, [
                        $expense['id'] ?? '',
                        $expense['date'] ?? '',
                        $expense['description'] ?? '',
                        $expense['amount'] ?? '',
                        $expense['category'] ?? '',
                        $expense['payment_method'] ?? '',
                        $expense['added_by'] ?? '',
                        $expense['created_at'] ?? '',
                        $expense['updated_at'] ?? ''
                    ]);
                }
                fclose($output);
            } else {
                echo json_encode(['success' => false, 'error' => 'No expenses to export']);
            }
            exit;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            exit;
    }
}

// If not an API call, check authentication for HTML page too
if (!$action) {
    if (!isAuthenticated()) {
        // Return a simple authentication error page with optional debug info
        http_response_code(401);
        
        $debugInfo = '';
        if (isset($_GET['debug'])) {
            $headers = getallheaders();
            $debugInfo = '<div style="margin-top: 20px; font-size: 12px; color: #333; text-align: left; background: #f8f9fa; padding: 15px; border-radius: 4px; border: 1px solid #ddd; overflow-x: auto;">';
            $debugInfo .= '<h3 style="margin-top:0;">Debug Diagnostics</h3>';
            
            $debugInfo .= '<strong>1. Headers Detected:</strong><pre>' . print_r(array_keys($headers), true) . '</pre>';
            
            $debugInfo .= '<strong>2. Cookies Detected:</strong><pre>' . print_r($_COOKIE, true) . '</pre>';
            
            $paths = [
                __DIR__ . '/data/ucrm.json',
                dirname(__DIR__) . '/data/ucrm.json',
                'data/ucrm.json'
            ];
            
            $debugInfo .= '<strong>3. File Checks:</strong><ul>';
            foreach ($paths as $path) {
                $exists = file_exists($path) ? 'YES' : 'NO';
                $readable = is_readable($path) ? 'YES' : 'NO';
                $size = file_exists($path) ? filesize($path) : 'N/A';
                $debugInfo .= "<li>Path: $path - Exists: $exists, Readable: $readable, Size: $size</li>";
            }
            $debugInfo .= '</ul>';
            
            $debugInfo .= '<strong>4. Server Variables:</strong><pre>' . print_r(array_filter($_SERVER, function($k) {
                return strpos($k, 'USER') !== false || strpos($k, 'AUTH') !== false;
            }, ARRAY_FILTER_USE_KEY), true) . '</pre>';
            
            $debugInfo .= '</div>';
        }
        
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Authentication Required</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; max-width: 800px; margin: 0 auto; }
        .error { color: #dc3545; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        pre { background: #eee; padding: 10px; border-radius: 3px; white-space: pre-wrap; word-wrap: break-word; text-align: left; }
    </style>
</head>
<body>
    <h1 class="error">Authentication Required</h1>
    <p>Please log in to UISP to access this plugin.</p>
    <p>If you are already logged in, please try refreshing the page.</p>
    <p><a href="/">Return to UISP</a></p>
    ' . $debugInfo . '
</body>
</html>';
        exit;
    }
}

// If not an API call, continue with HTML output
header('Content-Type: text/html; charset=UTF-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
        }
        .page-header h1 {
            font-weight: 700;
            margin: 0;
            font-size: 2rem;
        }
        .page-header p {
            opacity: 0.9;
            margin: 5px 0 0 0;
        }
        .btn-add {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            padding: 12px 24px;
            font-weight: 600;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
            transition: all 0.3s ease;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(17, 153, 142, 0.4);
        }
        .btn-export {
            background: white;
            color: #667eea;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn-export:hover {
            background: rgba(255,255,255,0.9);
            color: #764ba2;
        }
        .summary-card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .summary-card .card-body {
            padding: 25px;
            text-align: center;
        }
        .summary-card .card-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        .summary-card .card-value {
            font-size: 1.8rem;
            font-weight: 700;
        }
        .summary-card small {
            opacity: 0.8;
        }
        .card-total { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: #fff; }
        .card-count { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; }
        .card-category { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
        .card-recent { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: #fff; }
        .main-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .main-card .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            padding: 20px 25px;
        }
        .main-card .card-header h5 {
            margin: 0;
            font-weight: 700;
            color: #333;
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px;
            border: none;
        }
        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }
        .table tbody tr:hover {
            background-color: #f8f9ff;
        }
        .expense-amount {
            font-weight: 700;
            color: #e74c3c;
            font-size: 1.1rem;
        }
        .category-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-general { background: #e3f2fd; color: #1976d2; }
        .badge-office { background: #f3e5f5; color: #7b1fa2; }
        .badge-travel { background: #e8f5e9; color: #388e3c; }
        .badge-equipment { background: #fff3e0; color: #f57c00; }
        .badge-software { background: #e0f7fa; color: #00838f; }
        .badge-utilities { background: #fce4ec; color: #c2185b; }
        .badge-marketing { background: #f1f8e9; color: #689f38; }
        .badge-other { background: #eceff1; color: #546e7a; }
        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .btn-edit {
            background: #e3f2fd;
            color: #1976d2;
            border: none;
        }
        .btn-edit:hover {
            background: #1976d2;
            color: white;
        }
        .btn-delete {
            background: #ffebee;
            color: #c62828;
            border: none;
        }
        .btn-delete:hover {
            background: #c62828;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        .modal-content {
            border: none;
            border-radius: 16px;
            overflow: hidden;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 20px 25px;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .modal-body {
            padding: 25px;
        }
        .modal-footer {
            border-top: 1px solid #eee;
            padding: 15px 25px;
        }
        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 12px 15px;
            transition: all 0.2s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 10px;
        }
        .btn-cancel {
            background: #f5f5f5;
            color: #666;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 10px;
        }
        .payment-method {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .added-by {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #666;
            font-size: 0.9rem;
        }
        @media (max-width: 768px) {
            .page-header { padding: 20px; }
            .summary-card .card-value { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1><i class="fas fa-receipt me-2"></i>Expense Manager</h1>
                    <p class="mb-0">Track and manage your business expenses</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-export" onclick="exportToCSV()">
                        <i class="fas fa-download me-2"></i>Export CSV
                    </button>
                    <button class="btn btn-add text-white" onclick="showAddModal()">
                        <i class="fas fa-plus me-2"></i>Add Expense
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4" id="summary-cards">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card summary-card card-total">
                    <div class="card-body">
                        <div class="card-title">Total Expenses</div>
                        <div class="card-value" id="total-amount">PHP 0.00</div>
                        <small>All time</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card summary-card card-count">
                    <div class="card-body">
                        <div class="card-title">Total Entries</div>
                        <div class="card-value" id="total-count">0</div>
                        <small>Expense records</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card summary-card card-category">
                    <div class="card-body">
                        <div class="card-title">Categories Used</div>
                        <div class="card-value" id="total-categories">0</div>
                        <small>Different categories</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card summary-card card-recent">
                    <div class="card-body">
                        <div class="card-title">This Month</div>
                        <div class="card-value" id="month-amount">PHP 0.00</div>
                        <small id="current-month">December 2025</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expense List -->
        <div class="card main-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-list me-2"></i>Expense Records</h5>
                <span class="text-muted" id="record-info"></span>
            </div>
            <div class="card-body p-0">
                <div id="expense-list">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="pagination-container" class="mt-4"></div>
    </div>

    <!-- Add/Edit Expense Modal -->
    <div class="modal fade" id="expenseModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="fas fa-plus-circle me-2"></i>Add Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="expenseForm">
                        <input type="hidden" id="expenseId">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="expenseDate" class="form-label">Date</label>
                                <input type="date" class="form-control" id="expenseDate" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="expenseAmount" class="form-label">Amount (PHP)</label>
                                <input type="number" class="form-control" id="expenseAmount" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="expenseDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="expenseDescription" rows="2" placeholder="Enter expense description..." required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="expenseCategory" class="form-label">Category</label>
                                <select class="form-select" id="expenseCategory" required>
                                    <option value="">Select category</option>
                                    <option value="General">General</option>
                                    <option value="Office">Office</option>
                                    <option value="Travel">Travel</option>
                                    <option value="Equipment">Equipment</option>
                                    <option value="Software">Software</option>
                                    <option value="Utilities">Utilities</option>
                                    <option value="Marketing">Marketing</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="expensePaymentMethod" class="form-label">Payment Method</label>
                                <select class="form-select" id="expensePaymentMethod" required>
                                    <option value="Cash">Cash</option>
                                    <option value="Gcash">Gcash</option>
                                    <option value="Xend-it">Xend-it</option>
                                    <option value="Paymaya">Paymaya</option>
                                    <option value="Bank">Bank</option>
                                    <option value="Others">Others</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Added By</label>
                            <input type="text" class="form-control" id="expenseAddedBy" readonly style="background-color: #f8f9fa;">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-save text-white" onclick="saveExpense()">
                        <i class="fas fa-save me-2"></i>Save Expense
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                    <p class="mb-1">Are you sure you want to delete this expense?</p>
                    <p class="fw-bold text-dark" id="deleteExpenseDescription"></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                        <i class="fas fa-trash me-2"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const currentUser = '<?php echo htmlspecialchars(getCurrentUser(), ENT_QUOTES, 'UTF-8'); ?>';
        let currentPage = 1;
        let deleteExpenseId = null;
        let allExpensesData = [];

        function loadExpenses(page = 1) {
            currentPage = page;
            fetch(`?action=get&page=${page}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allExpensesData = data.data.expenses;
                        renderExpenses(data.data.expenses);
                        renderPagination(data.data);
                        updateSummaryCards(data.data.expenses);
                    } else {
                        showError('Failed to load expenses: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Error loading expenses: ' + error.message);
                });
        }

        function updateSummaryCards(expenses) {
            // Calculate totals
            let totalAmount = 0;
            let monthAmount = 0;
            const categories = new Set();
            const currentMonth = new Date().getMonth();
            const currentYear = new Date().getFullYear();

            expenses.forEach(expense => {
                const amount = parseFloat(expense.amount) || 0;
                totalAmount += amount;
                categories.add(expense.category);
                
                const expenseDate = new Date(expense.date);
                if (expenseDate.getMonth() === currentMonth && expenseDate.getFullYear() === currentYear) {
                    monthAmount += amount;
                }
            });

            // Update cards
            document.getElementById('total-amount').textContent = 'PHP ' + totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('total-count').textContent = expenses.length.toLocaleString();
            document.getElementById('total-categories').textContent = categories.size;
            document.getElementById('month-amount').textContent = 'PHP ' + monthAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('current-month').textContent = new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            document.getElementById('record-info').textContent = `Showing ${expenses.length} expense${expenses.length !== 1 ? 's' : ''}`;
        }

        function getCategoryBadgeClass(category) {
            const classes = {
                'General': 'badge-general',
                'Office': 'badge-office',
                'Travel': 'badge-travel',
                'Equipment': 'badge-equipment',
                'Software': 'badge-software',
                'Utilities': 'badge-utilities',
                'Marketing': 'badge-marketing',
                'Other': 'badge-other'
            };
            return classes[category] || 'badge-general';
        }

        function renderExpenses(expenses) {
            const container = document.getElementById('expense-list');
            
            if (expenses.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h4>No expenses found</h4>
                        <p>Start by adding your first expense to track your spending.</p>
                        <button class="btn btn-add text-white mt-3" onclick="showAddModal()">
                            <i class="fas fa-plus me-2"></i>Add First Expense
                        </button>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Added By</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${expenses.map(expense => {
                                const isOwner = (expense.added_by === currentUser);
                                return `
                                <tr>
                                    <td>
                                        <span class="fw-semibold">${new Date(expense.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                                    </td>
                                    <td>${escapeHtml(expense.description)}</td>
                                    <td><span class="category-badge ${getCategoryBadgeClass(expense.category)}">${expense.category}</span></td>
                                    <td class="expense-amount">PHP ${parseFloat(expense.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                    <td><span class="payment-method"><i class="fas fa-wallet"></i>${expense.payment_method}</span></td>
                                    <td><span class="added-by"><i class="fas fa-user-circle"></i>${expense.added_by ?? 'Unknown'}</span></td>
                                    <td class="text-center">
                                        ${isOwner ? `
                                            <button class="btn btn-action btn-edit me-1" onclick="editExpense('${expense.id}')" title="Edit">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <button class="btn btn-action btn-delete" onclick="deleteExpense('${expense.id}', '${escapeHtml(expense.description)}')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        ` : `
                                            <span class="text-muted small" title="Only the creator can edit/delete"><i class="fas fa-lock"></i></span>
                                        `}
                                    </td>
                                </tr>
                            `}).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function renderPagination(data) {
            const container = document.getElementById('pagination-container');
            
            if (data.pages <= 1) {
                container.innerHTML = '';
                return;
            }

            let pagination = '<nav><ul class="pagination justify-content-center">';
            
            // Previous
            if (data.page > 1) {
                pagination += `<li class="page-item"><a class="page-link" href="#" onclick="loadExpenses(${data.page - 1}); return false;">Previous</a></li>`;
            }
            
            // Page numbers
            for (let i = 1; i <= data.pages; i++) {
                const active = i === data.page ? 'active' : '';
                pagination += `<li class="page-item ${active}"><a class="page-link" href="#" onclick="loadExpenses(${i}); return false;">${i}</a></li>`;
            }
            
            // Next
            if (data.page < data.pages) {
                pagination += `<li class="page-item"><a class="page-link" href="#" onclick="loadExpenses(${data.page + 1}); return false;">Next</a></li>`;
            }
            
            pagination += '</ul></nav>';
            container.innerHTML = pagination;
        }

        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Expense';
            document.getElementById('expenseForm').reset();
            document.getElementById('expenseId').value = '';
            document.getElementById('expenseDate').value = new Date().toISOString().split('T')[0];
            
            // Auto-detect current user (readonly field)
            document.getElementById('expenseAddedBy').value = currentUser;
            
            new bootstrap.Modal(document.getElementById('expenseModal')).show();
        }

        function editExpense(id) {
            fetch(`?action=get&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const expense = data.expense;
                        document.getElementById('modalTitle').textContent = 'Edit Expense';
                        document.getElementById('expenseId').value = expense.id;
                        document.getElementById('expenseDate').value = expense.date;
                        document.getElementById('expenseDescription').value = expense.description;
                        document.getElementById('expenseAmount').value = expense.amount;
                        document.getElementById('expenseCategory').value = expense.category;
                        document.getElementById('expensePaymentMethod').value = expense.payment_method;
                        document.getElementById('expenseAddedBy').value = expense.added_by || 'Unknown';
                        new bootstrap.Modal(document.getElementById('expenseModal')).show();
                    } else {
                        showError('Failed to load expense');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Error loading expense');
                });
        }

        function saveExpense() {
            const form = document.getElementById('expenseForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const id = document.getElementById('expenseId').value;
            const addedBy = document.getElementById('expenseAddedBy').value;

            const data = {
                date: document.getElementById('expenseDate').value,
                description: document.getElementById('expenseDescription').value,
                amount: parseFloat(document.getElementById('expenseAmount').value),
                category: document.getElementById('expenseCategory').value,
                payment_method: document.getElementById('expensePaymentMethod').value,
                added_by: addedBy
            };

            const action = id ? 'update' : 'add';
            const method = 'POST';
            const url = id ? `?action=update&id=${id}` : '?action=add';

            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('expenseModal')).hide();
                    loadExpenses(currentPage);
                    showSuccess(id ? 'Expense updated successfully' : 'Expense added successfully');
                } else {
                    showError(data.error || 'Failed to save expense');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error saving expense');
            });
        }

        function deleteExpense(id, description) {
            deleteExpenseId = id;
            document.getElementById('deleteExpenseDescription').textContent = description;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function confirmDelete() {
            if (!deleteExpenseId) return;

            fetch(`?action=delete&id=${deleteExpenseId}`, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    loadExpenses(currentPage);
                    showSuccess('Expense deleted successfully');
                } else {
                    showError('Failed to delete expense');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error deleting expense');
            });
        }

        function exportToCSV() {
            // Direct download - opens the export URL which streams CSV
            window.location.href = '?action=export';
        }

        function showSuccess(message) {
            showNotification(message, 'success');
        }

        function showError(message) {
            showNotification(message, 'danger');
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 5000);
        }

        // Load expenses when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadExpenses();
        });
    </script>
</body>
</html>
