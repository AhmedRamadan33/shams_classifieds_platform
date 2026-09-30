<?php

declare(strict_types=1);

namespace App\Settings;

use Filament\Support\Icons\Heroicon;

final class SettingsRegistry
{
    public static function tabs(): array
    {
        return [
            'mail' => [
                'icon' => Heroicon::OutlinedEnvelope,
                'fields' => [
                    new SettingField('MAIL_MAILER', 'mail.default', type: 'select', options: ['log', 'smtp']),
                    new SettingField('MAIL_HOST', 'mail.mailers.smtp.host', visibleWhenKey: 'MAIL_MAILER', visibleWhenValue: 'smtp'),
                    new SettingField('MAIL_PORT', 'mail.mailers.smtp.port', type: 'number', visibleWhenKey: 'MAIL_MAILER', visibleWhenValue: 'smtp'),
                    new SettingField('MAIL_USERNAME', 'mail.mailers.smtp.username', visibleWhenKey: 'MAIL_MAILER', visibleWhenValue: 'smtp'),
                    new SettingField('MAIL_PASSWORD', 'mail.mailers.smtp.password', secret: true, visibleWhenKey: 'MAIL_MAILER', visibleWhenValue: 'smtp'),
                    new SettingField('MAIL_FROM_ADDRESS', 'mail.from.address', type: 'email'),
                    new SettingField('MAIL_FROM_NAME', 'mail.from.name'),
                ],
            ],
            'sms' => [
                'icon' => Heroicon::OutlinedChatBubbleLeftRight,
                'fields' => [
                    new SettingField('SMS_DRIVER', 'services.sms.driver', type: 'select', options: ['log', 'twilio', 'vonage']),
                    new SettingField('TWILIO_SID', 'services.twilio.sid', visibleWhenKey: 'SMS_DRIVER', visibleWhenValue: 'twilio'),
                    new SettingField('TWILIO_TOKEN', 'services.twilio.token', secret: true, visibleWhenKey: 'SMS_DRIVER', visibleWhenValue: 'twilio'),
                    new SettingField('TWILIO_FROM', 'services.twilio.from', visibleWhenKey: 'SMS_DRIVER', visibleWhenValue: 'twilio'),
                    new SettingField('TWILIO_MESSAGING_SERVICE_SID', 'services.twilio.messaging_service_sid', visibleWhenKey: 'SMS_DRIVER', visibleWhenValue: 'twilio'),
                    new SettingField('VONAGE_KEY', 'services.vonage.key', visibleWhenKey: 'SMS_DRIVER', visibleWhenValue: 'vonage'),
                    new SettingField('VONAGE_SECRET', 'services.vonage.secret', secret: true, visibleWhenKey: 'SMS_DRIVER', visibleWhenValue: 'vonage'),
                    new SettingField('VONAGE_FROM', 'services.vonage.from', visibleWhenKey: 'SMS_DRIVER', visibleWhenValue: 'vonage'),
                ],
            ],
            'payments' => [
                'icon' => Heroicon::OutlinedCreditCard,
                'fields' => [
                    new SettingField('PAYMENT_DRIVER', 'services.payments.driver', type: 'select', options: ['fake', 'paymob']),
                    new SettingField('PAYMOB_API_KEY', 'services.paymob.api_key', secret: true, visibleWhenKey: 'PAYMENT_DRIVER', visibleWhenValue: 'paymob'),
                    new SettingField('PAYMOB_INTEGRATION_ID', 'services.paymob.integration_id', visibleWhenKey: 'PAYMENT_DRIVER', visibleWhenValue: 'paymob'),
                    new SettingField('PAYMOB_IFRAME_ID', 'services.paymob.iframe_id', visibleWhenKey: 'PAYMENT_DRIVER', visibleWhenValue: 'paymob'),
                    new SettingField('PAYMOB_HMAC_SECRET', 'services.paymob.hmac_secret', secret: true, visibleWhenKey: 'PAYMENT_DRIVER', visibleWhenValue: 'paymob'),
                ],
            ],
            'whatsapp' => [
                'icon' => Heroicon::OutlinedChatBubbleOvalLeft,
                'fields' => [
                    new SettingField('WHATSAPP_DRIVER', 'services.whatsapp.driver', type: 'select', options: ['log', 'cloud']),
                    new SettingField('WHATSAPP_PHONE_NUMBER_ID', 'services.whatsapp.phone_number_id', visibleWhenKey: 'WHATSAPP_DRIVER', visibleWhenValue: 'cloud'),
                    new SettingField('WHATSAPP_ACCESS_TOKEN', 'services.whatsapp.access_token', secret: true, visibleWhenKey: 'WHATSAPP_DRIVER', visibleWhenValue: 'cloud'),
                ],
            ],
            'captcha' => [
                'icon' => Heroicon::OutlinedShieldCheck,
                'fields' => [
                    new SettingField('CAPTCHA_DRIVER', 'services.captcha.driver', type: 'select', options: ['null', 'turnstile']),
                    new SettingField('TURNSTILE_SITE_KEY', 'services.turnstile.site_key', visibleWhenKey: 'CAPTCHA_DRIVER', visibleWhenValue: 'turnstile'),
                    new SettingField('TURNSTILE_SECRET_KEY', 'services.turnstile.secret', secret: true, visibleWhenKey: 'CAPTCHA_DRIVER', visibleWhenValue: 'turnstile'),
                ],
            ],
            'general' => [
                'icon' => Heroicon::OutlinedGlobeAlt,
                'fields' => [
                    new SettingField('CLASSIFIEDS_CONTACT_EMAIL', 'classifieds.contact_email', type: 'email'),
                    new SettingField('BACKUP_NOTIFICATION_EMAIL', 'backup.notifications.mail.to', type: 'email'),
                ],
            ],
        ];
    }

    public static function fields(): array
    {
        return collect(self::tabs())->flatMap(fn (array $tab) => $tab['fields'])->keyBy('key')->all();
    }

    public static function configMap(): array
    {
        return collect(self::fields())->map(fn (SettingField $field) => $field->configPath)->all();
    }
}
