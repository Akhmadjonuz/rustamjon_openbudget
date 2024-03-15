<?php

namespace App\Http\Controllers;

use App\Services\RuCaptchaService;
use App\Traits\HttpResponse;
use Illuminate\Http\Request;

class CaptchaController extends Controller
{
    use HttpResponse;

    public function test(Request $request)
    {
        $rucaptcha = new RuCaptchaService;

        return response()->json([
            'response' => $rucaptcha->createTask(
                $request->base64Image,
                $request->math
            )
        ]);
    }
}
