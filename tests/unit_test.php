<?php

require_once __DIR__ . '/../../_test_helpers/roundcube_smoke_stubs.php';
require_once __DIR__ . '/../px_password_strength.php';

smoke_note('Running px_password_strength unit test...');

function px_strength_boot(array $config = [])
{
    $defaults = [
        '_task' => 'settings',
        '_action' => 'plugin.password',
        'language' => 'en_US',
        'px_password_strength_enabled' => true,
    ];

    $rc = smoke_reset_runtime(array_merge($defaults, $config));
    $plugin = new px_password_strength();
    $plugin->init();

    return [$plugin, $rc];
}

function px_strength_call_cfg_bool_value($plugin, $key, $default)
{
    $ref = new ReflectionMethod('px_password_strength', 'cfg_bool_value');
    $ref->setAccessible(true);

    return $ref->invoke($plugin, $key, $default);
}

// cfg_bool_value(): string truthy/falsy normalization
list($plugin_cfg, $rc_cfg) = px_strength_boot();

$cases = [
    ['value' => 'true', 'default' => false, 'expected' => true],
    ['value' => ' TRUE ', 'default' => false, 'expected' => true],
    ['value' => 'on', 'default' => false, 'expected' => true],
    ['value' => 'yes', 'default' => false, 'expected' => true],
    ['value' => '1', 'default' => false, 'expected' => true],
    ['value' => 'false', 'default' => true, 'expected' => false],
    ['value' => ' OFF ', 'default' => true, 'expected' => false],
    ['value' => 'no', 'default' => true, 'expected' => false],
    ['value' => '0', 'default' => true, 'expected' => false],
    ['value' => '', 'default' => true, 'expected' => false],
];

foreach ($cases as $idx => $case) {
    $key = 'px_strength_test_bool_' . $idx;
    $rc_cfg->config->set($key, $case['value']);
    $actual = px_strength_call_cfg_bool_value($plugin_cfg, $key, $case['default']);
    smoke_assert_same($case['expected'], $actual, 'cfg_bool_value string normalization case ' . $idx);
}

// cfg_bool_value(): non-scalar and explicit null use default
$rc_cfg->config->set('px_strength_non_scalar_array', []);
smoke_assert_same(true, px_strength_call_cfg_bool_value($plugin_cfg, 'px_strength_non_scalar_array', true), 'cfg_bool_value returns default for array when default=true');
smoke_assert_same(false, px_strength_call_cfg_bool_value($plugin_cfg, 'px_strength_non_scalar_array', false), 'cfg_bool_value returns default for array when default=false');

$rc_cfg->config->set('px_strength_non_scalar_object', (object) ['x' => 1]);
smoke_assert_same(true, px_strength_call_cfg_bool_value($plugin_cfg, 'px_strength_non_scalar_object', true), 'cfg_bool_value returns default for object');

$rc_cfg->config->set('px_strength_null', null);
smoke_assert_same(false, px_strength_call_cfg_bool_value($plugin_cfg, 'px_strength_null', false), 'cfg_bool_value returns default for explicit null=false');
smoke_assert_same(true, px_strength_call_cfg_bool_value($plugin_cfg, 'px_strength_null', true), 'cfg_bool_value returns default for explicit null=true');

// cfg_bool_value(): scalar non-string fallback to bool cast
$rc_cfg->config->set('px_strength_int_two', 2);
$rc_cfg->config->set('px_strength_int_minus_one', -1);
$rc_cfg->config->set('px_strength_int_zero', 0);
smoke_assert_same(true, px_strength_call_cfg_bool_value($plugin_cfg, 'px_strength_int_two', false), 'cfg_bool_value casts int 2 to true');
smoke_assert_same(true, px_strength_call_cfg_bool_value($plugin_cfg, 'px_strength_int_minus_one', false), 'cfg_bool_value casts int -1 to true');
smoke_assert_same(false, px_strength_call_cfg_bool_value($plugin_cfg, 'px_strength_int_zero', true), 'cfg_bool_value casts int 0 to false');

// init(): auto-configures driver when unset/empty and sets minimum_score only when null
list($plugin_auto_empty, $rc_auto_empty) = px_strength_boot([
    'password_strength_driver' => '',
]);
smoke_assert_same('px_strength', $rc_auto_empty->config->get('password_strength_driver'), 'init auto-configures driver when empty string');
smoke_assert_same(2, (int) $rc_auto_empty->config->get('password_minimum_score'), 'init sets minimum score default only when null');
smoke_assert_true(smoke_plugin_has_hook($plugin_auto_empty, 'render_page'), 'enabled plugin registers render_page hook');

// init(): custom driver is preserved
list($plugin_custom, $rc_custom) = px_strength_boot([
    'password_strength_driver' => 'custom_driver',
    'password_minimum_score' => null,
]);
smoke_assert_same('custom_driver', $rc_custom->config->get('password_strength_driver'), 'init does not overwrite custom password_strength_driver');
smoke_assert_same(null, $rc_custom->config->get('password_minimum_score', null), 'init does not set minimum_score when custom driver is used');

// init(): explicit minimum_score=0 is preserved when auto-config path runs
list($plugin_zero, $rc_zero) = px_strength_boot([
    'password_strength_driver' => 'px_strength',
    'password_minimum_score' => 0,
]);
smoke_assert_same('px_strength', $rc_zero->config->get('password_strength_driver'), 'init keeps px_strength driver');
smoke_assert_same(0, (int) $rc_zero->config->get('password_minimum_score'), 'init preserves explicit minimum_score=0');

// init(): disabled plugin does not register hooks and does not auto-configure
$rc_disabled = smoke_reset_runtime([
    '_task' => 'settings',
    '_action' => 'plugin.password',
    'px_password_strength_enabled' => false,
]);
$plugin_disabled = new px_password_strength();
$plugin_disabled->init();
smoke_assert_true(!smoke_plugin_has_hook($plugin_disabled, 'render_page'), 'disabled plugin does not register render_page hook');
smoke_assert_same(null, $rc_disabled->config->get('password_strength_driver', null), 'disabled plugin does not auto-configure password_strength_driver');
smoke_assert_same(null, $rc_disabled->config->get('password_minimum_score', null), 'disabled plugin does not auto-configure password_minimum_score');

smoke_note('PASS: px_password_strength unit test');
exit(0);
