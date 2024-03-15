<?php

namespace App\Utility;

use App\Services\TelegramService;

class SupergroupTelegramUtility
{
    public static function message(TelegramService $telegramService)
    {
        $telegramService->sendMessage(text: 'This is a supergroup chat');
    }
}
