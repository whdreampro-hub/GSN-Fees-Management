<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Load environment variables manually
function loadEnv($path) {
    if (!file_exists($path)) return false;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, "\"'");
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

loadEnv(__DIR__ . '/.env');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $academic_year_id = isset($_POST['academic_year_id']) ? (int)$_POST['academic_year_id'] : 0;
    $term = isset($_POST['term']) ? (int)$_POST['term'] : 0;
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

    if ($student_id && $academic_year_id && $term && $amount > 0 && $phone) {
        try {
            $env_mode = $_ENV['FLW_ENVIRONMENT'] ?? 'test';
            $secret_key = ($env_mode === 'live') ? ($_ENV['FLWSECK_LIVE'] ?? '') : ($_ENV['FLWSECK_TEST'] ?? '');
            
            if (empty($secret_key) || $secret_key === 'YOUR_LIVE_SECRET_KEY') {
                throw new Exception("Payment gateway configuration missing or live keys not set. Please check .env file.");
            }

            // Flutterwave API request
            $tx_ref = 'gsn-' . uniqid() . '-' . time();
            
            $payload = [
                "tx_ref" => $tx_ref,
                "amount" => $amount,
                "currency" => "RWF", // Rwandan Francs
                "email" => "student" . $student_id . "@gsn.edu.rw",
                "phone_number" => $phone,
                "fullname" => "Student " . $student_id,
                "meta" => [
                    "student_id" => $student_id,
                    "term" => $term,
                    "academic_year_id" => $academic_year_id
                ]
            ];

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.flutterwave.com/v3/charges?type=mobile_money_rwanda",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer " . $secret_key,
                    "Content-Type: application/json"
                ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                throw new Exception("cURL Error: " . $err);
            } else {
                $res = json_decode($response);
                if (isset($res->status) && $res->status === 'success') {
                    // Check if Flutterwave returned a redirect URL (typical in TEST mode)
                    if (isset($res->meta->authorization->mode) && $res->meta->authorization->mode === 'redirect') {
                        // We do NOT insert the payment yet because they haven't paid!
                        echo json_encode([
                            'success' => true, 
                            'redirect' => $res->meta->authorization->redirect,
                            'message' => 'Please click the link below to authorize the test payment.'
                        ]);
                        exit;
                    }

                    // Payment successfully processed / initiated
                    // WARNING: In a real LIVE system, you should rely on Flutterwave Webhooks to insert this,
                    // not immediately upon "charge initiated".
                    $stmt = $pdo->prepare("INSERT INTO payments (student_id, academic_year_id, term, amount_paid) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$student_id, $academic_year_id, $term, $amount]);
                    
                    echo json_encode(['success' => true, 'message' => "Payment of FRW " . number_format($amount) . " processed successfully via Flutterwave."]);
                } else {
                    $error_msg = isset($res->message) ? $res->message : 'Unknown payment error';
                    echo json_encode(['success' => false, 'message' => 'Payment failed: ' . $error_msg]);
                }
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Please provide all required fields.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
