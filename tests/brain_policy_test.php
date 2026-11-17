<?php

/**
 * Self-contained regression tests for the real px_password_strength policy code.
 * No Roundcube installation, database, secrets or external test helpers needed.
 */
declare(strict_types=1);

class rcube_plugin
{
}

require_once dirname(__DIR__) . '/px_password_strength.php';

final class BrainTestConfig
{
    private $values;

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }
}

function brainTestPlugin(array $config): px_password_strength
{
    $plugin = new px_password_strength();
    $runtime = (object) ['config' => new BrainTestConfig($config)];

    $property = new ReflectionProperty(px_password_strength::class, 'rc');
    $property->setValue($plugin, $runtime);

    return $plugin;
}

function brainTestCall(px_password_strength $plugin, string $method, ...$arguments)
{
    $reflection = new ReflectionMethod(px_password_strength::class, $method);

    return $reflection->invoke($plugin, ...$arguments);
}

function brainTestEquals($expected, $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $label . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }

    echo "PASS: $label\n";
}

try {
    $flags = [
        ['false', true, false],
        [' OFF ', true, false],
        ['no', true, false],
        ['0', true, false],
        ['', true, false],
        ['true', false, true],
        [' TRUE ', false, true],
        ['on', false, true],
        ['yes', false, true],
        ['1', false, true],
        [0, true, false],
        [2, false, true],
        [[], true, true],
        [[], false, false],
        [null, true, true],
    ];

    foreach ($flags as $index => $flag) {
        [$value, $default, $expected] = $flag;
        $plugin = brainTestPlugin(['flag' => $value]);
        brainTestEquals(
            $expected,
            brainTestCall($plugin, 'cfg_bool_value', 'flag', $default),
            "boolean normalization #$index"
        );
    }

    $legacy = brainTestCall(
        brainTestPlugin(['password_minimum_length' => 10]),
        'resolve_password_policy'
    );
    brainTestEquals('legacy', $legacy['source'], 'legacy policy selected');
    brainTestEquals(10, $legacy['min_length'], 'legacy minimum length');
    brainTestEquals(true, $legacy['rules']['upper'], 'legacy default uppercase rule');

    $local = brainTestCall(
        brainTestPlugin([
            'password_minimum_length' => 7,
            'px_password_strength_minimum_length' => 12,
            'px_password_strength_require_upper' => false,
            'px_password_strength_forbid_diacritics' => false,
        ]),
        'resolve_password_policy'
    );
    brainTestEquals('strength', $local['source'], 'local policy selected');
    brainTestEquals(12, $local['min_length'], 'local minimum length overrides legacy');
    brainTestEquals(false, $local['rules']['upper'], 'local uppercase rule disabled');
    brainTestEquals(false, $local['rules']['diacritics'], 'local diacritics rule disabled');

    $global = brainTestCall(
        brainTestPlugin([
            'px_password_strength_minimum_length' => 12,
            'px_password_policy_minimum_length' => 16,
            'px_password_policy_require_lower' => 'off',
        ]),
        'resolve_password_policy'
    );
    brainTestEquals('global', $global['source'], 'global policy takes priority');
    brainTestEquals(16, $global['min_length'], 'global minimum length overrides local');
    brainTestEquals(false, $global['rules']['lower'], 'global lowercase rule disabled');
    brainTestEquals(true, $global['rules']['upper'], 'global uppercase rule defaults to enabled');

    $negative = brainTestCall(
        brainTestPlugin(['px_password_policy_minimum_length' => -5]),
        'resolve_password_policy'
    );
    brainTestEquals(0, $negative['min_length'], 'negative minimum length clamped to zero');
    brainTestEquals(false, $negative['rules']['length'], 'zero minimum length disables length rule');

    echo "PASS: all self-contained policy checks passed\n";
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL: " . $error->getMessage() . "\n");
    exit(1);
}
