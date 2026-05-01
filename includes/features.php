<?php
// includes/features.php — Feature flag helper (cached per request)
// Usage: feature('wishlist')        → true/false
//        feature_val('free_delivery') → '500'

if (!function_exists('feature')) {
    $_feature_cache = null;

    function _load_features(): void {
        global $_feature_cache, $pdo;
        if ($_feature_cache !== null) return;
        try {
            $rows = $pdo->query("SELECT feature_key, is_enabled, value FROM feature_settings")->fetchAll(PDO::FETCH_ASSOC);
            $_feature_cache = [];
            foreach ($rows as $r) {
                $_feature_cache[$r['feature_key']] = [
                    'enabled' => (bool)$r['is_enabled'],
                    'value'   => $r['value'],
                ];
            }
        } catch (Exception $e) {
            $_feature_cache = [];
        }
    }

    function feature(string $key): bool {
        global $_feature_cache;
        _load_features();
        return $_feature_cache[$key]['enabled'] ?? true; // default ON if not found
    }

    function feature_val(string $key, $default = null): mixed {
        global $_feature_cache;
        _load_features();
        return $_feature_cache[$key]['value'] ?? $default;
    }

    function all_features(): array {
        global $_feature_cache;
        _load_features();
        return $_feature_cache;
    }
}
