<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;
use Saizul\CourierExport\Template\Column;
use Saizul\CourierExport\Template\ColumnParser;
use Saizul\CourierExport\Template\TemplateException;

final class ColumnParserTest extends TestCase {

	public function test_parses_lines_comments_and_blank_lines(): void {
		$columns = ColumnParser::parse( "# comment\n\nInvoice = {order.number}\r\nName   =   {recipient.name}  \nEmpty =\nNote = a = b" );
		$this->assertCount( 4, $columns );
		$this->assertSame( 'Invoice', $columns[0]->header );
		$this->assertSame( '{order.number}', $columns[0]->template );
		$this->assertSame( '{recipient.name}', $columns[1]->template );
		$this->assertSame( '', $columns[2]->template );
		$this->assertSame( 'a = b', $columns[3]->template );
	}

	public function test_missing_separator(): void {
		$this->expectException( TemplateException::class );
		$this->expectExceptionMessage( 'Line 2' );
		ColumnParser::parse( "A = 1\nB 2" );
	}

	public function test_empty_header(): void {
		$this->expectException( TemplateException::class );
		ColumnParser::parse( ' = {order.number}' );
	}

	public function test_duplicate_header_case_insensitive(): void {
		$this->expectException( TemplateException::class );
		$this->expectExceptionMessage( 'duplicate header' );
		ColumnParser::parse( "Phone = {a}\nphone = {b}" );
	}

	public function test_no_columns(): void {
		$this->expectException( TemplateException::class );
		ColumnParser::parse( "# only comments\n\n" );
	}

	public function test_dump_round_trip(): void {
		$columns = array( new Column( 'Invoice', '{order.number}' ), new Column( 'Name', '{recipient.name}' ) );
		$text    = ColumnParser::dump( $columns );
		$this->assertSame( "Invoice = {order.number}\nName    = {recipient.name}", $text );
		$this->assertEquals( $columns, ColumnParser::parse( $text ) );
	}
}
