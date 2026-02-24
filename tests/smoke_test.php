<?php

require_once __DIR__ . '/../../_test_helpers/roundcube_smoke_stubs.php';
require_once __DIR__ . '/../px_password_strength.php';

smoke_note('Running px_password_strength smoke test...');

// Scenario 1: default wiring + localized labels.
$rc = smoke_reset_runtime([
    '_task' => 'settings',
    '_action' => 'plugin.password',
    'language' => 'de_DE',
    'px_password_strength_enabled' => true,
]);

$plugin = new px_password_strength();
$plugin->init();

smoke_assert_true(smoke_plugin_has_hook($plugin, 'render_page'), 'render_page hook registered');
smoke_assert_same('px_strength', $rc->config->get('password_strength_driver'), 'password strength driver auto-configured');
smoke_assert_same(2, (int) $rc->config->get('password_minimum_score'), 'minimum score default set');

$args = $plugin->render_page(['template' => 'plugin']);
smoke_assert_true(is_array($args), 'render_page returns args array');

$env = $rc->output->env['px_password_strength'] ?? null;
smoke_assert_true(is_array($env), 'render_page exports px_password_strength env');
smoke_assert_same(8, (int) ($env['min_length'] ?? 0), 'default minimum length exported');
smoke_assert_same('Passwortanforderungen', $env['labels']['title'] ?? null, 'localized label loaded from de_DE');
smoke_assert_same(true, (bool) ($env['rules']['lower'] ?? false), 'lowercase rule enabled by default');
smoke_assert_true(in_array('css/px_password_strength.css', $plugin->__smoke_styles, true), 'strength stylesheet included');
smoke_assert_true(in_array('js/px_password_strength.js', $plugin->__smoke_scripts, true), 'strength script included');

// Scenario 2: shared/global policy override propagates to core config and UI env.
$rc2 = smoke_reset_runtime([
    '_task' => 'settings',
    '_action' => 'plugin.password',
    'language' => 'en_US',
    'px_password_strength_enabled' => true,
    'px_password_policy_minimum_length' => 12,
    'px_password_policy_require_lower' => false,
    'px_password_policy_require_upper' => true,
    'px_password_policy_require_digit' => true,
    'px_password_policy_require_special' => false,
    'px_password_policy_forbid_diacritics' => true,
]);

$plugin2 = new px_password_strength();
$plugin2->init();
$plugin2->render_page(['template' => 'plugin']);

$env2 = $rc2->output->env['px_password_strength'] ?? null;
smoke_assert_true(is_array($env2), 'env exported with shared policy override');
smoke_assert_same(12, (int) $rc2->config->get('password_minimum_length'), 'shared min length propagated to core password config');
smoke_assert_same(12, (int) ($env2['min_length'] ?? 0), 'shared min length exported to UI');
smoke_assert_same(false, (bool) ($env2['rules']['lower'] ?? true), 'shared lower rule override exported');
smoke_assert_same(false, (bool) ($env2['rules']['special'] ?? true), 'shared special rule override exported');
smoke_assert_contains('12', (string) ($env2['labels']['length'] ?? ''), 'length label interpolates shared minimum');

// Scenario 3: unrelated template/action does not inject env.
$rc3 = smoke_reset_runtime([
    '_task' => 'settings',
    '_action' => 'preferences',
    'px_password_strength_enabled' => true,
]);
$plugin3 = new px_password_strength();
$plugin3->init();
$result3 = $plugin3->render_page(['template' => 'mail']);
smoke_assert_same(['template' => 'mail'], $result3, 'render_page leaves unrelated templates unchanged');
smoke_assert_true(empty($rc3->output->env['px_password_strength']), 'no env injected for unrelated template');

smoke_note('PASS: px_password_strength smoke test');
exit(0);
