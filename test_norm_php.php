<?php
// Kullanım: "C:/xampp/php/php.exe" test_norm_php.php
require __DIR__ . '/norm.php';
$cases = json_decode(file_get_contents(__DIR__ . '/test_norm_cases.json'), true);
$pass = 0; $fail = 0;
$extExc = array_merge(nx_default_exceptions(), ['TSŞ']);
foreach ($cases as [$inp, $fmt, $opt, $expected]) {
    $opts = [];
    if ($opt === 'conn_off') $opts['connectors'] = false;
    if ($opt === 'exc_ext') $opts['exceptions'] = $extExc;
    $got = nx_norm($inp, $fmt, $opts);
    if ($got === $expected) { $pass++; }
    else { $fail++; echo "FAIL ", json_encode($inp, JSON_UNESCAPED_UNICODE), " [$fmt] → ", json_encode($got, JSON_UNESCAPED_UNICODE), " beklenen ", json_encode($expected, JSON_UNESCAPED_UNICODE), PHP_EOL; }
}
echo "PHP: $pass geçti, $fail kaldı", PHP_EOL;
exit($fail ? 1 : 0);
