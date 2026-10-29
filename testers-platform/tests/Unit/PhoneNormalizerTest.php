<?php

namespace Tests\Unit;

use App\Support\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_accepted_formats_all_become_the_same_number(): void
    {
        foreach (['777123456', '0777123456', '+967777123456', '00967777123456', '967777123456',
                  '+967 777 123 456', '٠٧٧٧١٢٣٤٥٦'] as $input) {
            $this->assertSame('+967777123456', PhoneNormalizer::normalize($input), $input);
        }
    }

    public function test_invalid_numbers_return_null(): void
    {
        foreach (['', 'abc', '12345', '0577123456', '+966555123456', '77712345678'] as $input) {
            $this->assertNull(PhoneNormalizer::normalize($input), $input);
        }
    }
}