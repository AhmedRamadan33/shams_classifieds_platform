<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Governorate;
use App\Models\Page;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Pre-launch checklist: `php artisan launch:check` (add --strict to fail on warnings, --json for tools).
 *
 * "fail" items make the site unsafe or broken in production; "warn" items are strongly recommended.
 */
class LaunchCheckCommand extends Command
{
    protected $signature = 'launch:check {--strict : Exit with an error on warnings too} {--json : Print the results as JSON}';

    protected $description = 'Check that the application is ready for production';

    /** @var list<array{status: string, check: string, detail: string}> */
    private array $results = [];

    public function handle(): int
    {
        // Artisan::call() (used by launchCheck() in tests, and possibly by other code) reuses the
        // same command instance across calls, so this must be reset here rather than relying on the
        // property default, or a second call in the same process would see stale results from the first.
        $this->results = [];

        $this->environment();
        $this->integrations();
        $this->content();
        $this->infrastructure();

        $failed = collect($this->results)->where('status', 'fail')->count();
        $warned = collect($this->results)->where('status', 'warn')->count();

        if ($this->option('json')) {
            $this->line((string) json_encode(['results' => $this->results, 'failed' => $failed, 'warnings' => $warned], JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['', 'Check', 'Detail'], collect($this->results)->map(fn (array $r) => [
                match ($r['status']) {
                    'ok' => '<info>OK</info>',
                    'warn' => '<comment>WARN</comment>',
                    default => '<error>FAIL</error>',
                },
                $r['check'],
                $r['detail'],
            ])->all());

            $this->line("{$failed} failure(s), {$warned} warning(s).");
        }

        return ($failed > 0 || ($this->option('strict') && $warned > 0)) ? self::FAILURE : self::SUCCESS;
    }

    // ------------------------------------------------------------------ groups

    private function environment(): void
    {
        $production = app()->environment('production');

        $this->check('APP_ENV=production', $production, $production ? 'production' : 'currently "'.app()->environment().'"', 'warn');
        $this->check('APP_DEBUG is off', ! config('app.debug'), config('app.debug') ? 'debug pages expose secrets' : 'off', $production ? 'fail' : 'warn');
        $this->check('APP_URL uses HTTPS', str_starts_with((string) config('app.url'), 'https://'), (string) config('app.url'), $production ? 'fail' : 'warn');
        $this->check('Secure session cookie', (bool) config('session.secure'), 'set SESSION_SECURE_COOKIE=true behind HTTPS', 'warn');
        $this->check('Queue is asynchronous', config('queue.default') !== 'sync', 'QUEUE_CONNECTION='.config('queue.default').' (image conversions would run inside the upload request)', 'warn');
        $this->check('Cache store is persistent', config('cache.default') !== 'array', 'CACHE_STORE='.config('cache.default'), 'warn');
    }

    private function integrations(): void
    {
        $production = app()->environment('production');

        $sms = (string) config('services.sms.driver');
        $this->check('Real SMS provider', $sms !== 'log', $sms === 'log' ? 'SMS_DRIVER=log: users cannot receive OTP codes' : "driver: {$sms}", $production ? 'fail' : 'warn');

        if ($sms === 'twilio') {
            $ok = filled(config('services.twilio.sid')) && filled(config('services.twilio.token'))
                && (filled(config('services.twilio.from')) || filled(config('services.twilio.messaging_service_sid')));
            $this->check('Twilio credentials', $ok, $ok ? 'present' : 'TWILIO_SID / TWILIO_TOKEN / TWILIO_FROM missing', 'fail');
        }

        if ($sms === 'vonage') {
            $ok = filled(config('services.vonage.key')) && filled(config('services.vonage.secret'));
            $this->check('Vonage credentials', $ok, $ok ? 'present' : 'VONAGE_KEY / VONAGE_SECRET missing', 'fail');
        }

        $payments = (string) config('services.payments.driver');
        $this->check('Real payment gateway', $payments !== 'fake', $payments === 'fake' ? 'PAYMENT_DRIVER=fake: featured-ad payments are simulated, never charged' : "driver: {$payments}", $production ? 'fail' : 'warn');

        if ($payments === 'paymob') {
            $ok = filled(config('services.paymob.api_key')) && filled(config('services.paymob.integration_id'))
                && filled(config('services.paymob.iframe_id')) && filled(config('services.paymob.hmac_secret'));
            $this->check('Paymob credentials', $ok, $ok ? 'present' : 'PAYMOB_API_KEY / PAYMOB_INTEGRATION_ID / PAYMOB_IFRAME_ID / PAYMOB_HMAC_SECRET missing', 'fail');
        }

        $mailer = (string) config('mail.default');
        $this->check('Real mail transport', ! in_array($mailer, ['log', 'array'], true), "MAIL_MAILER={$mailer} (backup alerts and e-mail notifications need a real transport)", 'warn');

        // WhatsApp notifications are opt-in per user and off by default, so "log" is never a hard
        // failure; only warn, and only check credentials once an admin actually selects "cloud".
        $whatsapp = (string) config('services.whatsapp.driver');
        $this->check('Real WhatsApp provider', $whatsapp !== 'log', $whatsapp === 'log' ? 'WHATSAPP_DRIVER=log: opted-in users get no WhatsApp copy' : "driver: {$whatsapp}", 'warn');

        if ($whatsapp === 'cloud') {
            $ok = filled(config('services.whatsapp.phone_number_id')) && filled(config('services.whatsapp.access_token'));
            $this->check('WhatsApp Cloud API credentials', $ok, $ok ? 'present' : 'WHATSAPP_PHONE_NUMBER_ID / WHATSAPP_ACCESS_TOKEN missing', 'fail');
        }

        $captcha = (string) config('services.captcha.driver');

        if ($captcha === 'turnstile') {
            $ok = filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret'));
            $this->check('Turnstile keys', $ok, $ok ? 'present' : 'TURNSTILE_SITE_KEY / TURNSTILE_SECRET_KEY missing (the widget would not render)', 'fail');
        }

        $this->check('CAPTCHA enabled', $captcha !== 'null', $captcha === 'null' ? 'only the honeypot and rate limits protect the forms' : "driver: {$captcha}", 'warn');
    }

    private function content(): void
    {
        $this->check('Blocked words configured', config('classifieds.blocked_words') !== [], count((array) config('classifieds.blocked_words')).' word(s)', 'warn');

        $email = (string) config('classifieds.contact_email');
        $this->check('Contact e-mail is real', ! str_ends_with($email, 'example.com'), $email, 'warn');

        try {
            $contact = Page::query()->where('slug', 'contact')->value('body');
            $this->check('Contact page has no placeholder', $contact === null || ! str_contains($contact, 'example.com'), $contact === null ? 'page not created yet' : 'edit «اتصل بنا» in the admin panel', 'warn');

            $admins = User::role('admin')->get();
            $this->check('An administrator exists', $admins->isNotEmpty(), $admins->count().' admin(s)', 'fail');
            $weak = $admins->contains(fn (User $admin) => Hash::check('change-me-please', $admin->password));
            $this->check('Admin password changed', ! $weak, $weak ? 'an admin still uses the example password' : 'ok', 'fail');
        } catch (Throwable) {
            $this->check('Administrator check', false, 'database or roles not ready', 'fail');
        }
    }

    private function infrastructure(): void
    {
        try {
            DB::connection()->getPdo();
            $this->check('Database reachable', true, (string) config('database.default'));

            $pending = collect(app('migrator')->getMigrationFiles(app('migrator')->paths()))
                ->keys()
                ->diff(app('migrator')->getRepository()->getRan());
            $this->check('Migrations are up to date', $pending->isEmpty(), $pending->isEmpty() ? 'up to date' : $pending->count().' pending', 'fail');

            $engine = DB::table('information_schema.tables')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'listings')
                ->value('engine');
            $this->check('Tables use InnoDB', $engine === 'InnoDB', (string) ($engine ?? 'listings table missing'), 'fail');

            $this->check('Categories seeded', Schema::hasTable('categories') && Category::query()->exists(), 'run php artisan migrate --seed', 'warn');
            $this->check('Governorates seeded', Schema::hasTable('governorates') && Governorate::query()->exists(), 'run php artisan migrate --seed', 'warn');
        } catch (Throwable $e) {
            $this->check('Database reachable', false, $e->getMessage(), 'fail');
        }

        foreach (['pdo_mysql', 'mbstring', 'intl', 'gd', 'exif', 'fileinfo', 'zip'] as $extension) {
            $this->check("PHP extension {$extension}", extension_loaded($extension), extension_loaded($extension) ? 'loaded' : 'missing', 'fail');
        }

        $webp = extension_loaded('gd') && (gd_info()['WebP Support'] ?? false);
        $this->check('GD has WebP support', (bool) $webp, $webp ? 'yes' : 'image conversions need WebP', 'fail');

        $maxKb = (int) config('classifieds.max_image_kb');
        $needed = $maxKb * (int) config('classifieds.max_images');
        $upload = $this->iniBytes((string) ini_get('upload_max_filesize'));
        $post = $this->iniBytes((string) ini_get('post_max_size'));
        $this->check('PHP upload_max_filesize', $upload === 0 || $upload >= $maxKb * 1024, ini_get('upload_max_filesize').' (needs '.round($maxKb / 1024, 1).'M per image)', 'warn');
        $this->check('PHP post_max_size', $post === 0 || $post >= $needed * 1024, ini_get('post_max_size').' (needs '.round($needed / 1024).'M for a full ad)', 'warn');

        $memory = $this->iniBytes((string) ini_get('memory_limit'));
        $this->check('PHP memory_limit for image processing', $memory === -1 || $memory >= 256 * 1024 * 1024, (string) ini_get('memory_limit').' (256M recommended)', 'warn');

        // file_exists() follows a symlink, or a junction on Windows (where is_link()/is_dir() may say no).
        $this->check('public/storage link', file_exists(public_path('storage')), 'run php artisan storage:link', 'fail');
        $this->check('sitemap.xml generated', file_exists(public_path('sitemap.xml')), 'run php artisan sitemap:generate', 'warn');

        $dump = config('database.connections.mysql.dump.dump_binary_path');
        $found = (new ExecutableFinder)->find('mysqldump', null, array_filter([$dump]));
        $this->check('mysqldump available for backups', $found !== null, $found ?? 'install mysql-client or set DB_DUMP_BINARY_PATH', 'warn');
    }

    // ----------------------------------------------------------------- helpers

    private function check(string $name, bool $passed, string $detail, string $failureLevel = 'fail'): void
    {
        $this->results[] = ['status' => $passed ? 'ok' : $failureLevel, 'check' => $name, 'detail' => $detail];
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return $value === '-1' ? -1 : 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
