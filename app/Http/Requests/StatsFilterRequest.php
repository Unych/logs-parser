<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Parsing\UserAgentInfo;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class StatsFilterRequest extends FormRequest
{
    public const BOTS_ALL = 'all';
    public const BOTS_HUMANS = 'humans';
    public const BOTS_BOTS = 'bots';

    public const SORT_FIELDS = ['date', 'requests', 'top_url', 'top_browser'];
    public const SORT_DIRS = ['asc', 'desc'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to'   => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'os'        => ['nullable', 'string', 'in:'.implode(',', $this->validOsValues())],
            'arch'      => ['nullable', 'string', 'in:'.implode(',', $this->validArchValues())],
            'bots'      => ['nullable', 'string', 'in:'.self::BOTS_ALL.','.self::BOTS_HUMANS.','.self::BOTS_BOTS],
            'sort'      => ['nullable', 'string', 'in:'.implode(',', self::SORT_FIELDS)],
            'dir'       => ['nullable', 'string', 'in:'.implode(',', self::SORT_DIRS)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $from = $this->input('date_from');
            $to = $this->input('date_to');
            if (!is_string($from) || !is_string($to)) {
                return;
            }
            try {
                $start = CarbonImmutable::parse($from);
                $end = CarbonImmutable::parse($to);
            } catch (\Throwable) {
                return;
            }
            if (abs($start->diffInDays($end)) > 366) {
                $v->errors()->add('date_to', 'Диапазон дат не должен превышать 1 года.');
            }
        });
    }

    public function filters(): array
    {
        return [
            'date_from' => $this->validated('date_from'),
            'date_to'   => $this->validated('date_to'),
            'os'        => $this->validated('os'),
            'arch'      => $this->validated('arch'),
            'bots'      => $this->validated('bots') ?? self::BOTS_ALL,
            'sort'      => $this->validated('sort') ?? 'date',
            'dir'       => $this->validated('dir') ?? 'asc',
        ];
    }

    private function validOsValues(): array
    {
        return [
            UserAgentInfo::OS_WINDOWS,
            UserAgentInfo::OS_MACOS,
            UserAgentInfo::OS_LINUX,
            UserAgentInfo::OS_ANDROID,
            UserAgentInfo::OS_IOS,
            UserAgentInfo::OS_OTHER,
        ];
    }

    private function validArchValues(): array
    {
        return [
            UserAgentInfo::ARCH_X86,
            UserAgentInfo::ARCH_X64,
            UserAgentInfo::ARCH_UNKNOWN,
        ];
    }
}
