<?php

$finder = PhpCsFixer\Finder::create()
    ->in('src');

$config = new PhpCsFixer\Config();
return $config->setRiskyAllowed(false)->setRules([
    '@PER' => true,
    'elseif' => false,
    'braces' => false, // should not be required with control_structure_braces set to false
    'control_structure_braces' => false,
    'method_argument_space' => false,
    'strict_param' => false,
    'array_syntax' => ['syntax' => 'short'],
])->setFinder($finder);
