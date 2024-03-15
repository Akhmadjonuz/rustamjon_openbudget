<?php

namespace App\Utility;

use App\Models\Project;
use App\Models\Send;
use App\Models\User;
use App\Models\Vote;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdminTelegramUtility
{
    private static array $user;
    private static array $project;
    private static array $adminCommands = [
        'admin' => '/admin',
        'send' => '🗣 Xabar yuborish',
        'forward' => '➡️ Forward reklama',
        'stat' => '📊 Statistika',
        'voicePriceChange' => '💰 Ovoz narxi',
        'withdrawChange' => '📲 Minimal summa',
        'projectChange' => '🔄 Tashabbus',
        'refferalAmountChange' => '👫 Referal summa'
    ];


    public static function message(TelegramService $telegramService, array $project, array $user)
    {
        self::$user = $user;
        self::$project = $project;

        $text = $telegramService->Text();

        if (in_array($text, self::$adminCommands)) {
            self::handleCommand($telegramService);
            return;
        }

        if (
            self::$user['step'] === 2 ||
            self::$user['step'] === 3 ||
            self::$user['step'] === 4 ||
            self::$user['step'] === 5
        ) {
            $project = Project::latest()->first();

            if (self::$user['step'] === 2) {
                self::updateProject(
                    $text,
                    $project->per_referral_amount,
                    $project->per_vote_amount,
                    $project->withdraw_amount,
                    $project->card_number,
                    $project->phone_number,
                    $project->password
                );

                $telegramService->sendMessage(
                    text: '✅ *Tashabbus muvaffaqiyatli o\'zgartirildi!*',
                    reply_markup: self::adminMenu($telegramService)
                );
            }

            if (self::$user['step'] === 3) {
                self::updateProject(
                    $project->endpoint,
                    $project->per_referral_amount,
                    $project->per_vote_amount,
                    $text,
                    $project->card_number,
                    $project->phone_number,
                    $project->password
                );
                $telegramService->sendMessage(
                    text: '✅ *Minimal summa muvaffaqiyatli o\'zgartirildi!*',
                    reply_markup: self::adminMenu($telegramService)
                );
            }

            if (self::$user['step'] === 4) {
                self::updateProject(
                    $project->endpoint,
                    $project->per_referral_amount,
                    $text,
                    $project->withdraw_amount,
                    $project->card_number,
                    $project->phone_number,
                    $project->password
                );
                $telegramService->sendMessage(
                    text: '✅ *Ovoz narxi muvaffaqiyatli o\'zgartirildi!*',
                    reply_markup: self::adminMenu($telegramService)
                );
            }

            if (self::$user['step'] === 5) {
                self::updateProject(
                    $project->endpoint,
                    $text,
                    $project->per_vote_amount,
                    $project->withdraw_amount,
                    $project->card_number,
                    $project->phone_number,
                    $project->password
                );
                $telegramService->sendMessage(
                    text: '✅ *Referal summasi muvaffaqiyatli o\'zgartirildi!*',
                    reply_markup: self::adminMenu($telegramService)
                );
            }

            self::setStep(0);
            return;
        }

        if (self::$user['step'] === 10) {
            if (Cache::get('send_type') !== 'forward' && Cache::get('send_type') !== 'message')
                self::errorType($telegramService);

            if (Cache::get('send_type') === 'forward') {
                self::fwd($telegramService);
            } elseif (Cache::get('send_type') === 'message') {
                Send::create([
                    'message' => $text,
                    'type' => 'message'
                ]);
            }

            self::successMessage($telegramService);
        }

        $telegramService->sendMessage(
            text: '❌ *Noma\'lum buyruq!*',
            reply_markup: PrivateTelegramUtility::mainMenu($telegramService)
        );
    }


    public static function callback_query(TelegramService $telegramService, array $project, array $user)
    {
        self::$user = $user;
        self::$project = $project;

        $callbackData = $telegramService->Callback_Data();

        $telegramService->deleteMessage();

        $telegramService->sendMessage(
            text: ucfirst($callbackData) . ' turidagi xabaringizni yuboring:',
            reply_markup: self::backAdminMenu($telegramService)
        );

        Cache::forever('send_type', $callbackData);

        self::setStep(10);
    }


    public static function video(TelegramService $telegramService)
    {
        self::$user = Cache::get('user_' . $telegramService->ChatID());
        self::$project = Cache::get('project');

        if (self::$user['step'] === 10) {
            if (Cache::get('send_type') !== 'video' && Cache::get('send_type') !== 'forward')
                self::errorType($telegramService);

            if (Cache::get('send_type') === 'video') {
                if ($telegramService->VideoId() === 'no video') {
                    $telegramService->sendMessage(
                        text: '❌ *Iltimos video yuboring!*',
                        reply_markup: self::backAdminMenu($telegramService)
                    );
                }

                Send::create([
                    'video' => $telegramService->VideoId(),
                    'message' => $telegramService->Caption() ?? NULL,
                    'type' => 'video'
                ]);
            } else {
                self::fwd($telegramService);
            }

            self::successMessage($telegramService);
        }
    }


    public static function photo(TelegramService $telegramService)
    {
        self::$user = Cache::get('user_' . $telegramService->ChatID());
        self::$project = Cache::get('project');

        if (self::$user['step'] === 10) {
            if (Cache::get('send_type') !== 'photo' && Cache::get('send_type') !== 'forward')
                self::errorType($telegramService);


            if (Cache::get('send_type') === 'photo') {
                if (isset($telegramService->getData()['message']['media_group_id'])) {
                    $telegramService->sendMessage(
                        text: '❌ *Iltimos bir nechta rasm yubormang!*',
                        reply_markup: self::backAdminMenu($telegramService)
                    );
                    return;
                }

                Send::create([
                    'photos' => $telegramService->PhotoId(),
                    'message' => $telegramService->Caption() ?? NULL,
                    'type' => 'photo'
                ]);
            } else {
                self::fwd($telegramService);
            }

            self::successMessage($telegramService);
        }
    }



    public static function adminMenu(TelegramService $telegramService)
    {
        return $telegramService->buildKeyBoard([
            [
                $telegramService->buildKeyboardButton(text: '🗣 Xabar yuborish'),
                $telegramService->buildKeyboardButton(text: '📊 Statistika')
            ],
            [
                $telegramService->buildKeyboardButton(text: '➡️ Forward reklama'),
                $telegramService->buildKeyboardButton(text: '📲 Minimal summa')
            ],
            [
                $telegramService->buildKeyboardButton(text: '💰 Ovoz narxi'),
                $telegramService->buildKeyboardButton(text: '🔄 Tashabbus')
            ],
            [
                $telegramService->buildKeyboardButton(text: '👫 Referal summa')
            ]
        ], true, true, false);
    }


    // admin dashboard
    private static function admin(TelegramService $telegramService)
    {
        if ($telegramService->isAdmin()) {
            Cache::forget('send_type');

            $telegramService->sendMessage(
                text: '*Admin panelga xush kelibsiz!*',
                reply_markup: self::adminMenu($telegramService)
            );

            self::setStep(0);
        }
    }


    private static function stat(TelegramService $telegramService)
    {
        $users = User::all();
        $votes = Vote::orderBy('created_at', 'desc');
        $votesPendingCount = $votes->where('status', 'pending')->count();
        $votesCompletedCount = $votes->where('status', 'completed')->count();
        $votesRejectedCount = $votes->where('status', 'rejected')->count();

        $telegramService->sendMessage(
            text: '*📊 Statistika*' . PHP_EOL . PHP_EOL .
                '👤 *Foydalanuvchilar:* ' . $users->count() . 'ta' . PHP_EOL .
                '🗳 *Ovozlar:* ' . $votes->count() . 'ta' . PHP_EOL .
                '🕒 *Kutilmoqda:* ' . $votesPendingCount . 'ta' . PHP_EOL .
                '✅ *Tasdiqlangan:* ' . $votesCompletedCount . 'ta' . PHP_EOL .
                '❌ *Rad etilgan:* ' . $votesRejectedCount . 'ta',
            reply_markup: self::adminMenu($telegramService)
        );

        self::setStep(0);
    }


    public static function forward(TelegramService $telegramService)
    {
        self::checkSend($telegramService);

        $telegramService->sendMessage(
            text: '*Reklama yuboring:*',
            reply_markup: self::backAdminMenu($telegramService)
        );

        Cache::forever('send_type', 'forward');
        self::setStep(10);
    }


    public static function send(TelegramService $telegramService)
    {
        self::checkSend($telegramService);

        $telegramService->sendMessage(
            text: '*Qanday turdagi xabar yuborishni xohlaysiz?*',
            reply_markup: self::sendAdminMenu($telegramService)
        );
        return;
    }


    private static function projectChange(TelegramService $telegramService)
    {
        $telegramService->sendMessage(
            text: '*Hozirgi tashabbus:*' . PHP_EOL . self::$project['endpoint'] . PHP_EOL . PHP_EOL .
                '*Yangi tashabbusni yuboring:*',
            reply_markup: self::backAdminMenu($telegramService)
        );

        self::setStep(2);
    }


    private static function withdrawChange(TelegramService $telegramService)
    {
        $telegramService->sendMessage(
            text: '*Hozirgi minimal summa: ' . self::$project['withdraw_amount'] . ' so\'m*' . PHP_EOL . PHP_EOL .
                '*Yangi minimal summani yuboring:*',
            reply_markup: self::backAdminMenu($telegramService)
        );

        self::setStep(3);
    }


    private static function voicePriceChange(TelegramService $telegramService)
    {
        $telegramService->sendMessage(
            text: '*Hozirgi ovoz narxi: ' . self::$project['per_vote_amount'] . ' so\'m*' . PHP_EOL . PHP_EOL .
                '*Yangi ovoz narxini yuboring:*',
            reply_markup: self::backAdminMenu($telegramService)
        );

        self::setStep(4);
    }


    public static function refferalAmountChange(TelegramService $telegramService)
    {
        $telegramService->sendMessage(
            text: '*Hozirgi summasi: ' . self::$project['per_referral_amount'] . ' so\'m*' . PHP_EOL . PHP_EOL .
                '*Yangi referal summasini yuboring:*',
            reply_markup: self::backAdminMenu($telegramService)
        );

        self::setStep(5);
    }


    private static function backAdminMenu(TelegramService $telegramService)
    {
        return $telegramService->buildKeyBoard([
            [
                $telegramService->buildKeyboardButton('/admin')
            ]
        ], true, true, false);
    }


    private static function sendAdminMenu(TelegramService $telegramService)
    {
        return $telegramService->buildInlineKeyBoard([
            [
                $telegramService->buildInlineKeyboardButton(text: 'Matnli xabar', callback_data: 'message'),
                $telegramService->buildInlineKeyboardButton(text: 'Rasmli xabar', callback_data: 'photo')
            ],
            [
                $telegramService->buildInlineKeyboardButton(text: 'Video xabar', callback_data: 'video'),
            ]
        ], true, true, false);
    }


    private static function setStep($step)
    {
        self::$user['step'] = $step;
        Cache::forever('user_' . self::$user['chat_id'], self::$user);
    }


    public static function handleCommand(TelegramService $telegramService)
    {
        switch ($telegramService->Text()) {
            case self::$adminCommands['admin']:
                self::admin($telegramService);
                break;
            case self::$adminCommands['send']:
                self::send($telegramService);
                break;
            case self::$adminCommands['forward']:
                self::forward($telegramService);
                break;
            case self::$adminCommands['stat']:
                self::stat($telegramService);
                break;
            case self::$adminCommands['voicePriceChange']:
                self::voicePriceChange($telegramService);
                break;
            case self::$adminCommands['withdrawChange']:
                self::withdrawChange($telegramService);
                break;
            case self::$adminCommands['projectChange']:
                self::projectChange($telegramService);
                break;
            case self::$adminCommands['refferalAmountChange']:
                self::refferalAmountChange($telegramService);
                break;
        }
    }


    public static function fwd(TelegramService $telegramService)
    {
        if (empty($telegramService->FromChatID()) || empty($telegramService->ForwardFromMessageID())) {
            $telegramService->sendMessage(
                text: '❌ *Iltimos xabarni kanaldan forward qiling!*',
                reply_markup: self::backAdminMenu($telegramService)
            );
            exit();
        } else {
            Send::create([
                'forward_from_chat_id' => $telegramService->FromChatID(),
                'forward_from_message_id' => $telegramService->ForwardFromMessageID(),
                'type' => 'forward'
            ]);
        }
    }


    public static function errorType(TelegramService $telegramService)
    {
        $telegramService->sendMessage(
            text: '❌ *Iltimos ' . Cache::get('send_type') . ' turdagi xabar yuboring!*',
            reply_markup: self::backAdminMenu($telegramService)
        );

        return;
    }


    public static function successMessage(TelegramService $telegramService)
    {
        $telegramService->sendMessage(
            text: '✅ *Xabar muvaffaqiyatli saqlandi!*' . PHP_EOL . PHP_EOL .
                '*1 daqiqadan so\'ng xabar yuborish boshlandi.*',
            reply_markup: self::adminMenu($telegramService)
        );

        self::setStep(0);
        exit();
    }


    public static function checkSend(TelegramService $telegramService)
    {
        $send = Send::where('status', 'pending')->get();

        if ($send->count() !== 0) {
            $telegramService->sendMessage(
                text: '❌ *Avvalgi xabar yuborilmaguncha yangi xabar yubora olmaysiz!*',
                reply_markup: self::backAdminMenu($telegramService)
            );

            exit();
        }
    }


    private static function updateProject(
        $endpoint,
        $per_referral_amount,
        $per_vote_amount,
        $withdraw_amount,
        $card_number,
        $phone_number,
        $password,
    ) {
        $project = Project::latest()->first();

        $pr = new Project;
        if ($endpoint != $project->endpoint) {
            $pr->endpoint = $endpoint;
        } else {
            $pr->endpoint = $project->endpoint;
        }

        if ($per_referral_amount != $project->per_referral_amount) {
            $pr->per_referral_amount = $per_referral_amount;
        } else {
            $pr->per_referral_amount = $project->per_referral_amount;
        }

        if ($per_vote_amount != $project->per_vote_amount) {
            $pr->per_vote_amount = $per_vote_amount;
        } else {
            $pr->per_vote_amount = $project->per_vote_amount;
        }

        if ($withdraw_amount != $project->withdraw_amount) {
            $pr->withdraw_amount = $withdraw_amount;
        } else {
            $pr->withdraw_amount = $project->withdraw_amount;
        }

        if ($card_number != $project->card_number) {
            $pr->card_number = $card_number;
        } else {
            $pr->card_number = $project->card_number;
        }

        if ($phone_number != $project->phone_number) {
            $pr->phone_number = $phone_number;
        } else {
            $pr->phone_number = $project->phone_number;
        }

        if ($password != $project->password) {
            $pr->password = $password;
        } else {
            $pr->password = $project->password;
        }

        $pr->save();

        Cache::forever('project', $pr->toArray());
        return;
    }
}
