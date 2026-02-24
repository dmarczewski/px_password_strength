(function () {
    function q(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function findInsertAnchor(input) {
        if (!input) {
            return null;
        }

        var row = input.closest('tr');
        if (row) {
            return { mode: 'after', node: row };
        }

        var group = input.closest('.form-group, .input-group, p, .row, div');
        if (group) {
            return { mode: 'after', node: group };
        }

        return { mode: 'after', node: input };
    }

    function insertAfter(node, newNode) {
        if (!node || !node.parentNode) {
            return;
        }

        if (node.nextSibling) {
            node.parentNode.insertBefore(newNode, node.nextSibling);
        } else {
            node.parentNode.appendChild(newNode);
        }
    }

    function buildRuleItem(key, label) {
        var li = document.createElement('li');
        li.className = 'px-pwd-rule px-pwd-rule-' + key;
        li.setAttribute('data-rule', key);

        var icon = document.createElement('span');
        icon.className = 'icon';
        icon.setAttribute('aria-hidden', 'true');

        var text = document.createElement('span');
        text.className = 'text';
        text.textContent = label;

        li.appendChild(icon);
        li.appendChild(text);

        return li;
    }

    function scoreLevel(percent) {
        if (percent <= 0) {
            return '';
        }
        if (percent < 50) {
            return 'weak';
        }
        if (percent < 100) {
            return 'medium';
        }
        return 'strong';
    }

    window.rcmail && rcmail.addEventListener('init', function () {
        var cfg = (rcmail.env && rcmail.env.px_password_strength) || null;
        if (!cfg) {
            return;
        }

        var form = q('#password-form') || q('form[action*="password"]');
        var newpass = q('#newpasswd') || q('input[name="_newpasswd"]');
        var confpass = q('#confpasswd') || q('input[name="_confpasswd"]');

        if (!form || !newpass) {
            console.debug('[px_password_strength] Password form or input not found. UI overlay disabled.');
            return;
        }

        form.classList.add('px-pwd-form-active');

        if (cfg.hide_native_rules) {
            qa('#ruleslist').forEach(function (el) {
                el.classList.add('px-pwd-native-rules-hidden');
            });
        }

        if (q('#px-password-strength-panel')) {
            return;
        }

        var labels = cfg.labels || {};
        var rulesCfg = cfg.rules || {};
        var minLength = parseInt(cfg.min_length, 10);
        if (!isFinite(minLength) || minLength < 0) {
            minLength = 0;
        }

        var panel = document.createElement('div');
        panel.id = 'px-password-strength-panel';
        panel.className = 'px-password-strength-panel';
        panel.setAttribute('role', 'region');
        panel.setAttribute('aria-label', labels.title || 'Password requirements');

        var title = document.createElement('div');
        title.className = 'px-pwd-title';
        title.textContent = labels.title || 'Password requirements';
        panel.appendChild(title);

        var meterWrap = document.createElement('div');
        meterWrap.className = 'px-pwd-meter-wrap';

        var meterHead = document.createElement('div');
        meterHead.className = 'px-pwd-meter-head';

        var meterLabel = document.createElement('span');
        meterLabel.className = 'px-pwd-meter-label';
        meterLabel.textContent = labels.strength || 'Strength';
        meterHead.appendChild(meterLabel);

        var meterValue = document.createElement('span');
        meterValue.className = 'px-pwd-meter-value';
        meterValue.id = 'px-pwd-meter-value';
        meterValue.textContent = '';
        meterValue.setAttribute('aria-live', 'polite');
        meterValue.setAttribute('aria-atomic', 'true');
        if (!cfg.show_strength_label) {
            meterValue.style.display = 'none';
        }
        meterHead.appendChild(meterValue);

        meterWrap.appendChild(meterHead);

        var meterTrack = document.createElement('div');
        meterTrack.className = 'px-pwd-meter-track';
        meterTrack.setAttribute('role', 'progressbar');
        meterTrack.setAttribute('aria-valuemin', '0');
        meterTrack.setAttribute('aria-valuemax', '100');
        meterTrack.setAttribute('aria-valuenow', '0');
        meterTrack.setAttribute('aria-label', labels.strength || 'Strength');

        var meterBar = document.createElement('div');
        meterBar.className = 'px-pwd-meter-bar';
        meterBar.id = 'px-pwd-meter-bar';
        meterTrack.appendChild(meterBar);
        meterWrap.appendChild(meterTrack);

        panel.appendChild(meterWrap);

        var rulesList = document.createElement('ul');
        rulesList.className = 'px-pwd-rules-list hint proplist';
        rulesList.id = 'px-pwd-rules-list';

        var activeRules = [];
        if (rulesCfg.length) activeRules.push('length');
        if (rulesCfg.lower) activeRules.push('lower');
        if (rulesCfg.upper) activeRules.push('upper');
        if (rulesCfg.digit) activeRules.push('digit');
        if (rulesCfg.special) activeRules.push('special');
        if (rulesCfg.diacritics) activeRules.push('diacritics');

        activeRules.forEach(function (key) {
            if (labels[key]) {
                rulesList.appendChild(buildRuleItem(key, labels[key]));
            }
        });

        panel.appendChild(rulesList);

        // Cache rule elements for keystroke handler (avoid DOM queries per keystroke)
        var ruleElements = {};
        activeRules.forEach(function (key) {
            ruleElements[key] = q('.px-pwd-rule-' + key, panel);
        });

        var anchor = findInsertAnchor(newpass);
        if (anchor && anchor.node) {
            insertAfter(anchor.node, panel);
        } else {
            form.appendChild(panel);
        }

        var validators = {
            length: function (value) { return value.length >= Math.max(1, minLength); },
            lower: function (value) { return /[a-z]/.test(value); },
            upper: function (value) { return /[A-Z]/.test(value); },
            digit: function (value) { return /[0-9]/.test(value); },
            special: function (value) { return /[^a-zA-Z0-9]/.test(value); },
            diacritics: function (value) {
                if (!value.length) return false;
                // Allow only standard ASCII printables (rejects diacritics, emoji, other alphabets)
                return /^[\x20-\x7E]+$/.test(value);
            }
        };

        function update() {
            var value = String(newpass.value || '');
            var total = 0;
            var passed = 0;

            activeRules.forEach(function (key) {
                var li = ruleElements[key];
                var ok = validators[key] ? !!validators[key](value) : false;

                if (li) {
                    li.classList.toggle('is-passed', ok);
                    li.classList.toggle('is-empty', value.length === 0);
                }

                total += 1;
                if (ok) passed += 1;
            });

            var percent = total > 0 ? Math.round((passed / total) * 100) : 0;
            var level = scoreLevel(percent);

            meterBar.className = 'px-pwd-meter-bar';
            if (level) {
                meterBar.classList.add('is-' + level);
            }
            meterBar.style.width = (value.length === 0 ? 0 : Math.max(percent, 10)) + '%';
            meterTrack.setAttribute('aria-valuenow', String(value.length === 0 ? 0 : percent));

            if (!cfg.show_strength_label) {
                return;
            }

            if (value.length === 0) {
                meterValue.textContent = '';
                meterValue.className = 'px-pwd-meter-value';
                return;
            }

            var levelText = labels[level] || level;
            meterValue.textContent = levelText;
            meterValue.className = 'px-pwd-meter-value' + (level ? ' is-' + level : '');
        }

        function checkMismatch() {
            if (confpass) {
                form.classList.toggle('px-pwd-confirm-mismatch', !!confpass.value && confpass.value !== newpass.value);
            }
        }

        newpass.addEventListener('input', function () {
            update();
            checkMismatch();
        });

        if (confpass) {
            confpass.addEventListener('input', checkMismatch);
        }

        update();
    });
})();
