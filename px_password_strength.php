<?php

/**
 * Visual overlay for Roundcube password plugin.
 *
 * Adds a live password strength bar and checklist on the default password
 * change form in Settings -> Password.
 */
class px_password_strength extends rcube_plugin
{
    public $task = 'settings';

    private $rc;
    private $password_policy;

    public function init()
    {
        $this->rc = rcmail::get_instance();
        $this->load_config('config.inc.php.dist');
        $this->load_config();

        if (!$this->cfg_bool('px_password_strength_enabled', true)) {
            return;
        }

        $this->password_policy = $this->resolve_password_policy();

        // If plugin-local or shared override policy is configured, expose effective min length
        // to the core password plugin backend validation.
        if ($this->has_shared_password_policy_override() || $this->cfg_has('px_password_strength_minimum_length')) {
            $this->rc->config->set('password_minimum_length', (int) $this->password_policy['min_length']);
        }

        // Server-side enforcement: auto-configure password strength driver
        // unless admin explicitly set a different one.
        $current_driver = $this->rc->config->get('password_strength_driver');
        if (!$current_driver || $current_driver === 'px_strength') {
            $this->rc->config->set('password_strength_driver', 'px_strength');
            if ($this->rc->config->get('password_minimum_score', null) === null) {
                $this->rc->config->set('password_minimum_score', 2);
            }
        }

        $this->add_hook('render_page', [$this, 'render_page']);
    }

    public function render_page($args)
    {
        $action = (string) $this->rc->action;
        $template = isset($args['template']) ? (string) $args['template'] : '';

        if ($template !== 'plugin' || ($action !== 'plugin.password' && $action !== 'plugin.password-save')) {
            return $args;
        }

        $this->add_texts('localization/');
        $this->include_stylesheet('css/px_password_strength.css');
        $this->include_script('js/px_password_strength.js');

        $policy = is_array($this->password_policy) ? $this->password_policy : $this->resolve_password_policy();
        $rules = !empty($policy['rules']) && is_array($policy['rules']) ? $policy['rules'] : [];
        $min_length = isset($policy['min_length']) ? (int) $policy['min_length'] : 8;

        $labels = [
            'title' => (string) $this->gettext('passwordrequirements'),
            'strength' => (string) $this->gettext('passwordstrength'),
            'weak' => (string) $this->gettext('strengthweak'),
            'medium' => (string) $this->gettext('strengthmedium'),
            'strong' => (string) $this->gettext('strengthstrong'),
            'length' => (string) $this->gettext([
                'name' => 'passwordrulelength',
                'vars' => ['length' => max(1, $min_length)],
            ]),
            'lower' => (string) $this->gettext('passwordrulelower'),
            'upper' => (string) $this->gettext('passwordruleupper'),
            'digit' => (string) $this->gettext('passwordruledigit'),
            'special' => (string) $this->gettext('passwordrulespecial'),
            'diacritics' => (string) $this->gettext('passwordrulediacritics'),
        ];

        $config = [
            'min_length' => $min_length,
            'hide_native_rules' => $this->cfg_bool('px_password_strength_hide_native_rules', true),
            'show_strength_label' => $this->cfg_bool('px_password_strength_show_strength_label', true),
            'rules' => [
                'length' => !empty($rules['length']),
                'lower' => !empty($rules['lower']),
                'upper' => !empty($rules['upper']),
                'digit' => !empty($rules['digit']),
                'special' => !empty($rules['special']),
                'diacritics' => !empty($rules['diacritics']),
            ],
            'labels' => $labels,
        ];

        $this->rc->output->set_env('px_password_strength', $config);

        return $args;
    }

    private function cfg_bool($key, $default = false)
    {
        return $this->cfg_bool_value($key, $default);
    }

    private function resolve_password_policy()
    {
        if ($this->has_shared_password_policy_override()) {
            $min_len = $this->cfg_int('px_password_policy_minimum_length', 8);

            return [
                'source' => 'global',
                'min_length' => $min_len,
                'rules' => [
                    'length' => $min_len > 0,
                    'lower' => $this->cfg_bool_value('px_password_policy_require_lower', true),
                    'upper' => $this->cfg_bool_value('px_password_policy_require_upper', true),
                    'digit' => $this->cfg_bool_value('px_password_policy_require_digit', true),
                    'special' => $this->cfg_bool_value('px_password_policy_require_special', true),
                    'diacritics' => $this->cfg_bool_value('px_password_policy_forbid_diacritics', true),
                ],
            ];
        }

        $min_len = $this->cfg_has('px_password_strength_minimum_length')
            ? $this->cfg_int('px_password_strength_minimum_length', 8)
            : $this->cfg_int('password_minimum_length', 8);

        return [
            'source' => $this->cfg_has('px_password_strength_minimum_length') ? 'strength' : 'legacy',
            'min_length' => $min_len,
            'rules' => [
                'length' => $min_len > 0,
                'lower' => $this->cfg_bool_value('px_password_strength_require_lower', true),
                'upper' => $this->cfg_bool_value('px_password_strength_require_upper', true),
                'digit' => $this->cfg_bool_value('px_password_strength_require_digit', true),
                'special' => $this->cfg_bool_value('px_password_strength_require_special', true),
                'diacritics' => $this->cfg_bool_value('px_password_strength_forbid_diacritics', true),
            ],
        ];
    }

    private function has_shared_password_policy_override()
    {
        return $this->cfg_has('px_password_policy_minimum_length')
            || $this->cfg_has('px_password_policy_require_lower')
            || $this->cfg_has('px_password_policy_require_upper')
            || $this->cfg_has('px_password_policy_require_digit')
            || $this->cfg_has('px_password_policy_require_special')
            || $this->cfg_has('px_password_policy_forbid_diacritics');
    }

    private function cfg_has($key)
    {
        return $this->rc->config->get($key, null) !== null;
    }

    private function cfg_int($key, $default = 0)
    {
        $value = (int) $this->rc->config->get($key, $default);
        return $value < 0 ? 0 : $value;
    }

    private function cfg_bool_value($key, $default = false)
    {
        $value = $this->rc->config->get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return (bool) $default;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['0', 'false', 'off', 'no', ''], true)) {
                return false;
            }
            if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
                return true;
            }
        }

        return (bool) $value;
    }
}
