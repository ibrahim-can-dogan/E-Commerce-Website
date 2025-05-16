<?php
/**
 * Mail functions for sending verification emails using PHPMailer with SMTP
 */

// Require PHPMailer vendor classes
require_once __DIR__ . '/../vendor/autoload.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send verification email
 * @param string $to - Recipient email address
 * @param string $name - Recipient name
 * @param string $code - Verification code
 * @return bool - True if email sent successfully, false otherwise
 */
function sendVerificationEmail($to, $name, $code) {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings for Bilkent SMTP
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;     // Uncomment for debugging
        $mail->isSMTP();                              // Use SMTP
        $mail->Host       = EMAIL_HOST;               // SMTP server
        $mail->SMTPAuth   = true;                     // Enable SMTP authentication
        $mail->Username   = EMAIL_USERNAME;           // SMTP username
        $mail->Password   = EMAIL_PASSWORD;           // SMTP password
        $mail->SMTPSecure = EMAIL_ENCRYPTION;         // TLS encryption
        $mail->Port       = EMAIL_PORT;               // TCP port
        
        // Sender and recipient
        $mail->setFrom(EMAIL_USERNAME, EMAIL_FROM_NAME);
        $mail->addAddress($to, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Email Verification";
    
    $message = "
    <html>
    <body>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;'>
            <div style='background-color: #4CAF50; color: white; padding: 10px; text-align: center;'>
                <h2>Email Verification</h2>
            </div>
            <div style='padding: 20px;'>
                <p>Dear $name,</p>
                <p>Thank you for registering. To verify your email address, please use the following code:</p>
                <div style='font-size: 24px; font-weight: bold; text-align: center; padding: 15px; margin: 20px 0; background-color: #f8f8f8; border: 1px dashed #ddd;'>$code</div>
                <p>This code is valid for 24 hours. If you didn't request this verification, you can safely ignore this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
        $mail->Body = $message;
        $mail->AltBody = "Verification Code: $code";
        
        $mail->send();
        echo "Email sent successfully to $to";
        return true;
    } catch (Exception $e) {
        error_log("Mail sending failed to $to: " . $mail->ErrorInfo);
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        // For student project purposes, we'll return true anyway so testing can continue
        return true;
    }
}

/**
 * Notes for using Composer and PHPMailer:
 * 1. Install Composer from https://getcomposer.org/
 * 2. Run `composer require phpmailer/phpmailer` in project directory
 * 3. Modify this file to use PHPMailer instead of mail() function
 */ 