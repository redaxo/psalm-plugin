<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
;

return Redaxo\PhpCsFixerConfig\Config::redaxo5()
    ->setFinder($finder)
;
