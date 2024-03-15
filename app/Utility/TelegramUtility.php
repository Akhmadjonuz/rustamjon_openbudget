<?php

namespace App\Utility;

use App\Services\TelegramService;
use App\Traits\HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramUtility
{
    use HttpResponse;

    public static function handle(TelegramService $telegramService, string $method)
    {
        if ($telegramService->isPrivateChat()) {
            PrivateTelegramUtility::OnStart($telegramService);
            return PrivateTelegramUtility::$method($telegramService);
        } elseif ($telegramService->isSuperGroupChat()) {
            SupergroupTelegramUtility::$method($telegramService);
        } elseif ($telegramService->isGroupChat()) {
            $telegramService->sendMessage(text: 'I am not allowed to chat in groups');
        }
    }
}
