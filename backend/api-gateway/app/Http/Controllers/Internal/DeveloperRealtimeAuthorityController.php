<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\DeveloperRealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeveloperRealtimeAuthorityController extends Controller
{
    public function __construct(private readonly DeveloperRealtimeService $realtime) {}

    public function authorize(Request $request): JsonResponse
    {
        $data=$request->validate(['session_id'=>['required','string','max:160'],'environment'=>['required','in:sandbox,production']]);
        try{return response()->json(['data'=>$this->realtime->authorizeTransport($data['session_id'],$data['environment'])]);}
        catch(\Throwable){return response()->json(['error'=>['code'=>'REALTIME_AUTH_DENIED']],403);}
    }

    public function replay(Request $request): JsonResponse
    {
        $data=$request->validate(['session_id'=>['required','string','max:160'],'environment'=>['required','in:sandbox,production'],'stream'=>['required','string','max:120'],'after_sequence'=>['required','integer','min:0'],'limit'=>['nullable','integer','min:1','max:500']]);
        try{return response()->json(['data'=>$this->realtime->transportReplay($data['session_id'],$data['environment'],$data['stream'],(int)$data['after_sequence'],(int)($data['limit']??500))]);}
        catch(\Throwable){return response()->json(['error'=>['code'=>'REALTIME_REPLAY_DENIED']],403);}
    }
}
