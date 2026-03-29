<?php
require __DIR__ . '/vendor/autoload.php';
$r = new ReflectionClass('App\\Service\\PasskeyAuthService');
foreach ($r->getConstructor()->getParameters() as $i => $p) {
    $type = $p->getType();
    echo $i . ': ' . $p->getName() . ' => ' . ($type ? $type->getName() : 'null') . "\n";
}
