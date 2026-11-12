<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\Export;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saizul\CourierExport\Export\CsvWriter;

final class CsvWriterTest extends TestCase {

	public function test_basic_file_with_bom_and_crlf(): void {
		$csv = ( new CsvWriter() )->to_string( array( 'a', 'b' ), array( array( '1', '2' ), array( 'x', 'y' ) ) );
		$this->assertSame( CsvWriter::BOM . "a,b\r\n1,2\r\nx,y\r\n", $csv );
	}

	public function test_without_bom_and_with_lf(): void {
		$csv = ( new CsvWriter( ',', "\n", false ) )->to_string( array( 'a' ), array( array( '1' ) ) );
		$this->assertSame( "a\n1\n", $csv );
	}

	public function test_quoting(): void {
		$w = new CsvWriter();
		$this->assertSame( '"a,b"', $w->cell( 'a,b' ) );
		$this->assertSame( '"say ""hi"""', $w->cell( 'say "hi"' ) );
		$this->assertSame( "\"line\nbreak\"", $w->cell( "line\nbreak" ) );
		$this->assertSame( '" padded "', $w->cell( ' padded ' ) );
		$this->assertSame( 'plain', $w->cell( 'plain' ) );
		$this->assertSame( '', $w->cell( '' ) );
		$this->assertSame( 'রহিম উদ্দিন', $w->cell( 'রহিম উদ্দিন' ) );
	}

	public function test_semicolon_and_tab_delimiters(): void {
		$this->assertSame( "a,b;c\r\n", ( new CsvWriter( ';' ) )->line( array( 'a,b', 'c' ) ) );
		$this->assertSame( "\"a\tb\"\tc\r\n", ( new CsvWriter( "\t" ) )->line( array( "a\tb", 'c' ), false ) );
	}

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public function formulaProvider(): array {
		return array(
			'equals'   => array( '=HYPERLINK("x")', '"\'=HYPERLINK(""x"")"' ),
			'plus'     => array( '+cmd', "'+cmd" ),
			'minus'    => array( '-2+3', "'-2+3" ),
			'at'       => array( '@SUM(A1)', "'@SUM(A1)" ),
			'tab'      => array( "\tx", "'\tx" ),
			'negative' => array( '-5', '-5' ),
			'intl'     => array( '+8801711234567', '+8801711234567' ),
		);
	}

	/**
	 * @dataProvider formulaProvider
	 */
	public function test_formula_protection( string $value, string $expected ): void {
		$this->assertSame( $expected, ( new CsvWriter() )->cell( $value ) );
	}

	public function test_headers_are_not_prefixed_and_protection_can_be_disabled(): void {
		$csv = ( new CsvWriter( ',', "\n", false, false ) )->to_string( array( '=h' ), array( array( '=x' ) ) );
		$this->assertSame( "=h\n=x\n", $csv );
	}

	public function test_write_to_stream(): void {
		$stream = fopen( 'php://memory', 'w+' );
		( new CsvWriter( ',', "\n", true ) )->write( $stream, array( 'h' ), array( array( 'v' ) ) );
		rewind( $stream );
		$this->assertSame( CsvWriter::BOM . "h\nv\n", stream_get_contents( $stream ) );
		fclose( $stream );
	}

	public function test_write_requires_stream(): void {
		$this->expectException( InvalidArgumentException::class );
		( new CsvWriter() )->write( 'nope', array(), array() );
	}

	public function test_invalid_delimiter(): void {
		$this->expectException( InvalidArgumentException::class );
		new CsvWriter( '"' );
	}

	public function test_invalid_eol(): void {
		$this->expectException( InvalidArgumentException::class );
		new CsvWriter( ',', "\r" );
	}
}
