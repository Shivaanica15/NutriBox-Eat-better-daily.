<?php

function sanitize_header_value($value)
{
    $value = trim($value);
    $value = str_replace(["\r", "\n"], "", $value);
    return $value;
}

function smtp_read_response($socket)
{
    $data = "";
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $data .= $line;
        if (preg_match("/^\d{3} /", $line)) {
            break;
        }
    }
    return $data;
}

function smtp_expect($socket, $expected_codes)
{
    $response = smtp_read_response($socket);
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, (array) $expected_codes, true)) {
        return [false, $response];
    }
    return [true, $response];
}

function smtp_command($socket, $command, $expected_codes)
{
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $expected_codes);
}

function smtp_send_mail($to, $subject, $body, $from_email, $from_name)
{
    global $last_smtp_error;
    $last_smtp_error = "";
    $host = SMTP_HOST;
    $port = (int) SMTP_PORT;
    $username = SMTP_USERNAME;
    $password = SMTP_PASSWORD;
    $encryption = SMTP_ENCRYPTION;

    if (!smtp_config_valid()) {
        $last_smtp_error = smtp_config_error();
        return [false, $last_smtp_error];
    }

    $transport = $encryption === "ssl" ? "ssl" : "tcp";
    $socket = stream_socket_client(
        $transport . "://" . $host . ":" . $port,
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT,
    );

    if (!$socket) {
        $last_smtp_error = "SMTP connection failed: " . $errstr;
        return [false, $last_smtp_error];
    }

    stream_set_timeout($socket, 10);

    [$ok] = smtp_expect($socket, [220]);
    if (!$ok) {
        fclose($socket);
        $last_smtp_error = "SMTP greeting failed.";
        return [false, $last_smtp_error];
    }

    [$ok, $resp] = smtp_command($socket, "EHLO localhost", [250]);
    if (!$ok) {
        fclose($socket);
        $last_smtp_error = "EHLO failed: " . $resp;
        return [false, $last_smtp_error];
    }

    if ($encryption === "tls" && stripos($resp, "STARTTLS") !== false) {
        [$ok, $resp] = smtp_command($socket, "STARTTLS", [220]);
        if (!$ok) {
            fclose($socket);
            $last_smtp_error = "STARTTLS failed: " . $resp;
            return [false, $last_smtp_error];
        }
        if (
            !stream_socket_enable_crypto(
                $socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT,
            )
        ) {
            fclose($socket);
            $last_smtp_error = "TLS negotiation failed.";
            return [false, $last_smtp_error];
        }
        [$ok, $resp] = smtp_command($socket, "EHLO localhost", [250]);
        if (!$ok) {
            fclose($socket);
            $last_smtp_error = "EHLO after STARTTLS failed: " . $resp;
            return [false, $last_smtp_error];
        }
    }

    if ($username !== "" && $password !== "") {
        [$ok, $resp] = smtp_command($socket, "AUTH LOGIN", [334]);
        if (!$ok) {
            fclose($socket);
            $last_smtp_error = "AUTH LOGIN failed: " . $resp;
            return [false, $last_smtp_error];
        }
        [$ok, $resp] = smtp_command($socket, base64_encode($username), [334]);
        if (!$ok) {
            fclose($socket);
            $last_smtp_error = "SMTP username rejected: " . $resp;
            return [false, $last_smtp_error];
        }
        [$ok, $resp] = smtp_command($socket, base64_encode($password), [235]);
        if (!$ok) {
            fclose($socket);
            $last_smtp_error = "SMTP password rejected: " . $resp;
            return [false, $last_smtp_error];
        }
    }

    [$ok, $resp] = smtp_command($socket, "MAIL FROM:<" . $from_email . ">", [
        250,
    ]);
    if (!$ok) {
        fclose($socket);
        $last_smtp_error = "MAIL FROM failed: " . $resp;
        return [false, $last_smtp_error];
    }

    [$ok, $resp] = smtp_command($socket, "RCPT TO:<" . $to . ">", [250, 251]);
    if (!$ok) {
        fclose($socket);
        $last_smtp_error = "RCPT TO failed: " . $resp;
        return [false, $last_smtp_error];
    }

    [$ok, $resp] = smtp_command($socket, "DATA", [354]);
    if (!$ok) {
        fclose($socket);
        $last_smtp_error = "DATA failed: " . $resp;
        return [false, $last_smtp_error];
    }

    $subject = sanitize_header_value($subject);
    $from_name = sanitize_header_value($from_name);
    $from_email = sanitize_header_value($from_email);

    $headers = [
        "From: " . $from_name . " <" . $from_email . ">",
        "Reply-To: " . $from_email,
        "MIME-Version: 1.0",
        "Content-Type: text/plain; charset=UTF-8",
    ];

    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = preg_replace("/^\./m", "..", $body);

    $payload =
        "Subject: " .
        $subject .
        "\r\n" .
        implode("\r\n", $headers) .
        "\r\n\r\n" .
        $body .
        "\r\n.";

    fwrite($socket, $payload . "\r\n");
    [$ok, $resp] = smtp_expect($socket, [250]);
    smtp_command($socket, "QUIT", [221]);
    fclose($socket);

    if (!$ok) {
        $last_smtp_error = "SMTP send failed: " . $resp;
        return [false, $last_smtp_error];
    }

    return [true, ""];
}

function send_notification_email($to, $subject, $body)
{
    $to = sanitize_header_value($to);
    $subject = sanitize_header_value($subject);
    $from_email = sanitize_header_value(SMTP_FROM_EMAIL);
    $from_name = sanitize_header_value(SMTP_FROM_NAME);

    if ($to === "" || $from_email === "") {
        return [false, "missing sender or recipient."];
    }

    if (class_exists("\\PHPMailer\\PHPMailer\\PHPMailer")) {
        global $last_smtp_error;
        $last_smtp_error = "";
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->Port = (int) SMTP_PORT;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            if (SMTP_ENCRYPTION === "ssl") {
                $mail->SMTPSecure =
                    \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif (SMTP_ENCRYPTION === "tls") {
                $mail->SMTPSecure =
                    \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
            return [true, ""];
        } catch (\Exception $e) {
            $last_smtp_error = $e->getMessage();
            return [false, $last_smtp_error];
        }
    }

    return smtp_send_mail($to, $subject, $body, $from_email, $from_name);
}

function smtp_config_error()
{
    if (SMTP_HOST === "") {
        return "SMTP_HOST is not configured.";
    }
    if (SMTP_PORT <= 0) {
        return "SMTP_PORT is invalid.";
    }
    if (SMTP_USERNAME === "" || SMTP_PASSWORD === "") {
        return "SMTP credentials are not configured.";
    }
    if (SMTP_FROM_EMAIL === "") {
        return "SMTP_FROM_EMAIL is not configured.";
    }
    if (
        SMTP_ENCRYPTION !== "" &&
        SMTP_ENCRYPTION !== "tls" &&
        SMTP_ENCRYPTION !== "ssl"
    ) {
        return "SMTP_ENCRYPTION must be tls or ssl.";
    }
    return "";
}

function smtp_last_error()
{
    global $last_smtp_error;
    return $last_smtp_error ?? "";
}

?>
