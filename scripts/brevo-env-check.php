<?php

require __DIR__.'/../vendor/autoload.php';
$report = ['dotenv_syntax_valid' => false, 'private_smtp_key_present' => false, 'credential_type_plausible' => false, 'credential_value_displayed' => false];
try {
    $values = \Dotenv\Dotenv::parse(file_get_contents(__DIR__.'/../.env'));
    $key = (string) ($values['MAIL_PASSWORD'] ?? '');
    $report['dotenv_syntax_valid'] = true;
    $report['private_smtp_key_present'] = $key !== '' && strtolower($key) !== 'null';
    $report['credential_type_plausible'] = strlen($key) >= 15
        && preg_match('/\s/', $key) === 0
        && preg_match('/^[A-Za-z0-9]{16}$/D', $key) !== 1
        && ! str_starts_with(strtolower($key), 'xkeysib-')
        && ! in_array(strtolower($key), ['your_brevo_smtp_key', 'your-brevo-smtp-key', 'your_smtp_key_here'], true);
    unset($key, $values);
} catch (\Throwable) {
    $report['blocked_reason'] = 'Check .env syntax privately; parser exception suppressed.';
}
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($report['dotenv_syntax_valid'] && $report['credential_type_plausible'] ? 0 : 1);
