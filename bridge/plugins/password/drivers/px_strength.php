<?php

/**
 * px_password_strength server-side enforcement driver.
 *
 * Bridge copy stored in px_password_strength module with full target path
 * structure. Deploy this file to:
 *   plugins/password/drivers/px_strength.php
 *
 * Reads the same config keys as px_password_strength's JS checklist
 * and enforces them on the server before the password is saved.
 *
 * Usage:
 *   $config['password_strength_driver'] = 'px_strength';
 *   $config['password_minimum_score']   = 2;
 *
 * (px_password_strength plugin sets these automatically.)
 */
class rcube_px_strength_password
{
    /**
     * Check password against configured composition rules.
     *
     * @param string $passwd The new password to validate.
     *
     * @return array [int $score, string|null $reason]
     *               score 1 = fail, score 3 = pass
     */
    function check_strength($passwd)
    {
        $rc = rcmail::get_instance();
        $errors = [];

        if ($this->cfg_bool($rc, 'px_password_policy_require_lower', 'px_password_strength_require_lower', true)) {
            if (!preg_match('/[a-z]/', $passwd)) {
                $errors[] = $this->label($rc, 'passwordrulelower', 'must contain a lowercase letter');
            }
        }

        if ($this->cfg_bool($rc, 'px_password_policy_require_upper', 'px_password_strength_require_upper', true)) {
            if (!preg_match('/[A-Z]/', $passwd)) {
                $errors[] = $this->label($rc, 'passwordruleupper', 'must contain an uppercase letter');
            }
        }

        if ($this->cfg_bool($rc, 'px_password_policy_require_digit', 'px_password_strength_require_digit', true)) {
            if (!preg_match('/[0-9]/', $passwd)) {
                $errors[] = $this->label($rc, 'passwordruledigit', 'must contain a number');
            }
        }

        if ($this->cfg_bool($rc, 'px_password_policy_require_special', 'px_password_strength_require_special', true)) {
            if (!preg_match('/[^a-zA-Z0-9]/', $passwd)) {
                $errors[] = $this->label($rc, 'passwordrulespecial', 'must contain a special character');
            }
        }

        if ($this->cfg_bool($rc, 'px_password_policy_forbid_diacritics', 'px_password_strength_forbid_diacritics', true)) {
            // Match characters outside basic ASCII printable range (0x20-0x7E)
            if (preg_match('/[^\x20-\x7E]/', $passwd)) {
                $errors[] = $this->label($rc, 'passwordrulediacritics', 'must use only standard characters');
            }
        }

        if (!empty($errors)) {
            return [1, implode('; ', $errors)];
        }

        return [3, null];
    }

    /**
     * Attempt to load a localized label from px_password_strength plugin.
     */
    private function label($rc, $name, $fallback)
    {
        try {
            $text = $rc->gettext("px_password_strength.$name");
            if ($text && $text !== "[$name]" && $text !== $name) {
                return $text;
            }
        } catch (\Exception $e) {
            // ignore
        }

        return $fallback;
    }

    private function cfg_bool($rc, $global_key, $local_key, $default = false)
    {
        if ($this->has_global_policy_override($rc)) {
            $global = $rc->config->get($global_key, $default);
            return $this->to_bool($global, $default);
        }

        return $this->to_bool($rc->config->get($local_key, $default), $default);
    }

    private function has_global_policy_override($rc)
    {
        return $rc->config->get('px_password_policy_minimum_length', null) !== null
            || $rc->config->get('px_password_policy_require_lower', null) !== null
            || $rc->config->get('px_password_policy_require_upper', null) !== null
            || $rc->config->get('px_password_policy_require_digit', null) !== null
            || $rc->config->get('px_password_policy_require_special', null) !== null
            || $rc->config->get('px_password_policy_forbid_diacritics', null) !== null;
    }

    private function to_bool($value, $default = false)
    {
        if (is_bool($value)) {
            return $value;
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

        if ($value === null) {
            return (bool) $default;
        }

        return (bool) $value;
    }
}
