<?php

namespace App\Http\Controllers;

use App\Models\Send;
use App\Models\User;
use App\Services\TelegramService;

class SendController extends Controller
{
    public function send()
    {
        $send = Send::where('status', 'pending');
        $send = $send->first();

        if (!$send) return;

        $users = User::whereNotNull('chat_id')
            ->limit($send->limit)
            ->offset($send->last_count)
            ->get();
        $type = $send->type;

        $telegramService = new TelegramService;

        foreach ($users as $user) {
            $telegramService->setUserId((int) $user->chat_id);

            if ($type == 'photo')
                $telegramService->sendPhoto($send->photos, $send->message);
            elseif ($type == 'video')
                $telegramService->sendVideo([
                    'chat_id' => $user->chat_id,
                    'video' => $send->video,
                    'caption' => $send->message ?? ''
                ]);
            elseif ($type == 'message')
                $telegramService->sendMessage(text: $send->message);
            elseif ($type == 'forward')
                $telegramService->forwardMessage([
                    'chat_id' => $user->chat_id,
                    'from_chat_id' => $send->forward_from_chat_id,
                    'message_id' => $send->forward_from_message_id
                ]);

            $send->last_count++;
        }

        if ($send->last_count >= $users->count()) {
            $send->status = 'completed';

            $telegramService->setUserId((int) env('ADMIN_ID'));
            $telegramService->sendMessage(text: '✅ *Xabarlar yuborish tugallandi!*');
        }

        $send->save();
        return;
    }
}
