<?php

return (new PhpCsFixer\Config())
  ->setUsingCache(false)
  ->setRiskyAllowed(true)
  //->setCacheFile(__DIR__ . '/.php_cs.cache')
  ->setRules([
    '@PSR1' => true,
    '@PSR2' => true,
    '@Symfony' => true,
    'psr_autoloading' => true,
    'yoda_style' => false,
    'array_syntax' => ['syntax' => 'short'],
    'list_syntax' => ['syntax' => 'short'],
    'concat_space' => ['spacing' => 'one'],
    'cast_spaces' => ['space' => 'none'],
    'compact_nullable_typehint' => true,
    'increment_style' => ['style' => 'post'],
    'declare_equal_normalize' => ['space' => 'single'],
    'echo_tag_syntax' => ['format' => 'long'],
    'protected_to_private' => false,
    'phpdoc_align' => false,
    'phpdoc_add_missing_param_annotation' => ['only_untyped' => false],
    'phpdoc_order' => true, // psr-5
    'phpdoc_no_empty_return' => false,
    'align_multiline_comment' => true, // psr-5
    'general_phpdoc_annotation_remove' => [
      'annotations' => [
        'author',
        'package',
      ],
    ],
  ])
  ->setFinder(PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true));
