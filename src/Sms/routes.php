<?php

Route::get('sms/info', 'ULan\Sms\SmsController@getInfo');

Route::post('sms/verify-code/rule/{rule}/mobile/{mobile?}', 'ULan\Sms\SmsController@postSendCode');

Route::post('sms/voice-verify/rule/{rule}/mobile/{mobile?}', 'ULan\Sms\SmsController@postVoiceVerify');
