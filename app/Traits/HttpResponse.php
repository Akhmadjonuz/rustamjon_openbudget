<?php

namespace App\Traits;

use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

trait HttpResponse
{

  public function __construct()
  {
  }

  /**
   * Error message http response json
   * @param \Exception $e
   * @return void
   */
  protected function log($e): void
  {
    $bot = new TelegramService;

    if ($bot->getUserId())
      $bot->sendMessage(text: 'Sorry, something went wrong. Please try again later.');

    return;
  }
}
