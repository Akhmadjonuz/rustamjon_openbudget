<?php

namespace App\Utility;

use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vote;
use App\Services\TelegramService;
use App\Traits\HttpResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrivateTelegramUtility
{
    use HttpResponse;

    private static array $user;
    private static array $project;
    private static array $commands = [
        'voice' => '🗣 Ovoz berish',
        'balance' => '💰 Hisobim',
        'rating' => '🏆 Reyting',
        'referral' => '👫 Referal',
        'contact' => '💌 Biz bilan aloqa',
    ];


    public static function message(TelegramService $telegramService)
    {
        $text = $telegramService->Text();

        if ($text === '/start') {

            // if is admin remove send_type         
            if ($telegramService->isAdmin()) {
                Cache::forget('send_type');
            }

            $telegramService->sendMessage(
                text: 'Assalomu alaykum, ' . self::$user['full_name'] . '!',
                reply_markup: self::mainMenu($telegramService)
            );

            return;
        }

        if (self::$user['step'] === 1) {
            if (!preg_match('/^\+998\d{9}$/', $text)) {
                $telegramService->sendMessage(
                    text: '❌ *Telefon raqamingizni noto\'g\'ri yozdingiz. Iltimos, qaytadan urinib ko\'ring.*' . PHP_EOL . PHP_EOL .
                        'Namuna: +998901234567',
                    reply_markup: self::removeKeyboard($telegramService)
                );
                return;
            }

            // check vote
            $check = Vote::where('phone_number', $text)->first();

            if ($check) {
                if ($check->status === 'completed') {
                    $telegramService->sendMessage(
                        text: '❌ *Ushbu raqamdan allaqachon ovoz berilgan!*',
                        reply_markup: self::mainMenu($telegramService)
                    );
                } elseif ($check->status === 'pending' && $check->user_id === self::$user['id']) {
                    $telegramService->sendMessage(
                        text: '❌ *Ushbu raqam tekshirilmoqda. Tekshirilgandan so\'ng sizga xabar yuboriladi.*',
                        reply_markup: self::mainMenu($telegramService)
                    );
                } elseif ($check->status === 'rejected' && $check->user_id === self::$user['id']) {
                    $telegramService->sendMessage(
                        text: '❌ *Ushbu raqam tekshirilgan va rad etilgan. Iltimos, qaytadan urinib ko\'ring.*',
                        reply_markup: self::mainMenu($telegramService)
                    );
                } else {
                    $telegramService->sendMessage(
                        text: '❌ *Ushbu raqam tekshirilmoqda. Iltimos, qaytadan urinib ko\'ring.*',
                        reply_markup: self::mainMenu($telegramService)
                    );
                }

                self::setStep(0);
                return;
            }

            // update user phone number
            self::$user['phone_number'] = $text;
            Cache::forever('user_' . self::$user['chat_id'], self::$user);

            $user = User::find(self::$user['id']);
            $user->phone_number = $text;
            $user->save();

            $telegramService->sendMessage(
                text: '🗣 Ovoz berish uchun quyidagi tugmani bosing:',
                reply_markup: self::voiceMenu($telegramService)
            );

            self::setStep(0);
            return;
        }

        // withdraw with phone
        if (self::$user['step'] === 20) {
            if (!preg_match('/^\+998\d{9}$/', $text)) {
                $telegramService->sendMessage(
                    text: '❌ *Telefon raqamingizni noto\'g\'ri yozdingiz. Iltimos, qaytadan urinib ko\'ring.*' . PHP_EOL . PHP_EOL .
                        'Namuna: +998901234567',
                    reply_markup: self::removeKeyboard($telegramService)
                );
                return;
            }

            $user = User::find(self::$user['id']);

            $trans = Transaction::create([
                'user_id' => self::$user['id'],
                'amount' => $user->balance,
                'phone_number' => $text
            ]);

            $group_id = config('services.telegram_bot.group_id');
            $telegramService->sendMessage(
                chat_id: $group_id,
                text: '📢 *Yangi so\'rov keldi:*' . PHP_EOL . PHP_EOL .
                    '🆔 *ID:* ' . $trans->id . PHP_EOL .
                    '👤 *Foydalanuvchi:* ' . $user->full_name . PHP_EOL .
                    '📞 *Telefon raqami:* ' . $text . PHP_EOL .
                    '💰 *Summa:* ' . number_format($user->balance, 0, '', ' ') . ' so\'m',
                reply_markup: self::withdrawAcceptMenu($telegramService, $trans)
            );

            $telegramService->sendMessage(
                text: '✅ *ID: ' . $trans->id . '*' . PHP_EOL . PHP_EOL .
                    '*Telefon raqam:* ' . $text . PHP_EOL .
                    '*Summa:* ' . number_format($user->balance, 0, '', ' ') . ' so\'m' . PHP_EOL . PHP_EOL .
                    '*Sizning so\'rovingiz qabul qilindi. 24 soat ichida sizga xabar yuboriladi.*',
                reply_markup: self::mainMenu($telegramService)
            );

            $user->balance = 0;
            $user->save();

            self::setStep(0);
            self::$user['balance'] = 0;
            Cache::forever('user_' . self::$user['chat_id'], self::$user);
            return;
        }


        // withdraw with card
        if (self::$user['step'] === 21) {
            if (!preg_match('/^\d{16}$/', $text)) {
                $telegramService->sendMessage(
                    text: '❌ *Karta raqamingizni noto\'g\'ri yozdingiz. Iltimos, qaytadan urinib ko\'ring.*' . PHP_EOL . PHP_EOL .
                        'Namuna: 8600120422223333',
                    reply_markup: self::removeKeyboard($telegramService)
                );
                return;
            }

            $user = User::find(self::$user['id']);

            $trans = Transaction::create([
                'user_id' => self::$user['id'],
                'amount' => $user->balance,
                'card_number' => $text
            ]);

            $group_id = config('services.telegram_bot.group_id');
            $telegramService->sendMessage(
                chat_id: $group_id,
                text: '📢 *Yangi so\'rov keldi:*' . PHP_EOL . PHP_EOL .
                    '🆔 *ID:* ' . $trans->id . PHP_EOL .
                    '👤 *Foydalanuvchi:* ' . $user->full_name . PHP_EOL .
                    '💳 *Karta raqami:* ' . $text . PHP_EOL .
                    '💰 *Summa:* ' . number_format($user->balance, 0, '', ' ') . ' so\'m',
                reply_markup: self::withdrawAcceptMenu($telegramService, $trans)
            );

            $telegramService->sendMessage(
                text: '✅ *ID: ' . $trans->id . '*' . PHP_EOL . PHP_EOL .
                    '*Karta raqam:* ' . $text . PHP_EOL .
                    '*Summa:* ' . number_format($user->balance, 0, '', ' ') . ' so\'m' . PHP_EOL . PHP_EOL .
                    '*Sizning so\'rovingiz qabul qilindi. 24 soat ichida sizga xabar yuboriladi.*',
                reply_markup: self::mainMenu($telegramService)
            );

            $user->balance = 0;
            $user->save();

            self::setStep(0);
            self::$user['balance'] = 0;
            Cache::forever('user_' . self::$user['chat_id'], self::$user);
            return;
        }

        if (in_array($text, self::$commands)) {
            self::handleCommand($telegramService);
            return;
        } elseif ($telegramService->isAdmin()) {
            AdminTelegramUtility::message($telegramService, self::$project, self::$user);
            return;
        }
    }


    public static function callback_query(TelegramService $telegramService)
    {
        $data = $telegramService->Callback_Data();

        if ($data === 'referral') {
            $telegramService->editMessageText(
                text: '✅ <b>OpenBudget portalining ovoz yig\'ish boti</b> 🤖' . PHP_EOL . PHP_EOL .
                    '🎈' . (!is_null(self::$user['username']) ? '@' . self::$user['username'] : self::$user['full_name']) .
                    ' do\'stingizdan unikal havola-taklifnoma.' . PHP_EOL . PHP_EOL .
                    '👇 Boshlash uchun bosing:' . PHP_EOL . PHP_EOL .
                    'https://t.me/' . config('services.telegram_bot.username') . '?start=' . $telegramService->getUserId(),
                parse_mode: 'HTML',
                reply_markup: self::refferalShareMenu($telegramService)
            );

            return;
        }


        if (mb_stristr($data, 'checkVoice:') !== false) {
            $ex = explode(':', $data);

            $vote = new Vote;
            $vote->project_id = self::$project['id'];
            $vote->user_id = self::$user['id'];
            $vote->phone_number = $ex[1];
            $vote->status = 'pending';
            $vote->save();

            $telegramService->deleteMessage();
            $telegramService->sendMessage(
                text: '🔍 *Ovozingizni tekshirilmoqda sizga 30 daqiqa ichida xabar beramiz.*',
                reply_markup: self::mainMenu($telegramService)
            );

            return;
        }

        if ($data === 'withdraw') {
            $telegramService->editMessageText(
                text: '💰 *Pullaringizni qaysi hisobga yechmoqchisiz ?*',
                reply_markup: self::withdrawTypeMenu($telegramService)
            );

            return;
        }

        if ($data === 'withPhone') {
            $telegramService->editMessageText(
                text: '📞 *Telefon raqamingizni yozing:*' . PHP_EOL . PHP_EOL .
                    '*Namuna: +998901234567*',
            );
            self::setStep(20);
            return;
        }

        if ($data === 'withCard') {
            $telegramService->editMessageText(
                text: '💳 *Karta raqamingizni yozing:*' . PHP_EOL . PHP_EOL .
                    '*Namuna: 8600120422223333*',
            );

            self::setStep(21);
            return;
        }



        if ($telegramService->isAdmin()) {
            AdminTelegramUtility::callback_query($telegramService, self::$project, self::$user);
        }
    }


    public static function inline_query(TelegramService $telegramService)
    {
        $check = User::where('chat_id', $telegramService->InlineQuery())->first();

        if ($check) {
            $telegramService->answerInlineQuery([
                'inline_query_id' => $telegramService->getData()['inline_query']['id'],
                'results' => json_encode([
                    [
                        'type' => 'article',
                        'id' => '1',
                        'title' => 'OpenBudget portalining ovoz yig\'ish boti 🤖',
                        'input_message_content' => [
                            'message_text' => '✅ <b>OpenBudget portalining ovoz yig\'ish boti</b> 🤖' . PHP_EOL . PHP_EOL .
                                '🎈' . (!is_null(self::$user['username']) ? '@' . self::$user['username'] : self::$user['full_name']) .
                                ' do\'stingizdan unikal havola-taklifnoma.' . PHP_EOL . PHP_EOL .
                                '👇 Boshlash uchun bosing:' . PHP_EOL . PHP_EOL .
                                'https://t.me/' . config('services.telegram_bot.username') . '?start=' . $telegramService->getUserId(),
                            'parse_mode' => 'HTML'
                        ],
                        'reply_markup' => json_decode(self::refferalShareMenu($telegramService), true)
                    ]
                ])
            ]);
        }
    }


    public static function video(TelegramService $telegramService)
    {
        if ($telegramService->isAdmin()) {
            AdminTelegramUtility::video($telegramService, self::$project, self::$user);
        }
    }


    public static function photo(TelegramService $telegramService)
    {
        if ($telegramService->isAdmin()) {
            AdminTelegramUtility::photo($telegramService, self::$project, self::$user);
        }
    }


    private static function balance(TelegramService $telegramService)
    {
        if (!isset(self::$user['balance'])) {
            self::handleDefaultCase($telegramService);
        }

        $telegramService->sendMessage(
            text: 'Sizning hisobingiz: ' . number_format(self::$user['balance'], 0, '', ' ') . ' so\'m' . PHP_EOL . PHP_EOL .
                'Minimal pul yechish: ' . number_format(self::$project['withdraw_amount'], 0, '', ' ') . ' so\'m',
            reply_markup: (self::$user['balance'] >= self::$project['withdraw_amount']) ? self::withdrawMenu($telegramService) : self::mainMenu($telegramService)
        );
    }


    private static function voice(TelegramService $telegramService)
    {
        $telegramService->sendMessage(
            text: '🗣 *Ovoz berish uchun telefon raqamingizni yozing:*' . PHP_EOL .
                '*Namuna: +998901234567*',
            reply_markup: self::removeKeyboard($telegramService)
        );

        self::setStep(1);
    }


    private static function rating(TelegramService $telegramService)
    {
        $votes = Vote::with('project', 'user')->where('status', 'completed')->distinct('chat_id')->get();

        $temp = '🏆 *TOP 10 ta eng koʻp ovoz bergan foydalanuvchilar:*' . PHP_EOL . PHP_EOL;

        $i = 1;
        foreach ($votes as $vote) {
            $temp .= $i . ') ' . $vote->user->full_name . ' | ' . $vote->user->vote_count . ' ovoz | ' . $vote->user->balance . ' so\'m' . PHP_EOL;
            $i++;
        }

        $temp .= PHP_EOL . '*Har hafta eng ko\'p ovoz to\'plagan TOP 3 ta foydalanuvchi rag\'batlantiriladi!*';

        $telegramService->sendMessage(
            text: $temp,
            reply_markup: self::mainMenu($telegramService)
        );
    }


    private static function referral(TelegramService $telegramService)
    {
        $telegramService->sendMessage(
            text: '❓ *DO\'STLARINGIZNI TAKLIF QILIB ' . self::$project['per_referral_amount'] . ' SO\'M SOHIBI BO\'LISHNI ISTAYSIZMI?*' . PHP_EOL . PHP_EOL .
                '🔗 O\'zingizning unikal promo-havolagangizni do\'stlaringizga ulashing.' . PHP_EOL . PHP_EOL .
                '👫 Promo-havolani do\'stlaringizga yuboring, botimizda ovoz berishga' .
                'yordam bering va sizning havolangiz orqali kirib ovoz bergan har bir do\'stingiz uchun ' . self::$project['per_referral_amount'] . ' so\'m sohibi bo\'ling!',
            reply_markup: self::refferalMenu($telegramService)
        );
    }


    private static function contact(TelegramService $telegramService)
    {
        $telegramService->sendMessage(
            text: 'Admin: @admin',
            reply_markup: self::mainMenu($telegramService)
        );
    }


    private static function handleCommand(TelegramService $telegramService)
    {
        switch ($telegramService->Text()) {
            case self::$commands['voice']:
                self::voice($telegramService);
                break;
            case self::$commands['balance']:
                self::balance($telegramService);
                break;
            case self::$commands['rating']:
                self::rating($telegramService);
                break;
            case self::$commands['referral']:
                self::referral($telegramService);
                break;
            case self::$commands['contact']:
                self::contact($telegramService);
                break;
        }
    }


    private static function setStep($step)
    {
        self::$user['step'] = $step;
        Cache::forever('user_' . self::$user['chat_id'], self::$user);
    }


    private static function voiceMenu(TelegramService $telegramService)
    {
        $endpoins = json_decode(self::$project['endpoint'], true);

        // get random endpoint
        $end = $endpoins[array_rand($endpoins)];

        $button = ['text' => '🗣 Ovoz berish', 'web_app' => ['url' => $end]];

        return $telegramService->buildInlineKeyBoard([
            [$button],
            [$telegramService->buildInlineKeyboardButton(text: '✅ Ovoz berdim', callback_data: 'checkVoice:' . self::$user['phone_number'])]
        ]);
    }


    public static function mainMenu(TelegramService $telegramService)
    {
        return $telegramService->buildKeyBoard([
            [$telegramService->buildKeyboardButton('🗣 Ovoz berish')],
            [$telegramService->buildKeyboardButton('💰 Hisobim'), $telegramService->buildKeyboardButton('🏆 Reyting')],
            [$telegramService->buildKeyboardButton('👫 Referal'), $telegramService->buildKeyboardButton('💌 Biz bilan aloqa')]
        ], true, true, false);
    }


    public static function withdrawMenu(TelegramService $telegramService)
    {
        return $telegramService->buildInlineKeyBoard([
            [$telegramService->buildInlineKeyboardButton(text: 'Pul yechish', callback_data: 'withdraw')]
        ]);
    }


    public static function withdrawTypeMenu(TelegramService $telegramService)
    {
        return $telegramService->buildInlineKeyBoard([
            [$telegramService->buildInlineKeyboardButton(text: '📞 Telefon raqamga', callback_data: 'withPhone')],
            [$telegramService->buildInlineKeyboardButton(text: '💳 Karta raqamga', callback_data: 'withCard')]
        ]);
    }


    public static function withdrawAcceptMenu(TelegramService $telegramService, Transaction $transaction)
    {
        return $telegramService->buildInlineKeyBoard([
            [$telegramService->buildInlineKeyboardButton(text: '✅ Tasdiqlash', callback_data: 'confirmed:' . $transaction->id)],
            [$telegramService->buildInlineKeyboardButton(text: '❌ Bekor qilish', callback_data: 'rejected:' . $transaction->id)]
        ]);
    }


    public static function refferalMenu(TelegramService $telegramService)
    {
        return $telegramService->buildInlineKeyBoard([
            [$telegramService->buildInlineKeyboardButton(text: '🔗 Promo-havolani olish', callback_data: 'referral')]
        ]);
    }


    public static function refferalShareMenu(TelegramService $telegramService)
    {
        return $telegramService->buildInlineKeyBoard([
            [$telegramService->buildInlineKeyboardButton(text: '↗️ Do\'stlarga ulashish', switch_inline_query: $telegramService->getUserId())]
        ]);
    }


    private static function removeKeyboard(TelegramService $telegramService)
    {
        return $telegramService->buildKeyBoardHide(true);
    }


    public static function OnStart(TelegramService $telegramService)
    {
        self::$project = Cache::remember('project', now()->addDay(), function () {
            return Project::latest()->first()->toArray();
        });

        $userId = $telegramService->getUserId();
        $userCacheKey = 'user_' . $userId;

        self::$user = Cache::remember($userCacheKey, now()->addDay(), function () use ($telegramService) {
            return User::firstOrCreate([
                'chat_id' => $telegramService->getUserId(),
                'username' => $telegramService->Username(),
                'full_name' => $telegramService->FirstName() . ' ' . $telegramService->LastName(),
            ])->toArray();
        });

        if ($telegramService->Text() === '/start' || mb_strpos($telegramService->Text(), '/start ') !== false) {
            self::handleStartCommand($telegramService);
        } else {
            self::handleDefaultCase($telegramService);
        }
    }


    private static function handleStartCommand(TelegramService $telegramService)
    {
        self::$user['step'] = 0;
        self::$user['balance'] = self::$user['balance'] ?? 0;

        Cache::forever('user_' . $telegramService->getUserId(), self::$user);

        if (mb_strpos($telegramService->Text(), '/start ') !== false) {
            self::handleReferralStart($telegramService);
        }
    }


    private static function handleReferralStart(TelegramService $telegramService)
    {
        try {
            $ex = explode(' ', $telegramService->Text());

            if ($ex[1] === $telegramService->getUserId()) {
                $telegramService->sendMessage('Siz o\'zingizni do\'st sifatida ro\'yxatdan o\'tkaza olmaysiz.');
                return;
            }

            DB::beginTransaction();

            $telegramService->sendMessage(
                text: 'Assalomu alaykum, ' . self::$user['full_name'] . '!',
                reply_markup: self::mainMenu($telegramService)
            );

            $referrer = User::where('chat_id', $ex[1])->first();
            $usr = User::where('chat_id', $telegramService->getUserId())->first();

            if ($referrer && $usr && is_null($usr->referrer_id)) {
                // update referrer id
                $usr->referrer_id = $referrer->id;
                $usr->save();

                $referrer->increment('balance', self::$project['per_referral_amount']);
                $telegramService->sendMessage(
                    chat_id: $referrer->chat_id,
                    text: 'Sizni do\'stingiz ' . self::$user['full_name'] .
                        ' ro\'yxatdan o\'tdi. Sizga ' .
                        self::$project['per_referral_amount'] . ' so\'m berildi.'
                );

                // update referrer_id cache
                $referrerData = $referrer->toArray();
                $referrerData['step'] = Cache::get('user_' . $referrer->chat_id)['step'];

                Cache::forever('user_' . $referrer->chat_id, $referrerData);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
        }
    }


    private static function handleDefaultCase(TelegramService $telegramService)
    {
        $user = User::where('chat_id', $telegramService->getUserId())->first()->toArray();

        $user['step'] = self::$user['step'] ?? 0;
        $user['balance'] = self::$user['balance'] ?? 0;

        Cache::forever('user_' . $telegramService->getUserId(), $user);
    }
}
