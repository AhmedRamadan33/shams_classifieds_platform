<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Support\Facades\DB;

final class DemoConversations
{
    private const GOODS = [
        'السلام عليكم، الإعلان "{title}" لسه متاح؟',
        'وعليكم السلام، أيوه متاح والحمد لله.',
        'ممكن أعرف السبب في البيع وحالته بالظبط؟',
        'استعمال نضيف وبحالة ممتازة، وكل حاجة شغالة زي ما هي في الصور.',
        'طيب آخر سعر كام؟ لو ينفع {price}.',
        'السعر النهائي قريب من كده، ممكن نتفق لو هتستلم بنفسك.',
        'تمام، أقدر أعاين النهاردة بعد الساعة 6؟',
        'أهلاً بيك، كلمني قبلها بساعة عشان أكون متواجد.',
    ];

    private const VEHICLE = [
        'مساء الخير، "{title}" لسه موجودة؟',
        'مساء النور، أيوه موجودة ومتاحة للمعاينة.',
        'الأوراق كلها سليمة والرخصة سارية؟',
        'كل الأوراق سليمة ومتاحة، والرخصة سارية لآخر السنة.',
        'ممكن أعاينها عند مهندس أو ميكانيكي من طرفي؟',
        'طبعاً، اتفضل في أي وقت ومن غير مشكلة.',
        'آخر سعر لو الدفع كاش؟ أنا جاد وناوي أحجز.',
        'الأخير {price}، وفي حالة الحجز بنكتب عقد مبدئي.',
    ];

    private const REAL_ESTATE = [
        'السلام عليكم، "{title}" لسه متاحة؟',
        'وعليكم السلام، أيوه متاحة ومتاح معاينتها.',
        'الشقة أو الوحدة جاهزة للسكن ولا محتاجة تشطيب؟',
        'جاهزة للاستلام والمرافق كلها متوصلة ومتسجلة.',
        'وسعرها النهائي كام؟ وفي تقسيط ولا كاش؟',
        'السعر {price}، وفي مرونة بسيطة مع الدفع كاش.',
        'ممكن نتقابل الجمعة بعد الظهر أشوفها في الموقع؟',
        'تمام، هبعتلك اللوكيشن على واتساب ونتقابل عند المدخل.',
    ];

    private const SERVICE = [
        'مرحباً، عايز أعرف تفاصيل "{title}" لو سمحت.',
        'أهلاً بحضرتك، اتفضل قولي إيه المطلوب بالظبط وأنا أقولك المدة والتكلفة.',
        'المطلوب في منطقتي ومحتاجه خلال الأسبوع ده.',
        'تمام، ممكن أعدي أعاين وأديك سعر نهائي من غير التزام.',
        'التكلفة التقريبية {price} وبتتحدد بعد المعاينة.',
        'مناسب جداً، إمتى تقدر تيجي؟',
        'بكرة الصبح لو ينفع، وأنا هكلمك قبلها بساعة.',
        'ينفع جداً، مستنيك وهجهز كل حاجة.',
    ];

    private const JOB = [
        'السلام عليكم، شفت إعلان "{title}" وحابب أعرف التفاصيل.',
        'وعليكم السلام، أهلاً بيك. عندك خبرة قد إيه في المجال ده؟',
        'عندي خبرة كام سنة في نفس التخصص، ومستعد أبدأ فوراً.',
        'تمام، ممكن تبعتلي السيرة الذاتية على واتساب؟',
        'جاري الإرسال دلوقتي، وياريت أعرف مواعيد الدوام والراتب التقريبي.',
        'الدوام من 9 لـ 5، والراتب حسب الخبرة بعد المقابلة الشخصية.',
        'تمام، مستني ميعاد المقابلة إن شاء الله.',
        'هراجع السيرة الذاتية وأرجعلك بالرد خلال يومين.',
    ];

    private const VEHICLE_CATEGORIES = ['cars-for-sale', 'cars-for-rent', 'motorcycles', 'car-parts'];

    private const REAL_ESTATE_CATEGORIES = ['apartments-for-sale', 'apartments-for-rent', 'villas', 'land', 'shops-and-offices'];

    private const SERVICE_CATEGORIES = ['maintenance-and-finishing', 'transport-and-shipping', 'lessons-and-courses', 'events-and-photography', 'other-services'];

    private const JOB_CATEGORIES = ['job-vacancies', 'job-seekers'];

    public static function create(Listing $listing, User $buyer, int $unreadCount = 0, int $daysAgo = 1, bool $endsWithBuyer = false): ?Conversation
    {
        $listing->loadMissing(['user', 'category']);
        $seller = $listing->user;

        if ($seller === null || $seller->is($buyer)) {
            return null;
        }

        $existing = Conversation::query()->where(['listing_id' => $listing->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id])->exists();

        if ($existing) {
            return null;
        }

        $lines = self::linesFor($listing);

        if ($endsWithBuyer) {
            $lines = array_slice($lines, 0, count($lines) - 1);
        }

        $price = $listing->price !== null ? number_format((float) $listing->price).' '.config('classifieds.currency_label') : 'أقل قليلاً من المعروض';
        $started = now()->subDays($daysAgo)->subMinutes(random_int(20, 240));

        $conversation = Conversation::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'last_message_at' => $started,
        ]);

        $total = count($lines);
        $lastMessage = null;

        $cursor = $started->copy();

        foreach ($lines as $index => $line) {
            $sentAt = $cursor = $cursor->copy()->addMinutes(random_int(3, 40));
            $fromBuyer = $index % 2 === 0;
            $unread = $index >= $total - $unreadCount && $unreadCount > 0;

            $lastMessage = $conversation->messages()->create([
                'sender_id' => $fromBuyer ? $buyer->id : $seller->id,
                'body' => str_replace(['{title}', '{price}'], [$listing->title, $price], $line),
                'read_at' => $unread ? null : $sentAt->copy()->addMinutes(2),
            ]);

            $lastMessage->forceFill(['created_at' => $sentAt, 'updated_at' => $sentAt])->save();
        }

        $conversation->update(['last_message_at' => $lastMessage?->created_at ?? $started]);

        if ($unreadCount > 0 && $lastMessage !== null) {
            self::notifyRecipient($lastMessage->load('conversation.listing', 'sender'), $lastMessage->sender_id === $buyer->id ? $seller : $buyer);
        }

        return $conversation;
    }

    private static function linesFor(Listing $listing): array
    {
        $slug = $listing->category?->slug;

        return match (true) {
            in_array($slug, self::VEHICLE_CATEGORIES, true) => self::VEHICLE,
            in_array($slug, self::REAL_ESTATE_CATEGORIES, true) => self::REAL_ESTATE,
            in_array($slug, self::SERVICE_CATEGORIES, true) => self::SERVICE,
            in_array($slug, self::JOB_CATEGORIES, true) => self::JOB,
            default => self::GOODS,
        };
    }

    private static function notifyRecipient(Message $message, User $recipient): void
    {
        $recipient->notifyNow(new NewMessageReceived($message));

        DB::table('notifications')
            ->where('notifiable_id', $recipient->id)
            ->where('notifiable_type', $recipient->getMorphClass())
            ->where('type', NewMessageReceived::class)
            ->orderByDesc('created_at')
            ->limit(1)
            ->update(['created_at' => $message->created_at, 'updated_at' => $message->created_at]);
    }
}
