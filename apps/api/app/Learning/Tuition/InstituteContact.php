<?php

declare(strict_types=1);

namespace App\Learning\Tuition;

use Illuminate\Contracts\Config\Repository;

final readonly class InstituteContact
{
    public function __construct(
        private Repository $config,
    ) {}

    /**
     * @return array{phone: ?string, whatsapp: ?string, email: ?string}
     */
    public function learnerPayload(): array
    {
        return [
            'phone' => $this->phone(),
            'whatsapp' => $this->whatsappDigits(),
            'email' => $this->email(),
        ];
    }

    private function phone(): ?string
    {
        $value = $this->string('tuition.institute_contact.phone');
        if ($value === '') {
            return null;
        }

        if (preg_match('/\A\+[1-9][0-9]{7,14}\z/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private function whatsappDigits(): ?string
    {
        $value = $this->string('tuition.institute_contact.whatsapp');
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (preg_match('/\A[1-9][0-9]{9,14}\z/', $digits) !== 1) {
            return null;
        }

        return $digits;
    }

    private function email(): ?string
    {
        $value = $this->string('tuition.institute_contact.email');
        if ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $value;
    }

    private function string(string $key): string
    {
        $value = $this->config->get($key);

        return is_string($value) ? trim($value) : '';
    }
}
