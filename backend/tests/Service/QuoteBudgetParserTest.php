<?php

namespace App\Tests\Service;

use App\Service\QuoteBudgetParser;
use PHPUnit\Framework\TestCase;

final class QuoteBudgetParserTest extends TestCase
{
    private QuoteBudgetParser $parser;

    protected function setUp(): void
    {
        $this->parser = new QuoteBudgetParser();
    }

    public function testParsesPlainNumber(): void
    {
        self::assertSame('5000.00', $this->parser->parse('5000'));
    }

    public function testParsesFormattedNumberWithSpacesAndCurrencySymbol(): void
    {
        self::assertSame('5000.00', $this->parser->parse('5 000 €'));
    }

    public function testKeepsOnlyTheFirstNumberOfARange(): void
    {
        self::assertSame('5000.00', $this->parser->parse('5000-8000'));
    }

    public function testParsesDecimalWithComma(): void
    {
        self::assertSame('1500.50', $this->parser->parse('1500,50'));
    }

    public function testReturnsNullForNullOrEmptyInput(): void
    {
        self::assertNull($this->parser->parse(null));
        self::assertNull($this->parser->parse(''));
        self::assertNull($this->parser->parse('   '));
    }

    public function testReturnsNullWhenNoNumberIsPresent(): void
    {
        self::assertNull($this->parser->parse('à discuter'));
    }

    public function testReturnsNullForZeroOrNegativeAmount(): void
    {
        self::assertNull($this->parser->parse('0'));
    }
}
