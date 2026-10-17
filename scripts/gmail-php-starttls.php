<?php

// Uses the same Symfony SMTP implementation as Laravel, without credentials.
require __DIR__.'/../vendor/autoload.php';
$report = ['php_starttls_verified' => false, 'authentication_attempted' => false, 'email_sent' => false, 'certificate_verification_disabled' => false];
try {
    $transport = (new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory())->create(
        new \Symfony\Component\Mailer\Transport\Dsn('smtp', 'smtp.gmail.com', null, null, 587, ['auto_tls' => true, 'require_tls' => true]),
    );
    $transport->getStream()->setTimeout(15);
    $transport->start();
    // start() throws unless TLS is negotiated when this requirement is set.
    // SocketStream::isTLS() only describes implicit TLS, not STARTTLS upgrades.
    $report['php_starttls_verified'] = $transport->isTlsRequired();
    $transport->stop();
} catch (\Throwable $error) {
    $report['failure_type'] = get_class($error);
}
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
file_put_contents(__DIR__.'/../docs/gmail-php-starttls.json', $json);
echo $json;
exit($report['php_starttls_verified'] ? 0 : 1);
