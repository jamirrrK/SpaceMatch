<?php

require __DIR__.'/../vendor/autoload.php';
$report = ['host' => 'smtp-relay.brevo.com', 'port' => 587, 'starttls_verified' => false, 'credentials_sent' => false, 'email_sent' => false, 'certificate_verification_disabled' => false];
try {
    $transport = (new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory())->create(
        new \Symfony\Component\Mailer\Transport\Dsn('smtp', 'smtp-relay.brevo.com', null, null, 587, ['auto_tls' => true, 'require_tls' => true]),
    );
    $transport->getStream()->setTimeout(15);
    $transport->start();
    // Successful start with required TLS proves negotiated TLS. isTLS() on the
    // socket only indicates implicit TLS, not a successful STARTTLS upgrade.
    $report['starttls_verified'] = $transport->isTlsRequired();
    $transport->stop();
} catch (\Throwable $error) {
    $report['failure_type'] = get_class($error);
}
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
file_put_contents(__DIR__.'/../docs/brevo-starttls.json', $json);
echo $json;
exit($report['starttls_verified'] ? 0 : 1);
