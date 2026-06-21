<?php
require_once __DIR__ . "/../config/env.php";
require_once __DIR__ . "/../config/app.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../vendor/autoload.php';

function sendOTP($email, $otp)
{
    $mail = new PHPMailer(true);
    $safe_otp = htmlspecialchars((string) $otp, ENT_QUOTES, 'UTF-8');
    $logo_url = htmlspecialchars(BASE_URL . 'assets/images/printing-logo.png', ENT_QUOTES, 'UTF-8');

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('MAIL_USER');
        $mail->Password = getenv('MAIL_PASS');
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom(getenv('MAIL_USER'), 'PrintEase');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Your PrintEase password reset code";
        $mail->Body = "
            <table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0' style='width:100%;margin:0;padding:0;background:#f3f9ff;font-family:Arial,Helvetica,sans-serif;color:#070566;'>
                <tr>
                    <td align='center' style='padding:32px 16px;'>
                        <table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0' style='max-width:560px;width:100%;background:#ffffff;border:1px solid #d7ecfb;border-radius:16px;box-shadow:0 14px 34px rgba(7,5,102,0.12);overflow:hidden;'>
                            <tr>
                                <td style='padding:30px 28px 18px;text-align:center;background:#070566;'>
                                    <h1 style='margin:0;color:#ffffff;font-size:24px;line-height:1.25;font-weight:800;'>Password reset code</h1>
                                    <p style='margin:9px 0 0;color:#c8f7ff;font-size:14px;line-height:1.5;'>Use this one-time code to continue resetting your PrintEase password.</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:32px 28px;text-align:center;'>
                                    <p style='margin:0 0 14px;color:#0070bf;font-size:14px;font-weight:700;line-height:1.5;'>Your verification code is</p>
                                    <div style='display:inline-block;padding:16px 22px;border:1px solid #77d5ff;border-radius:12px;background:#eaf4ff;color:#070566;font-size:34px;line-height:1;font-weight:800;letter-spacing:8px;'>{$safe_otp}</div>
                                    <p style='margin:20px 0 0;color:#3d6384;font-size:14px;line-height:1.6;'>This code expires in <strong style='color:#070566;'>5 minutes</strong>. If you did not request a password reset, you can safely ignore this email.</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:18px 28px 26px;text-align:center;border-top:1px solid #edf7ff;'>
                                    <p style='margin:0;color:#6a86a0;font-size:12px;line-height:1.5;'>PrintEase E-Printing System</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        ";
        $mail->AltBody = "Your PrintEase password reset code is {$otp}. This code expires in 5 minutes. If you did not request a password reset, you can ignore this email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
