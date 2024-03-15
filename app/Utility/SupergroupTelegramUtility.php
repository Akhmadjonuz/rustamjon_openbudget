<?php

namespace App\Utility;

use App\Models\Transaction;
use App\Services\TelegramService;

class SupergroupTelegramUtility
{
    public static function message(TelegramService $telegramService)
    {
    }


    public static function callback_query(TelegramService $telegramService)
    {
        $data = $telegramService->Callback_Data();

        if (mb_stripos($data, 'confirmed:') !== false) {
            $data = explode(':', $data);

            $transaction = Transaction::find($data[1]);
            $user = $transaction->user;

            $transaction->update(['status' => 'confirmed']);

            // send message to user
            $telegramService->sendMessage(
                text: '✅ *ID:* ' . $transaction->id . ' *raqamli tranzaksiya tasdiqlandi. Pul mablag\'i hisobingizga tushurildi.*',
            );

            // update keyboard
            $telegramService->EditMessageReplyMarkup([
                'chat_id' => config('services.telegram_bot.group_id'),
                'message_id' => $telegramService->MessageID(),
                'inline_keyboard' => [
                    [
                        ['text' => '✅✅✅', 'callback_data' => 'empty']
                    ]
                ]
            ]);

            return;
        }

        if (mb_stripos($data, 'rejected:') !== false) {
            $data = explode(':', $data);

            $transaction = Transaction::find($data[1]);
            $user = $transaction->user;

            $transaction->update(['status' => 'rejected']);

            // send message to user
            $telegramService->sendMessage(
                text: '❌ *ID:* ' . $transaction->id . ' *raqamli tranzaksiya rad etildi. Pul mablag\'i hisobingizga tushurilmadi.*',
            );

            // update keyboard
            $telegramService->EditMessageReplyMarkup([
                'chat_id' => config('services.telegram_bot.group_id'),
                'message_id' => $telegramService->MessageID(),
                'inline_keyboard' => [
                    [
                        ['text' => '❌❌❌', 'callback_data' => 'empty']
                    ]
                ]
            ]);
        }
    }
}
