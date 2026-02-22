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

    public function init()
    {
        $this->rc = rcmail::get_instance();
        $this->load_config('config.inc.php.dist');
        $this->load_config();

        if (!$this->cfg_bool('px_password_strength_enabled', true)) {
            return;
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

        $min_length = (int) $this->rc->config->get('password_minimum_length', 8);
        if ($min_length < 0) {
            $min_length = 0;
        }

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
                'length' => $min_length > 0,
                'lower' => $this->cfg_bool('px_password_strength_require_lower', true),
                'upper' => $this->cfg_bool('px_password_strength_require_upper', true),
                'digit' => $this->cfg_bool('px_password_strength_require_digit', true),
                'special' => $this->cfg_bool('px_password_strength_require_special', true),
                'diacritics' => $this->cfg_bool('px_password_strength_forbid_diacritics', true),
            ],
            'labels' => $labels,
        ];

        $this->rc->output->set_env('px_password_strength', $config);

        return $args;
    }

    private function cfg_bool($key, $default = false)
    {
        return (bool) $this->rc->config->get($key, $default);
    }
}
