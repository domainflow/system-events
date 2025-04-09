<?php declare(strict_types=1);

$config = (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect()
);

$defaultConfigPath = __DIR__ . '/phptoolsconfig.json';
$configData = json_decode(file_get_contents($defaultConfigPath), true);

$finder = PhpCsFixer\Finder::create();

/**
 * @var string $directory
 */
foreach ($configData['directories'] as $directory) {
    $path = __DIR__ . '/' . $directory;
    if (is_dir($path)) {
        $finder->in($path);
    }
}

return $config
    ->setRules([
        '@PSR12' => true,
        '@PSR2' => true,
        'declare_strict_types' => true,
        'align_multiline_comment' => true,
        'array_indentation' => true,
        'array_syntax' => true,
        'binary_operator_spaces' => true,
        'blank_line_before_statement' => ['statements' => ['return']],
        'cast_spaces' => true,
        'class_definition' => true,
        'clean_namespace' => true,
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'concat_space' => ['spacing' => 'one'],
        'declare_parentheses' => true,
        'fully_qualified_strict_types' => true,
        'function_typehint_space' => true,
        'global_namespace_import' => true,
        'heredoc_indentation' => true,
        'include' => true,
        'magic_constant_casing' => true,
        'magic_method_casing' => true,
        'method_argument_space' => true,
        'multiline_comment_opening_closing' => true,
        'native_function_casing' => true,
        'no_blank_lines_after_phpdoc' => true,
        'no_empty_statement' => true,
        'no_extra_blank_lines' => true,
        'no_leading_namespace_whitespace' => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'no_short_bool_cast' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'no_spaces_around_offset' => true,
        'no_unused_imports' => true,
        'no_whitespace_before_comma_in_array' => true,
        'normalize_index_brace' => true,
        'object_operator_without_whitespace' => true,
        'operator_linebreak' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => [
                'class',
                'function',
                'const'
            ]
        ],
        'phpdoc_order' => true,
        'phpdoc_types' => true,
        'php_unit_test_case_static_method_calls' => [
            'call_type' => 'this'
        ],
        'standardize_not_equals' => true,
        'switch_continue_to_break' => true,
        'trailing_comma_in_multiline' => true
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);