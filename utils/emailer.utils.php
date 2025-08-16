<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/environment.config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Config\Environment;

Environment::loadEnv();

final class EmailSender
{
    private array $messages;
    private array $cfg;
    private bool $enabled = false;
    private string $logoPath;

    public function __construct()
    {
        $this->messages = require __DIR__ . '/../utils/messages.error.php';
        $this->cfg = include __DIR__ . '/../config/validate.php';

        // Detectar si SMTP está configurado (las 4 claves base)
        $need = ['HOST_SMTP', 'USERNAME_SMTP', 'PASS_SMTP', 'PORT_SMTP'];
        $missing = array_filter($need, fn($k) => empty($this->cfg[$k]));

        $this->enabled = empty($missing);

        // Logo por defecto (de tu versión)
        $this->logoPath = "https://static.wixstatic.com/media/95b6a4_a5da7c3855d34b5382f26553c6e6e9ff~mv2.png/v1/fill/w_113,h_74,al_c,q_85,usm_0.66_1.00_0.01,enc_avif,quality_auto/95b6a4_a5da7c3855d34b5382f26553c6e6e9ff~mv2.png";
    }

    /**
     * Configura PHPMailer con SMTP si está disponible.
     * Si no hay SMTP, lanza RuntimeException y los callers deben manejar (o bien retornamos true antes de llamar).
     */
    private function setupMailer(string $subject, string $toEmail): PHPMailer
    {
        if (!$this->enabled) {
            throw new RuntimeException('SMTP no configurado (modo no-op).');
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = (string)$this->cfg['HOST_SMTP'];
            $mail->SMTPAuth   = true;
            $mail->Username   = (string)$this->cfg['USERNAME_SMTP'];
            $mail->Password   = (string)$this->cfg['PASS_SMTP'];

            $secure = strtolower((string)($this->cfg['SMTP_SECURE'] ?? 'tls'));
            if ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = (int)($this->cfg['PORT_SMTP'] ?? 465);
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = (int)($this->cfg['PORT_SMTP'] ?? 587);
            }

            $mail->CharSet  = 'UTF-8';

            // From: preferir EMAIL_FROM; si no, usar USERNAME_SMTP.
            $fromEmail = $this->cfg['EMAIL_FROM'] ?? $this->cfg['USERNAME_SMTP'];
            $fromName  = $this->cfg['EMAIL_FROM_NAME'] ?? 'Los Reyes del Usado';

            $mail->setFrom((string)$fromEmail, (string)$fromName);
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;

            return $mail;
        } catch (Exception $e) {
            error_log("Error configurando el mailer: {$e->getMessage()}");
            throw new RuntimeException('Error al configurar el servicio de correo.');
        }
    }

    /* ==========================
       Plantillas comunes
       ========================== */

    private function getCommonHead(): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            {$this->getCommonStyles()}
        </head>
        HTML;
    }

    private function getCommonStyles(): string
    {
        return <<<CSS
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #000000; }
            a { text-decoration: none; }
            h4 { zoom: 1.2; color: #ffffff; }
            h4 a { color: #ffffff !important; text-decoration: none; }
            .white_p { color: #ffffff !important; }
            .email-container { max-width: 600px; margin: 0 auto; background-color: #141414; }
            .header { text-align: center; padding: 20px 0; background-color: #141414; }
            .logo { max-width: 150px; height: auto; }
            .content { background-color: #141414; padding-bottom: 30px; padding-right: 30px; padding-left: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); color: white; }
            .btn { display: inline-block; padding: 12px 24px; background-color: #EFD36C; color: black !important; border-radius: 5px; margin: 20px 0; font-weight: bold; }
            .footer { text-align: center; margin-top: 20px; padding: 20px; font-size: 0.9em; color: #6c757d; }
            .titulo { color: #EFD36C; text-align: center; padding: 0px; }
            .form1 { width: 100%; min-height: 30px; background-color: #282828; border: none; color: white; margin: 10px; }
            .btn12 { text-align: center; }
            .code { color: #EFD36C; font-weight: bold; font-size: 30px; text-transform: uppercase; }
            .CenterUsr { text-align: center; }
            .ContentCode { background-color: #2e2700; border-radius: 15px; padding: 30px; }
        </style>
        CSS;
    }

    private function getCommonFooter(): string
    {
        return <<<HTML
        <div class='footer'>
            <p>This email was sent automatically, please do not reply.</p>
            <p>&copy; 2025 | Zelcar Games LLC. All rights reserved.</p>
            <p>
                <a href="https://www.zelcar.games/copia-de-privacy-policy" style="color:#6c757d;">Privacy Policy</a> |
                <a href="https://www.zelcar.games/copia-de-cookies-policy" style="color:#6c757d;">Cookies</a> |
                <a href="https://www.zelcar.games/" style="color:#6c757d;">Terms & Conditions</a>
            </p>
        </div>
        HTML;
    }

    /* ==========================
       Helpers de envío (no-op en local)
       ========================== */

    private function canSend(): bool
    {
        if ($this->enabled) return true;
        // En local sin SMTP, hacemos no-op silencioso para no romper flujos
        error_log('[EmailSender] SMTP no configurado; se omite envío (no-op).');
        return false;
    }

    /* ==========================
       Métodos públicos
       ========================== */

    /** Enviar código de activación */
    public function sendActivateAccountEmailCode(string $email, string $code): bool
    {
        if (!$this->canSend()) return true;

        try {
            $mail = $this->setupMailer('Activate your account', $email);
            $safeEmail = htmlspecialchars($email);
            $safeCode  = htmlspecialchars($code);
            $mail->Body = <<<HTML
            {$this->getCommonHead()}
            <body role="document" aria-label="Email body">
                <main role="main">
                    <div class='email-container'>
                        <div class='header'>
                            <img src='{$this->logoPath}' alt='Secret Forest Logo' class='logo'>
                        </div>
                        <div class='content'>
                            <h1 class='titulo'>Activate your account</h1>
                            <div class='CenterUsr'>
                                <h4>Hello, {$safeEmail}</h4>
                                <p class="white_p">Your activation code is:</p>
                            </div>
                            <div class='CenterUsr ContentCode'>
                                <span class='code'>{$safeCode}</span>
                            </div>
                            <p class='CenterUsr white_p'>Please use this code to activate your account.</p>
                            <h4 class='titulo'>Welcome to Secret Forest!</h4>
                        </div>
                    </div>
                </main>
                {$this->getCommonFooter()}
            </body>
            </html>
            HTML;
            $mail->AltBody = "Account Created Successfully!\n\nYour activation code is: {$code}";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar el correo para crear tu cuenta: {$e->getMessage()}");
            return false;
        }
    }

    /** Enlace de activación */
    public function sendActivationEmailLink(string $email, string $activationLink): void
    {
        if (!$this->canSend()) return;

        try {
            $mail = $this->setupMailer('Activación de tu cuenta de Secret Forest', $email);
            $safeLink = htmlspecialchars($activationLink);
            $mail->Body = "
            {$this->getCommonHead()}
            <body>
                <main>
                    <div class='email-container'>
                        <div class='header'>
                            <img src='{$this->logoPath}' alt='Logo' class='logo'>
                        </div>
                        <div class='content'>
                            <h1 style='color:#EFD36C; text-align:center;'>¡Bienvenido!</h1>
                            <p>Gracias por registrarte. Para activar tu cuenta hacé clic en el botón:</p>
                            <div style='text-align:center;'>
                                <a href='{$safeLink}' class='btn'>Activar mi cuenta</a>
                            </div>
                            <p style='margin-top:20px;'>Si el botón no funciona, copiá y pegá este enlace:</p>
                            <p style='word-break:break-all; color:#EFD36C;'>
                                <a style='color:#EFD36C;' href='{$safeLink}'>{$safeLink}</a>
                            </p>
                        </div>
                    </div>
                </main>
                {$this->getCommonFooter()}
            </body>
            </html>";
            $mail->AltBody = "Bienvenido a Secret Forest\n\nPara activar tu cuenta, visitá: {$activationLink}";
            $mail->send();
        } catch (Exception $e) {
            error_log("Error al enviar el correo: {$e->getMessage()}");
            throw new RuntimeException($this->messages['ERROR_REGISTER_FAILED'] ?? 'No se pudo enviar el correo.');
        }
    }

    /** Recovery por link */
    public function sendRecoveryEmailByRecoveryLink(string $email, string $recoveryLink): void
    {
        if (!$this->canSend()) return;

        try {
            $mail = $this->setupMailer('Recuperación de contraseña', $email);
            $safeLink = htmlspecialchars($recoveryLink);
            $mail->Body = "
            {$this->getCommonHead()}
            <body>
                <main>
                    <div class='email-container'>
                        <div class='header'>
                            <img src='{$this->logoPath}' alt='Logo' class='logo'>
                        </div>
                        <div class='content'>
                            <h1 class='titulo'>Recuperación de contraseña</h1>
                            <p>Recibimos una solicitud para restablecer tu contraseña.</p>
                            <div style='text-align:center;'>
                                <a href='{$safeLink}' class='btn'>Restablecer contraseña</a>
                            </div>
                            <p>Si no solicitaste este cambio, ignorá este correo.</p>
                        </div>
                    </div>
                </main>
                {$this->getCommonFooter()}
            </body>
            </html>";
            $mail->AltBody = "Recuperación de contraseña\n\nRestablecé tu contraseña aquí: {$recoveryLink}";
            $mail->send();
        } catch (Exception $e) {
            error_log("Error al enviar el correo de recuperación: {$e->getMessage()}");
            throw new RuntimeException('No se pudo enviar el correo de recuperación. Intentalo nuevamente.');
        }
    }

    /** Cuenta activada OK */
    public function sendActivateAccountEmailSuccess(string $email, string $username): bool
    {
        if (!$this->canSend()) return true;

        try {
            $mail = $this->setupMailer('Cuenta Activada Exitosamente', $email);
            $safeUser = htmlspecialchars($username);
            $mail->Body = "
            {$this->getCommonHead()}
            <body>
                <main>
                    <div class='email-container'>
                        <div class='header'>
                            <img src='{$this->logoPath}' alt='Logo' class='logo'>
                        </div>
                        <div class='content'>
                            <h1 class='titulo'>Cuenta Activada</h1>
                            <h4>Hola, {$safeUser}</h4>
                            <p>Ya podés ingresar al juego.</p>
                        </div>
                    </div>
                </main>
                {$this->getCommonFooter()}
            </body>
            </html>";
            $mail->AltBody = "Cuenta activada exitosamente.";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar el correo: {$e->getMessage()}");
            return false;
        }
    }

    /** Cuenta activada con código (confirmación) */
    public function sendActivateAccountEmailCodeSuccess(string $email, string $username, string $code): bool
    {
        if (!$this->canSend()) return true;

        try {
            $mail = $this->setupMailer('Cuenta Activada Exitosamente', $email);
            $safeUser = htmlspecialchars($username);
            $safeCode = htmlspecialchars($code);
            $mail->Body = "
            {$this->getCommonHead()}
            <body>
                <main>
                    <div class='email-container'>
                        <div class='header'>
                            <img src='{$this->logoPath}' alt='Logo' class='logo'>
                        </div>
                        <div class='content'>
                            <h1 class='titulo'>Cuenta Activada</h1>
                            <h4>Hola, {$safeUser}</h4>
                            <p>Gracias por canjear tu código: <span>{$safeCode}</span></p>
                            <p>Ya podés ingresar al juego.</p>
                        </div>
                    </div>
                </main>
                {$this->getCommonFooter()}
            </body>
            </html>";
            $mail->AltBody = "Cuenta creada exitosamente. Código: {$code}";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar el correo: {$e->getMessage()}");
            return false;
        }
    }

    /** Password actualizada OK */
    public function sendNewPasswordEmailSuccess(string $email, string $username): bool
    {
        if (!$this->canSend()) return true;

        try {
            $mail = $this->setupMailer('Contraseña Actualizada', $email);
            $safeUser = htmlspecialchars($username);
            $mail->Body = "
            {$this->getCommonHead()}
            <body>
                <main>
                    <div class='email-container'>
                        <div class='header'>
                            <img src='{$this->logoPath}' alt='Logo' class='logo'>
                        </div>
                        <div class='content'>
                            <h1 class='titulo'>Contraseña Actualizada</h1>
                            <p>Hola, {$safeUser}</p>
                            <p>Tu nueva contraseña ya se encuentra activa.</p>
                            <p>Si no solicitaste este cambio, contactanos.</p>
                        </div>
                    </div>
                </main>
                {$this->getCommonFooter()}
            </body>
            </html>";
            $mail->AltBody = "Contraseña actualizada exitosamente.";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar el correo: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * (Demo) Enviar un email solicitando nueva contraseña (form estático).
     * Nota: enviar contraseñas por email no es recomendable. Usá links de reset.
     */
    public function sendNewPasswordLink(string $email, string $password): void
    {
        if (!$this->canSend()) return;

        try {
            $mail = $this->setupMailer('Reingrese la nueva contraseña', $email);
            $mail->Body = "
            {$this->getCommonHead()}
            <body>
                <main>
                    <div class='email-container'>
                        <div class='header'>
                            <img src='{$this->logoPath}' alt='Logo' class='logo'>
                        </div>
                        <div class='content'>
                            <h1 class='titulo'>Recuperación de contraseña</h1>
                            <label>Ingrese su contraseña</label><br>
                            <input type='password' class='form1'><br>
                            <label>Reingrese su contraseña</label><br>
                            <input type='password' class='form1'><br>
                            <div class='btn12'>
                                <button class='btn btn-primary' type='button'>Enviar</button>
                            </div>
                        </div>
                    </div>
                </main>
                {$this->getCommonFooter()}
            </body>
            </html>";
            $mail->AltBody = "Envío de nueva contraseña";
            $mail->send();
        } catch (Exception $e) {
            error_log("Error al enviar el correo de recuperación: {$e->getMessage()}");
            throw new RuntimeException('No se pudo enviar el correo de recuperación. Intentalo nuevamente.');
        }
    }
}
