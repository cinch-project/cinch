<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude(['vendor', 'resources', 'docker', 'config'])
    ->notName('php_cs.php')
    ->name(['\.php$', '/^cinch$/'])
    ->ignoreVCSIgnored(true)
    ->in(__DIR__);

$config = new PhpCsFixer\Config();
return $config->setRiskyAllowed(false)->setRules([
    '@PER' => true,
    'elseif' => false,
    'braces' => false, // control_structure_braces false should be enough
    'control_structure_braces' => false,
    'curly_braces_position' => [
        'control_structures_opening_brace' => 'same_line'
    ],
    'method_argument_space' => ['on_multiline' => 'ignore'],
    'array_syntax' => ['syntax' => 'short'],
])->setFinder($finder);
