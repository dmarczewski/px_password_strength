# TODO - px_password_strength

Audyt statyczny: 2026-02-24

## TODO hygiene (po refaktorze / security pass)

- Uruchom `plugins/run_px_smoke_tests.sh` (lub testy `px_password_strength` + cross-plugin) po zmianach w polityce haseł.
- Sprawdź, czy otwarte TODO o precedencji nadal dotyczy aktualnego kontraktu `px_password_policy_*`.
- Uaktualnij sekcję `Zrobione` po zmianach w JS/UI/backend driverze.

## P3 - Średnie

- [ ] **[P3][cross-plugin]** Asymetria policy resolution z `px_password_recovery` — `px_password_strength` fallback: `px_password_strength_*` → `password_minimum_length`; `px_password_recovery` fallback: `px_password_recovery_*` → `px_password_strength_*`. Mogą enforce'ować różne polityki przy tych samych ustawieniach. Ujednolicić precedencję.
  - ⏭ Pominięto: wymaga koordynacji obu pluginów, ryzyko regresji. Shared override (`px_password_policy_*`) rozwiązuje problem dla świadomych adminów.

## Zrobione

- [x] Shared/global override polityki (`px_password_policy_*`)
- [x] Auto-konfiguracja `password_strength_driver`
- [x] Ekspozycja `password_minimum_length` do backendu pluginu `password`
- [x] Labels lokalizowane (weak/medium/strong) eksportowane do JS
- [x] `classList.add()` zamiast innerHTML (bezpieczne)
- [x] Hardcoded allowlist kluczy rule w JS (length/lower/upper/digit/special/diacritics)
- [x] Auto-config `password_minimum_score`: zamiana `!(int)` na `=== null` check (zapobiega nadpisaniu jawnego `0`)
- [x] `cfg_bool_value()` type coercion: guard `!is_scalar($value)` zapobiega cichemu wyłączeniu reguł przez `[]`
- [x] `pl_PL.inc` brak `include en_US.inc` — dodany fallback
- [x] `!empty()` na bool rules — analiza: zachowanie poprawne (`!empty(false)` = `false`)
- [x] Form selector — `#password-form` jest już pierwszym selektorem, greedy fallback jest backup (OK)
- [x] Accessibility — dodano `role=region`, `aria-label`, `aria-live=polite`, `role=progressbar` z `aria-valuenow`
- [x] DOM queries per keystroke — scacheowane elementy reguł przy init
- [x] `config.inc.php.dist` — dodano komentarze o auto-konfiguracji `password_strength_driver` i `password_minimum_score`
