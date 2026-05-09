<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class UploadLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('logs_parser.max_upload_mb', 512) * 1024;

        return [
            'log_file' => [
                'required',
                'file',
                'max:'.$maxKb,
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $file = $this->file('log_file');
            if ($file === null) {
                return;
            }

            $path = $file->getRealPath();
            if ($path === false || !is_readable($path)) {
                $v->errors()->add('log_file', 'Файл не удалось прочитать.');
                return;
            }

            $head = @file_get_contents($path, false, null, 0, 8192);
            if ($head === false || $head === '') {
                $v->errors()->add('log_file', 'Файл пустой или нечитаемый.');
                return;
            }

            if (str_contains($head, "\0")) {
                $v->errors()->add('log_file', 'Файл бинарный, ожидается текстовый лог.');
                return;
            }

            $printable = preg_match_all('/[\x09\x0A\x0D\x20-\x7E]/', $head);
            if ($printable === false || $printable < strlen($head) * 0.85) {
                $v->errors()->add('log_file', 'Файл не похож на текстовый лог.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'log_file.required' => 'Файл лога обязателен.',
            'log_file.file' => 'Загруженный объект не является файлом.',
            'log_file.max' => 'Файл превышает максимальный размер.',
        ];
    }
}
