<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public static function inputs(): array
    {
        return [
            'plain' => ['185000', 18500000],
            'bangla digits with lakh grouping' => ['১,৮৫,০০০', 18500000],
            'danish thousands' => ['185.000', 18500000],
            'danish decimal' => ['185.000,50', 18500050],
            'english decimal' => ['185,000.50', 18500050],
            'single comma decimal' => ['12,5', 1250],
            'with symbol' => ['৳ 5,000', 500000],
            'empty' => ['', null],
            'text' => ['abc', null],
        ];
    }

    #[DataProvider('inputs')]
    public function test_it_parses_what_people_type(string $input, ?int $expected): void
    {
        $this->assertSame($expected, Money::parse($input));
    }

    public function test_it_formats_in_bangla_with_lakh_grouping(): void
    {
        $this->assertSame('৳১,৮৫,০০০', Money::format(18500000, 'BDT', 'bn'));
    }

    public function test_it_formats_in_english(): void
    {
        $this->assertSame('৳1,85,000', Money::format(18500000, 'BDT', 'en'));
        $this->assertSame('185.000,50 kr.', Money::format(18500050, 'DKK', 'en'));
        $this->assertSame('−৳500', Money::format(-50000, 'BDT', 'en'));
    }

    public function test_it_calculates_the_vat_part_of_a_gross_amount(): void
    {
        $this->assertSame(2500, Money::vatPart(12500, 25));
        $this->assertSame(0, Money::vatPart(12500, 0));
    }
}
