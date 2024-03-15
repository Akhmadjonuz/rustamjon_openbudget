<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\TelegramService;
use App\Utility\TelegramUtility;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    public function handle(Request $request)
    {
        $telegramService = new TelegramService();
        $telegramService->setData($request->all());
        TelegramUtility::handle($telegramService, $telegramService->getUpdateType());
    }
}
