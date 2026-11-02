<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_local_numbers_get_the_country_code(): void
    {
        $this->assertSame('+8801712345678', PhoneNumber::normalize('01712-345678'));
        $this->assertSame('+8801712345678', PhoneNumber::normalize('০১৭১২৩৪৫৬৭৮'));
        $this->assertSame('+8801712345678', PhoneNumber::normalize('008801712345678'));
        $this->assertSame('+8801712345678', PhoneNumber::normalize('+880 1712 345678'));
    }

    public function test_the_country_code_comes_from_config(): void
    {
        config(['planner.phone_country_code' => '45']);

        $this->assertSame('+4512345678', PhoneNumber::normalize('012345678'));
    }

    public function test_valid_and_invalid_numbers(): void
    {
        $this->assertTrue(PhoneNumber::isValid('01712345678'));
        $this->assertTrue(PhoneNumber::isValid('+8801712345678'));
        $this->assertFalse(PhoneNumber::isValid('12'));
        $this->assertFalse(PhoneNumber::isValid('abc'));
        $this->assertFalse(PhoneNumber::isValid('1712345678'));
    }

    public function test_it_tells_phones_from_emails(): void
    {
        $this->assertTrue(PhoneNumber::looksLikePhone('01712 345678'));
        $this->assertFalse(PhoneNumber::looksLikePhone('rina@example.com'));
    }

    public function test_it_masks_the_middle_of_a_number(): void
    {
        $this->assertSame('+880 17•• •••678', PhoneNumber::mask('+8801712345678'));
    }
}
