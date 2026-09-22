<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingApproved;
use App\Notifications\NewMessageReceived;
use App\Services\WhatsApp\WhatsAppCloudApiGateway;
use App\Services\WhatsApp\WhatsAppDeliveryException;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

// ---------------------------------------------------------- channel selection

it('only creates an in-app notification by default', function () {
    $user = User::factory()->create();

    $user->notify(new ListingApproved(Listing::factory()->create(['user_id' => $user->id])));

    expect($user->notifications()->count())->toBe(1);
});

it('also sends an e-mail when the user opted in and has an address', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'user@example.com', 'notify_email' => true]);
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    $user->notify(new ListingApproved($listing));

    Notification::assertSentTo($user, ListingApproved::class, fn ($notification, $channels) => in_array('mail', $channels, true));
});

it('does not send an e-mail when opted in but no address is on file', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => null, 'notify_email' => true]);
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    $user->notify(new ListingApproved($listing));

    Notification::assertSentTo($user, ListingApproved::class, fn ($notification, $channels) => ! in_array('mail', $channels, true));
});

it('calls the WhatsApp channel only when opted in, and never when not', function () {
    $optedIn = User::factory()->create(['phone' => '+201012345678', 'notify_whatsapp' => true]);
    $optedOut = User::factory()->create(['phone' => '+201098765432', 'notify_whatsapp' => false]);
    $listing = Listing::factory()->create(['user_id' => $optedIn->id]);

    $calls = [];
    app()->instance(WhatsAppGateway::class, new class($calls) implements WhatsAppGateway
    {
        public function __construct(private array &$calls) {}

        public function send(string $phone, string $message): void
        {
            $this->calls[] = $phone;
        }
    });

    $optedIn->notify(new ListingApproved($listing));
    $optedOut->notify(new ListingApproved(Listing::factory()->create(['user_id' => $optedOut->id])));

    // The instance captured $calls by reference at binding time; read it back from the container.
    $gateway = app(WhatsAppGateway::class);
    $reflection = new ReflectionProperty($gateway, 'calls');

    expect($reflection->getValue($gateway))->toBe(['+201012345678']);
});

it('swallows a WhatsApp delivery failure instead of breaking the request', function () {
    $user = User::factory()->create(['phone' => '+201012345678', 'notify_whatsapp' => true]);
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    app()->instance(WhatsAppGateway::class, new class implements WhatsAppGateway
    {
        public function send(string $phone, string $message): void
        {
            throw WhatsAppDeliveryException::provider('test', 'down');
        }
    });

    $user->notify(new ListingApproved($listing)); // must not throw

    expect($user->notifications()->count())->toBe(1);
});

// -------------------------------------------------------------- mail content

it('renders the approval e-mail in Arabic with an RTL layout', function () {
    $user = User::factory()->create(['email' => 'user@example.com', 'notify_email' => true, 'name' => 'أحمد']);
    $listing = Listing::factory()->titled('شقة للبيع بالتجمع')->create(['user_id' => $user->id]);

    $mail = (new ListingApproved($listing))->toMail($user);
    $html = (string) $mail->render();

    expect($html)->toContain('dir="rtl"')
        ->and($html)->toContain('أحمد')
        ->and($html)->toContain('شقة للبيع بالتجمع');
});

it('renders the new-message e-mail with the message body', function () {
    $seller = User::factory()->create(['email' => 'seller@example.com', 'notify_email' => true]);
    $buyer = User::factory()->create(['name' => 'مشتري مهتم']);
    $listing = Listing::factory()->titled('سيارة تويوتا')->create(['user_id' => $seller->id]);
    $conversation = Conversation::create(['listing_id' => $listing->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
    $message = $conversation->messages()->create(['sender_id' => $buyer->id, 'body' => 'هل السيارة متاحة؟']);

    $html = (string) (new NewMessageReceived($message))->toMail($seller)->render();

    expect($html)->toContain('dir="rtl"')
        ->and($html)->toContain('مشتري مهتم')
        ->and($html)->toContain('هل السيارة متاحة؟');
});

// ---------------------------------------------------------- WhatsApp Cloud API

it('sends a WhatsApp Cloud API text message with the access token', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

    (new WhatsAppCloudApiGateway('12345', 'token-abc'))->send('+201012345678', 'رسالة تجريبية');

    Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.facebook.com/v21.0/12345/messages'
        && $r->hasHeader('Authorization', 'Bearer token-abc')
        && $r['to'] === '201012345678'
        && $r['type'] === 'text'
        && $r['text']['body'] === 'رسالة تجريبية');
});

it('throws when the WhatsApp Cloud API rejects the message', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid phone number']], 400)]);

    (new WhatsAppCloudApiGateway('12345', 'token'))->send('+201012345678', 'x');
})->throws(WhatsAppDeliveryException::class, 'Invalid phone number');

it('selects the WhatsApp driver from configuration', function () {
    config(['services.whatsapp.driver' => 'cloud', 'services.whatsapp.phone_number_id' => '1', 'services.whatsapp.access_token' => 't']);
    app()->forgetInstance(WhatsAppGateway::class);

    expect(app(WhatsAppGateway::class))->toBeInstanceOf(WhatsAppCloudApiGateway::class);
});

it('escapes hostile content in notification e-mails', function () {
    $hostile = '<img src=x onerror=alert(1)>"\'';
    $user = User::factory()->create(['email' => 'user@example.com', 'notify_email' => true]);
    $listing = Listing::factory()->titled($hostile)->create(['user_id' => $user->id]);

    $html = (string) (new ListingApproved($listing))->toMail($user)->render();

    expect($html)->not->toContain('<img src=x onerror=alert(1)>');
});
