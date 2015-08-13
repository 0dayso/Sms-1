<?php

Route::get('sms/info', 'ULan\Sms\Controllers\SmsController@getInfo');

Route::post('sms/verify-code/rule/{rule}/mobile/{mobile?}', 'ULan\Sms\Controllers\SmsController@postSendCode');

Route::post('sms/voice-verify/rule/{rule}/mobile/{mobile?}', 'ULan\Sms\Controllers\SmsController@postVoiceVerify');
