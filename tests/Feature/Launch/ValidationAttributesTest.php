<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;

it('uses our field names in validation messages regardless of lang/ar/validation.php', function () {
    // "phone" is also defined by the laravel-lang package as «الهاتف»; ours must win.
    $validator = Validator::make([], ['phone' => 'required']);

    $validator->fails();

    expect($validator->errors()->first('phone'))->toContain('رقم الهاتف')
        ->and($validator->errors()->first('phone'))->not->toContain('حقل الهاتف');
});

it('still lets a request supply its own attribute names', function () {
    $validator = Validator::make([], ['phone' => 'required'], [], ['phone' => 'جوال المعلن']);
    $validator->fails();

    expect($validator->errors()->first('phone'))->toContain('جوال المعلن');
});

it('survives a lang:update that rewrites lang/ar/validation.php', function () {
    $path = lang_path('ar/validation.php');
    $original = file_get_contents($path);

    try {
        // what the package does: replace its own keys, including "phone"
        $rewritten = str_replace("'phone' => 'رقم الهاتف'", "'phone' => 'الهاتف'", $original);
        file_put_contents($path, $rewritten);
        app('translator')->setLoaded([]);

        $validator = Validator::make([], ['phone' => 'required']);
        $validator->fails();

        expect($validator->errors()->first('phone'))->toContain('رقم الهاتف');
    } finally {
        file_put_contents($path, $original);
        app('translator')->setLoaded([]);
    }
});
