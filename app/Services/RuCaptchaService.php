<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

set_time_limit(0);

class RuCaptchaService
{
    public function createTask(string $base64Image, $math = false)
    {
        $response = Http::post(config('services.rucaptcha.endpoint') . '/createTask', [
            'clientKey' => config('services.rucaptcha.api_key'),
            'task' => [
                'type' => 'ImageToTextTask',
                'body' => $base64Image,
                'phrase' => false,
                'case' => false,
                'numeric' => 1,
                'math' => ($math == true) ? 1 : 0,
                'minLength' => ($math == true) ? 1 : 3,
                'maxLength' => ($math == true) ? 2 : 3,
                'comment' => ($math == true) ? 'Write the result of the math' : 'Write the number from the image',
            ],
            'softId' => '3898',
            'languagePool' => 'en',
        ]);

        if ($response->successful()) {
            if ($response->json('errorId') === 0) {
                recheck:

                $resp = $this->getTaskResult($response->json('taskId'));

                // check if the response is solution text
                if (isset($resp['solution']['text']) && !empty($resp['solution']['text'])) {
                    Log::info('Captcha solved: ' . $resp['solution']['text']);

                    if ($math == true) {
                        if (mb_stripos($resp['solution']['text'], '=') !== false) {
                            $equal = explode('=', $resp['solution']['text']);

                            $equal = array_map('trim', $equal);

                            if (mb_stripos($equal[0], '+') !== false) {
                                $numbers = explode('+', $equal[0]);
                                return (int)$numbers[0] + (int)$numbers[1];
                            } elseif (mb_stripos($equal[0], '-') !== false) {
                                $numbers = explode('-', $equal[0]);
                                return (int)$numbers[0] - (int)$numbers[1];
                            } elseif (mb_stripos($equal[0], '*') !== false) {
                                $numbers = explode('*', $equal[0]);
                                return (int)$numbers[0] * (int)$numbers[1];
                            } elseif (mb_stripos($equal[0], '/') !== false) {
                                $numbers = explode('/', $equal[0]);
                                return (int)$numbers[0] / (int)$numbers[1];
                            }
                        }
                    }

                    return $resp['solution']['text'];
                } else {
                    sleep(6);
                    goto recheck;
                }
            }
        }
    }


    public function getTaskResult(int $taskId)
    {
        $response = Http::post(config('services.rucaptcha.endpoint') . '/getTaskResult', [
            'clientKey' => config('services.rucaptcha.api_key'),
            'taskId' => $taskId
        ]);


        if ($response->successful()) {
            return $response->json();
        }
    }
}
