<?php
namespace ENZO\License;

defined('ABSPATH') || exit;

/**
 * License activation / verification (Phases 11 & 15).
 * D-10: Fail-Open with grace period.
 * Phase 15 adds: domain binding (informational — a mismatch never breaks
 * the customer site) and the data the Updater needs.
 */
class LicenseManager
{
    private const OPTION_KEY = 'enzo_license';
    private const GRACE_DAYS = 30;
    private const DEFAULT_VERIFY_URL = 'https://rtl-theme.com/verify-license.php';
    /** Phase 15: update/version check endpoint consumed by Updater. */
    public const DEFAULT_UPDATE_CHECK_URL = 'https://rtl-theme.com/update-check.php';
    public const PRODUCT = 'enzo';

    /** Update-check endpoint — overridable for self-hosted license servers. */
    public static function updateCheckUrl(): string
    {
        return (string) apply_filters('enzo_license_update_check_url', self::DEFAULT_UPDATE_CHECK_URL);
    }

    /** Verification endpoint — overridable for self-hosted license servers. */
    public static function verifyUrl(): string
    {
        return (string) apply_filters('enzo_license_verify_url', self::DEFAULT_VERIFY_URL);
    }

    /** Maximum domain change attempts before enforcing domain binding. */
    private const MAX_DOMAIN_CHANGES = 5;

    /** Track domain change attempts in the stored license data. */
    public static function trackDomainChange(): void
    {
        $lic = get_option(self::OPTION_KEY, []);
        if (!is_array($lic)) {
            $lic = [];
        }

        $lic['domain_changes'] = ($lic['domain_changes'] ?? 0) + 1;
        update_option(self::OPTION_KEY, $lic, false);
    }

    /** Check if domain change limit has been exceeded. */
    public static function domainChangeLimitExceeded(): bool
    {
        $lic = get_option(self::OPTION_KEY, []);
        if (!is_array($lic)) {
            return false;
        }

        return ($lic['domain_changes'] ?? 0) >= self::MAX_DOMAIN_CHANGES;
    }

    /** Reset domain change counter (for testing or manual intervention). */
    public static function resetDomainChanges(): void
    {
        $lic = get_option(self::OPTION_KEY, []);
        if (!is_array($lic)) {
            return;
        }

        unset($lic['domain_changes']);
        update_option(self::OPTION_KEY, $lic, false);
    }

    /** @return array<string,mixed> */
    public static function status(): array
    {
        $lic = get_option(self::OPTION_KEY, []);
        if (!is_array($lic)) {
            $lic = [];
        }

        $valid = !empty($lic['key']) && !empty($lic['expires']);
        $expired = $valid && (time() > strtotime($lic['expires']));
        $grace = $valid && !$expired && (time() > strtotime('-' . self::GRACE_DAYS . ' days', strtotime($lic['expires'])));
        $domain = (string) ($lic['domain'] ?? '');

        return [
            'activated'    => (bool) ($lic['activated'] ?? false),
            'valid'        => $valid && !$expired,
            'expired'      => $expired,
            'grace'        => $grace,
            'key'          => $lic['key'] ?? '',
            'expires'      => $lic['expires'] ?? '',
            'product'      => $lic['product'] ?? self::PRODUCT,
            // Phase 15 — domain binding. Unbound licenses always match
            // (fail-safe); a mismatch is reported, never enforced.
            'domain'       => $domain,
            'domain_match' => $domain === '' || strcasecmp($domain, self::currentDomain()) === 0,
        ];
    }

    /** Host of the current site (lowercased, no www). */
    public static function currentDomain(): string
    {
        $host = strtolower((string) parse_url(home_url(), PHP_URL_HOST));
        return preg_replace('/^www\./', '', $host) ?: '';
    }

    /**
     * D-10 fail-open gate: features stay available unless a license was
     * activated and has fully expired. Not-activated installs fail open;
     * expired installs are cut off.
     */
    public static function failOpen(): bool
    {
        $s = self::status();
        return !$s['activated'] || $s['valid'];
    }

    /**
     * Verify $key against the license server and store the result.
     * The site domain is sent for binding; the server-confirmed domain
     * (or the current one when the server stays silent) is stored.
     * @return array{success:bool, message:string}
     */
    public static function activate(string $key): array
    {
        $key = trim($key);
        if ($key === '') {
            return ['success' => false, 'message' => 'کلید لایسنس نمی‌تواند خالی باشد.'];
        }

        try {
            $http = \ENZO\Core\Container::instance()->make(\ENZO\Contracts\HttpClientInterface::class);
            $resp = $http->request(self::verifyUrl(), [
                'method'  => 'POST',
                'body'    => [
                    'license_key' => $key,
                    'product'     => self::PRODUCT,
                    'domain'      => self::currentDomain(),
                    'version'     => defined('ENZO_VERSION') ? ENZO_VERSION : '',
                ],
                'timeout' => 15,
            ]);
        } catch (\Throwable) {
            return ['success' => false, 'message' => 'خطا در اتصال به سرور لایسنس.'];
        }

        if (($resp['status'] ?? 0) !== 200) {
            return ['success' => false, 'message' => 'خطا در اتصال به سرور لایسنس.'];
        }

        $data = json_decode((string) ($resp['body'] ?? ''), true);
        if (!is_array($data) || empty($data['valid'])) {
            $extra = isset($data['message']) ? ' (' . $data['message'] . ')' : '';
            return ['success' => false, 'message' => 'کلید لایسنس نامعتبر است.' . $extra];
        }

        $savedDomain = (string) ($data['domain'] ?? self::currentDomain());
        $stored = [
            'key'       => $key,
            'activated' => true,
            'expires'   => (string) ($data['expires'] ?? ''),
            'product'   => (string) ($data['product'] ?? self::PRODUCT),
            'domain'    => $savedDomain,
            'domain_changes' => 0,
        ];

        // Track domain change attempts: each activation counts as a domain change attempt
        $lic = get_option(self::OPTION_KEY, []);
        if (is_array($lic) && isset($lic['domain']) && $lic['domain'] !== $savedDomain) {
            $stored['domain_changes'] = ($lic['domain_changes'] ?? 0) + 1;
        }

        update_option(self::OPTION_KEY, $stored, false);

        return ['success' => true, 'message' => 'لایسنس فعال شد.'];
    }

    /** Remove the stored license entirely. @return array{success:bool, message:string} */
    public static function deactivate(): array
    {
        delete_option(self::OPTION_KEY);
        return ['success' => true, 'message' => 'لایسنس غیرفعال شد.'];
    }

    /** Register the License submenu under the ENZO parent menu. */
    public static function registerMenu(): void
    {
        add_submenu_page(
            'enzo-telegram-ai-admin',
            'لایسنس ENZO',
            'لایسنس',
            'manage_options',
            'enzo-license',
            [self::class, 'adminPage']
        );
    }

    /** Admin page for license management. */
    public static function adminPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $message = '';
        $error = false;
        if (isset($_POST['enzo_license_activate']) || isset($_POST['enzo_license_deactivate'])) {
            check_admin_referer('enzo_license_action', 'enzo_license_nonce');
            if (isset($_POST['enzo_license_activate'])) {
                $key = isset($_POST['enzo_license_key']) ? sanitize_text_field(wp_unslash((string) $_POST['enzo_license_key'])) : '';
                $result = self::activate($key);
                $message = $result['message'];
                $error = !$result['success'];
            } else {
                self::deactivate();
                $message = 'لایسنس غیرفعال شد.';
            }
        }

        $status = self::status();
        ?>
        <div class="wrap">
            <h2>لایسنس ENZO: AI Telegram Admin</h2>
            <?php if ($message): ?>
                <div class="notice notice-<?php echo $error ? 'error' : 'success'; ?>"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>
            <form method="post" action="">
                <?php wp_nonce_field('enzo_license_action', 'enzo_license_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="enzo_license_key">کلید لایسنس</label></th>
                        <td><input type="text" name="enzo_license_key" id="enzo_license_key" class="large-text" value="<?php echo esc_attr($status['key']); ?>" required></td>
                    </tr>
                </table>
                <?php if (!$status['activated']): ?>
                    <p class="submit"><input type="submit" name="enzo_license_activate" class="button button-primary" value="فعال کردن"></p>
                <?php else: ?>
                    <p class="submit"><input type="submit" name="enzo_license_deactivate" class="button" value="حذف لایسنس"></p>
                <?php endif; ?>
            </form>
            <p><strong>وضعیت:</strong> <?php echo $status['activated'] && $status['valid'] ? 'فعال' : ($status['expired'] ? 'منقضی' : 'غیرفعال'); ?></p>
            <p><strong>انقضا:</strong> <?php echo esc_html($status['expires'] ?: 'بدون تاریخ'); ?></p>
            <p><strong>دامنه:</strong> <?php echo esc_html($status['domain'] ?: '—'); ?>
                <?php if ($status['domain'] !== '' && !$status['domain_match']): ?>
                    <span style="color:#b32d2e;">(با دامنه فعلی <?php echo esc_html(self::currentDomain()); ?> مطابقت ندارد)</span>
                <?php endif; ?></p>
        </div>
        <?php
    }

    /** Check status and show admin notice if needed. */
    public static function maybeCheckStatus(): void
    {
        $s = self::status();
        if (!$s['activated'] && !$s['valid']) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-warning is-dismissible"><p>لایسنس فعال نشده است. از بخش "لایسنس" فعال کنید.</p></div>';
            });
        }
    }
}
