<?php

namespace App\Services;

use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeService
{
    public function generate(string $code, string $format = 'EAN13'): string
    {
        $generator = new BarcodeGeneratorSVG;
        $fmt = strtoupper($format);

        if ($fmt === 'EAN13') {
            return $generator->getBarcode($code, $generator::TYPE_EAN_13);
        }

        return $generator->getBarcode($code, $generator::TYPE_CODE_128);
    }

    public function generateEan13(string $productId): string
    {
        $hash = strtoupper(str_replace('-', '', $productId));
        $num = substr(preg_replace('/[^0-9]/', '', $hash).sprintf('%u', crc32($hash)), 0, 9);
        $num = str_pad($num, 9, '0', STR_PAD_RIGHT);
        $base12 = '200'.$num;
        $check = $this->ean13CheckDigit($base12);

        return $base12.$check;
    }

    public function generateCode128(string $text): string
    {
        return $this->generate($text, 'CODE128');
    }

    public function isValidEan13(string $code): bool
    {
        if (! preg_match('/^\d{13}$/', $code)) {
            return false;
        }

        $base12 = substr($code, 0, 12);
        $check = (int) substr($code, 12, 1);

        return $this->ean13CheckDigit($base12) === $check;
    }

    private function ean13CheckDigit(string $base12): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $base12[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }

        return (10 - ($sum % 10)) % 10;
    }
}
